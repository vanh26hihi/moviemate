<?php

namespace App\Services;

use App\Domain\Pricing\VersionedTicketPrice;
use App\Exceptions\PriceBookException;
use App\Models\Cinema;
use App\Models\PriceBook;
use App\Models\PriceBookAdjustment;
use App\Models\PriceBookVersion;
use App\Models\Room;
use App\Models\RoomType;
use App\Models\SeatType;
use App\Models\Showtime;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

final class VersionedTicketPricingService
{
    private ?PriceBook $priceBook = null;

    /** @var array<string, PriceBookVersion> */
    private array $versionCache = [];

    /** @var array<int, Collection<int, PriceBookAdjustment>> */
    private array $adjustmentCache = [];

    public function resolve(
        Cinema $cinema,
        Room $room,
        RoomType $roomType,
        SeatType $seatType,
        CarbonInterface $showtimeLocalStart,
    ): VersionedTicketPrice {
        $timezone = $this->timezone($cinema);
        $localStart = CarbonImmutable::instance($showtimeLocalStart)->setTimezone($timezone);
        if ((int) $room->cinema_id !== (int) $cinema->id
            || (int) $room->room_type_id !== (int) $roomType->id) {
            throw new PriceBookException(
                PriceBookException::INVALID_ADJUSTMENT,
                'Resolver Cinema, Room, and RoomType context is inconsistent.',
            );
        }

        $businessDate = $localStart->toDateString();
        $version = $this->versionFor($businessDate);
        $matches = $this->matchingAdjustments(
            $version->adjustments,
            $cinema,
            $room,
            $roomType,
            $seatType,
            $localStart,
        );

        $ordered = [];
        foreach (['seat_type', 'room_type', 'time_window', 'weekend', 'holiday', 'cinema', 'room'] as $dimension) {
            if ($dimension === 'weekend' && isset($matches['holiday'])) {
                continue;
            }
            if (! isset($matches[$dimension])) {
                continue;
            }
            $adjustment = $matches[$dimension];
            $ordered[] = [
                'dimension' => $dimension,
                'adjustment_id' => (int) $adjustment->id,
                'label' => (string) $adjustment->label,
                'reference' => $this->reference($adjustment),
                'amount_vnd' => (int) $adjustment->amount_vnd,
            ];
        }

        $base = (int) $version->base_price_vnd;
        $final = $base + array_sum(array_column($ordered, 'amount_vnd'));
        if ($final <= 0 || $final > Showtime::MAX_PRICE) {
            throw new PriceBookException(
                PriceBookException::RESULT_NOT_POSITIVE,
                'Resolved logical ticket amount must be positive and within the supported VND range.',
            );
        }

        $identity = [
            'price_book_id' => (int) $this->priceBook->id,
            'price_book_code' => (string) $this->priceBook->code,
            'price_book_version_id' => (int) $version->id,
            'version_number' => (int) $version->version_number,
            'base_price_vnd' => $base,
            'adjustments' => array_map(
                fn (array $item): array => [$item['adjustment_id'], $item['dimension'], $item['amount_vnd']],
                $ordered,
            ),
            'final_unit_amount_vnd' => $final,
        ];

        return new VersionedTicketPrice(
            (int) $this->priceBook->id,
            (string) $this->priceBook->code,
            (int) $version->id,
            (int) $version->version_number,
            $businessDate,
            $base,
            $ordered,
            $final,
            hash('sha256', json_encode($identity, JSON_THROW_ON_ERROR)),
        );
    }

    private function versionFor(string $businessDate): PriceBookVersion
    {
        if (isset($this->versionCache[$businessDate])) {
            return $this->versionCache[$businessDate];
        }

        $this->priceBook ??= $this->loadSingleton();
        $versions = PriceBookVersion::query()
            ->where('price_book_id', $this->priceBook->id)
            ->where('status', PriceBookVersion::STATUS_PUBLISHED)
            ->where('effective_from', '<=', $businessDate)
            ->where(fn ($query) => $query->whereNull('effective_until')->orWhere('effective_until', '>', $businessDate))
            ->limit(2)
            ->get();

        if ($versions->isEmpty()) {
            throw new PriceBookException(
                PriceBookException::VERSION_NOT_FOUND,
                'No applicable published PriceBookVersion exists for the Cinema-local business date.',
            );
        }
        if ($versions->count() !== 1) {
            throw new PriceBookException(
                PriceBookException::VERSION_OVERLAP,
                'Multiple published PriceBookVersions apply to one business date.',
            );
        }

        $version = $versions->first();
        $version->setRelation(
            'adjustments',
            $this->adjustmentCache[$version->id] ??= $version->adjustments()->orderBy('id')->get(),
        );

        return $this->versionCache[$businessDate] = $version;
    }

    private function loadSingleton(): PriceBook
    {
        $books = PriceBook::query()->limit(2)->get();
        if ($books->count() !== 1 || $books->first()->code !== PriceBook::CHAIN_CODE) {
            throw new PriceBookException(
                PriceBookException::BOOK_NOT_FOUND,
                'Exactly one chain PriceBook authority must exist.',
            );
        }

        return $books->first();
    }

    /**
     * @param  Collection<int, PriceBookAdjustment>  $adjustments
     * @return array<string, PriceBookAdjustment>
     */
    private function matchingAdjustments(
        Collection $adjustments,
        Cinema $cinema,
        Room $room,
        RoomType $roomType,
        SeatType $seatType,
        CarbonImmutable $start,
    ): array {
        $matched = $adjustments->filter(function (PriceBookAdjustment $adjustment) use ($cinema, $room, $roomType, $seatType, $start): bool {
            return match ($adjustment->dimension) {
                'seat_type' => (int) $adjustment->seat_type_id === (int) $seatType->id,
                'room_type' => (int) $adjustment->room_type_id === (int) $roomType->id,
                'cinema' => (int) $adjustment->cinema_id === (int) $cinema->id,
                'room' => (int) $adjustment->room_id === (int) $room->id,
                'weekend' => in_array($start->dayOfWeekIso, array_map('intval', $adjustment->weekend_days ?? []), true),
                'holiday' => $adjustment->holiday_date_from->toDateString() <= $start->toDateString()
                    && $start->toDateString() < $adjustment->holiday_date_until->toDateString(),
                'time_window' => $this->timeMatches($start->format('H:i:s'), $adjustment->time_start, $adjustment->time_end),
                default => false,
            };
        })->groupBy('dimension');

        foreach ($matched as $dimension => $items) {
            if ($items->count() !== 1) {
                throw new PriceBookException(
                    PriceBookException::AMBIGUOUS_ADJUSTMENT,
                    "Multiple {$dimension} adjustments match one logical pricing context.",
                );
            }
        }

        return $matched->map(fn (Collection $items): PriceBookAdjustment => $items->first())->all();
    }

    private function timeMatches(string $time, ?string $start, ?string $end): bool
    {
        if (! $start || ! $end || $start === $end) {
            return false;
        }
        $start = substr($start, 0, 8);
        $end = substr($end, 0, 8);

        return $start < $end ? $time >= $start && $time < $end : $time >= $start || $time < $end;
    }

    private function reference(PriceBookAdjustment $adjustment): ?int
    {
        return match ($adjustment->dimension) {
            'seat_type' => $adjustment->seat_type_id,
            'room_type' => $adjustment->room_type_id,
            'cinema' => $adjustment->cinema_id,
            'room' => $adjustment->room_id,
            default => null,
        };
    }

    private function timezone(Cinema $cinema): string
    {
        $timezone = $cinema->timezone ?: config('cinema.timezone');
        if (! is_string($timezone) || ! in_array($timezone, timezone_identifiers_list(), true)) {
            throw new PriceBookException(
                PriceBookException::INVALID_ADJUSTMENT,
                'Cinema timezone is invalid for PriceBook resolution.',
            );
        }

        return $timezone;
    }
}
