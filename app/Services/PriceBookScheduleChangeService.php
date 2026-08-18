<?php

namespace App\Services;

use App\Exceptions\PriceBookException;
use App\Models\PriceBook;
use App\Models\PriceBookAdjustment;
use App\Models\PriceBookVersion;
use App\Models\SeatType;
use App\Models\Showtime;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

final class PriceBookScheduleChangeService
{
    public const KIND_FROM_DATE = 'from_date';

    public const KIND_SINGLE_DAY = 'single_day';

    public const KINDS = [self::KIND_FROM_DATE, self::KIND_SINGLE_DAY];

    public function __construct(private readonly PriceBookVersionService $versions) {}

    /** @param array<int|string, int|string> $ticketPrices */
    public function preview(
        PriceBookVersion $source,
        string $kind,
        string $changeDate,
        array $ticketPrices,
    ): array {
        $source->loadMissing('adjustments');

        return $this->plan($source, $kind, $changeDate, $ticketPrices);
    }

    /**
     * @param  array<int|string, int|string>  $ticketPrices
     * @return Collection<int, PriceBookVersion>
     */
    public function apply(
        PriceBookVersion $source,
        string $kind,
        string $changeDate,
        array $ticketPrices,
        ?User $actor = null,
    ): Collection {
        $book = $this->versions->chainPriceBook();

        return DB::transaction(function () use ($source, $kind, $changeDate, $ticketPrices, $actor, $book): Collection {
            PriceBook::query()->whereKey($book->id)->lockForUpdate()->firstOrFail();
            $locked = PriceBookVersion::query()
                ->whereKey($source->id)
                ->lockForUpdate()
                ->with('adjustments')
                ->firstOrFail();

            if ((int) $locked->price_book_id !== (int) $book->id) {
                throw new PriceBookException(
                    PriceBookException::BOOK_NOT_FOUND,
                    'The source version does not belong to the chain PriceBook.',
                );
            }

            $plan = $this->plan($locked, $kind, $changeDate, $ticketPrices);
            $this->versions->retire($locked, $actor);

            $published = collect();
            foreach ($plan['segments'] as $segment) {
                $draft = $this->versions->createDraft($book, [
                    'base_price_vnd' => $segment['base_price_vnd'],
                    'effective_from' => $segment['effective_from']->toDateString(),
                    'effective_until' => $segment['effective_until']?->toDateString(),
                ], $actor);
                $this->versions->replaceAdjustments($draft, $segment['definitions']);
                $published->push($this->versions->publish($draft, $actor));
            }

            return $published;
        }, 3);
    }

    /** @param array<int|string, int|string> $ticketPrices */
    private function plan(
        PriceBookVersion $source,
        string $kind,
        string $changeDate,
        array $ticketPrices,
    ): array {
        if ($source->status !== PriceBookVersion::STATUS_PUBLISHED) {
            throw new PriceBookException(
                PriceBookException::INVALID_TRANSITION,
                'Only a published PriceBookVersion schedule may be replaced.',
            );
        }
        if (! in_array($kind, self::KINDS, true)) {
            throw new PriceBookException(
                PriceBookException::INVALID_TRANSITION,
                'Unsupported PriceBook schedule change kind.',
            );
        }

        $from = $source->effective_from;
        $until = $source->effective_until;
        $date = CarbonImmutable::createFromFormat('!Y-m-d', $changeDate);
        if ($from === null || $date->lessThan($from) || ($until && ! $date->lessThan($until))) {
            throw new PriceBookException(
                PriceBookException::INVALID_TRANSITION,
                'The change date must be inside the published source period.',
            );
        }

        $seatTypes = $this->activeSeatTypes();
        $normal = $seatTypes->firstWhere('code', 'normal');
        if (! $normal) {
            throw new PriceBookException(
                PriceBookException::INVALID_ADJUSTMENT,
                'An active normal seat type is required.',
            );
        }
        $prices = $this->normalizedPrices($seatTypes, $ticketPrices);
        $sourceDefinitions = $source->adjustments
            ->map(fn (PriceBookAdjustment $adjustment): array => $this->definition($adjustment))
            ->values()
            ->all();
        $sourcePrices = $this->seatPrices($source, $seatTypes);
        $changedDefinitions = $this->changedDefinitions(
            $source,
            $seatTypes,
            $prices,
        );
        $changedUntil = $kind === self::KIND_SINGLE_DAY
            ? ($until && $date->addDay()->greaterThan($until) ? $until : $date->addDay())
            : $until;

        $segments = [];
        if ($from->lessThan($date)) {
            $segments[] = $this->segment(
                'before',
                'Giá hiện tại trước ngày thay đổi',
                $from,
                $date,
                (int) $source->base_price_vnd,
                $sourcePrices,
                $sourceDefinitions,
                $source->adjustments->where('dimension', '!=', 'seat_type')->count(),
            );
        }

        $segments[] = $this->segment(
            'changed',
            $kind === self::KIND_SINGLE_DAY ? 'Giá đặc biệt trong một ngày' : 'Giá mới từ ngày thay đổi',
            $date,
            $changedUntil,
            (int) $prices[(int) $normal->id],
            $prices,
            $changedDefinitions,
            $source->adjustments->where('dimension', '!=', 'seat_type')->count(),
        );

        if ($kind === self::KIND_SINGLE_DAY && ($until === null || $changedUntil->lessThan($until))) {
            $segments[] = $this->segment(
                'after',
                'Trở lại giá hiện tại sau ngày đặc biệt',
                $changedUntil,
                $until,
                (int) $source->base_price_vnd,
                $sourcePrices,
                $sourceDefinitions,
                $source->adjustments->where('dimension', '!=', 'seat_type')->count(),
            );
        }

        return [
            'source' => $source,
            'kind' => $kind,
            'change_date' => $date,
            'seat_types' => $seatTypes,
            'ticket_prices' => $prices,
            'segments' => $segments,
        ];
    }

    /** @param array<int|string, int|string> $ticketPrices */
    private function normalizedPrices(Collection $seatTypes, array $ticketPrices): array
    {
        $expected = $seatTypes->pluck('id')->map(fn ($id): int => (int) $id)->sort()->values()->all();
        $normalized = collect($ticketPrices)
            ->mapWithKeys(fn ($price, $seatTypeId): array => [(int) $seatTypeId => (int) $price])
            ->sortKeys()
            ->all();

        if (array_keys($normalized) !== $expected
            || collect($normalized)->contains(fn (int $price): bool => $price <= 0 || $price > Showtime::MAX_PRICE)) {
            throw new PriceBookException(
                PriceBookException::INVALID_ADJUSTMENT,
                'Ticket prices must exactly cover every active logical seat type.',
            );
        }

        return $normalized;
    }

    private function activeSeatTypes(): Collection
    {
        return SeatType::query()->where('status', true)
            ->orderByRaw("CASE code WHEN 'normal' THEN 1 WHEN 'vip' THEN 2 WHEN 'couple' THEN 3 ELSE 4 END")
            ->orderBy('sort_order')->orderBy('name')->get();
    }

    private function seatPrices(PriceBookVersion $version, Collection $seatTypes): array
    {
        $adjustments = $version->adjustments
            ->where('dimension', 'seat_type')
            ->keyBy(fn (PriceBookAdjustment $adjustment): int => (int) $adjustment->seat_type_id);

        return $seatTypes->mapWithKeys(fn (SeatType $seatType): array => [
            (int) $seatType->id => (int) $version->base_price_vnd
                + (int) ($adjustments->get((int) $seatType->id)?->amount_vnd ?? 0),
        ])->all();
    }

    private function changedDefinitions(
        PriceBookVersion $source,
        Collection $seatTypes,
        array $prices,
    ): array {
        $activeIds = $seatTypes->pluck('id')->map(fn ($id): int => (int) $id);
        $definitions = $source->adjustments->filter(
            fn (PriceBookAdjustment $adjustment): bool => $adjustment->dimension !== 'seat_type'
                || ! $activeIds->contains((int) $adjustment->seat_type_id),
        )->map(fn (PriceBookAdjustment $adjustment): array => $this->definition($adjustment))->values()->all();
        $normal = $seatTypes->firstWhere('code', 'normal');
        $base = (int) $prices[(int) $normal->id];
        $sourceLabels = $source->adjustments->where('dimension', 'seat_type')
            ->keyBy(fn (PriceBookAdjustment $adjustment): int => (int) $adjustment->seat_type_id);

        foreach ($seatTypes as $seatType) {
            $amount = (int) $prices[(int) $seatType->id] - $base;
            if ($amount === 0) {
                continue;
            }
            $definitions[] = [
                'dimension' => 'seat_type',
                'label' => $sourceLabels->get((int) $seatType->id)?->label ?? 'Giá '.$seatType->name,
                'amount_vnd' => $amount,
                'seat_type_id' => (int) $seatType->id,
            ];
        }

        return $definitions;
    }

    private function segment(
        string $purpose,
        string $label,
        CarbonImmutable $from,
        ?CarbonImmutable $until,
        int $basePrice,
        array $ticketPrices,
        array $definitions,
        int $contextualRuleCount,
    ): array {
        return [
            'purpose' => $purpose,
            'label' => $label,
            'effective_from' => $from,
            'effective_until' => $until,
            'base_price_vnd' => $basePrice,
            'ticket_prices' => $ticketPrices,
            'definitions' => $definitions,
            'contextual_rule_count' => $contextualRuleCount,
        ];
    }

    private function definition(PriceBookAdjustment $adjustment): array
    {
        return $adjustment->only([
            'dimension', 'label', 'amount_vnd', 'seat_type_id', 'room_type_id',
            'cinema_id', 'room_id', 'time_start', 'time_end',
            'holiday_date_from', 'holiday_date_until', 'weekend_days',
        ]);
    }
}
