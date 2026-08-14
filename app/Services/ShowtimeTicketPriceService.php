<?php

namespace App\Services;

use App\Domain\Showtimes\ShowtimeWindow;
use App\Exceptions\PriceBookException;
use App\Models\BookingSeat;
use App\Models\PriceBookVersion;
use App\Models\Room;
use App\Models\RoomLayout;
use App\Models\RoomLayoutCell;
use App\Models\Showtime;
use App\Models\ShowtimeTicketPrice;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use LogicException;

final class ShowtimeTicketPriceService
{
    public function __construct(private readonly VersionedTicketPricingService $resolver) {}

    /** @return Collection<int, ShowtimeTicketPrice> */
    public function preview(Room $room, RoomLayout $layout, ShowtimeWindow $window): Collection
    {
        if ((int) $layout->room_id !== (int) $room->id || $layout->status !== RoomLayout::STATUS_PUBLISHED) {
            throw new PriceBookException(
                PriceBookException::INVALID_ADJUSTMENT,
                'Showtime pricing requires the exact published RoomLayout selected by scheduling.',
            );
        }

        $room->loadMissing(['cinema', 'roomType']);
        $layout->loadMissing(['cells.seat.seatType']);
        $seatTypes = $layout->cells
            ->where('cell_type', RoomLayoutCell::TYPE_SEAT)
            ->map(function (RoomLayoutCell $cell) {
                $seat = $cell->seat;
                if (! $seat || ! $seat->seat_type_id || ! $seat->seatType || ! $seat->seatType->status) {
                    throw new PriceBookException(
                        PriceBookException::INVALID_ADJUSTMENT,
                        'Every structural Seat cell must reference one active logical SeatType.',
                    );
                }
                if ((string) $seat->type !== (string) $seat->seatType->code) {
                    throw new PriceBookException(
                        PriceBookException::INVALID_ADJUSTMENT,
                        'Physical Seat type and logical SeatType identity are inconsistent.',
                    );
                }

                return $seat->seatType;
            })
            ->unique('id')
            ->sortBy('id')
            ->values();

        if ($seatTypes->isEmpty()) {
            throw new PriceBookException(
                PriceBookException::INVALID_ADJUSTMENT,
                'A published RoomLayout must contain at least one priced structural Seat cell.',
            );
        }

        $snapshots = $seatTypes->map(function ($seatType) use ($room, $window): ShowtimeTicketPrice {
            $price = $this->resolver->resolve($room->cinema, $room, $room->roomType, $seatType, $window->start);

            return (new ShowtimeTicketPrice([
                'seat_type_id' => $seatType->id,
                'price_book_version_id' => $price->priceBookVersionId,
                'base_price_vnd' => $price->basePriceVnd,
                'adjustment_total_vnd' => array_sum(array_column($price->adjustments, 'amount_vnd')),
                'final_unit_amount_vnd' => $price->finalUnitAmountVnd,
                'breakdown_json' => $price->breakdown(),
                'pricing_fingerprint' => $price->fingerprint,
            ]))->setRelation('seatType', $seatType);
        });

        if ($snapshots->pluck('price_book_version_id')->unique()->count() !== 1) {
            throw new LogicException('Every logical SeatType snapshot for one Showtime must use one PriceBookVersion.');
        }

        return $snapshots;
    }

    /** @param Collection<int, ShowtimeTicketPrice> $snapshots */
    public function persist(Showtime $showtime, Collection $snapshots, bool $replace = false): void
    {
        if (! $showtime->exists || $snapshots->isEmpty()) {
            throw new LogicException('A persisted Showtime and a complete logical price set are required.');
        }
        if ($snapshots->pluck('price_book_version_id')->unique()->count() !== 1) {
            throw new LogicException('Every logical SeatType snapshot for one Showtime must use one PriceBookVersion.');
        }

        $version = PriceBookVersion::query()
            ->whereKey((int) $snapshots->first()->price_book_version_id)
            ->lockForUpdate()
            ->firstOrFail();
        $businessDate = (string) data_get($snapshots->first()->breakdown_json, 'business_date');
        if ($version->status !== PriceBookVersion::STATUS_PUBLISHED
            || $businessDate !== $showtime->show_date->format('Y-m-d')
            || $version->effective_from?->toDateString() > $businessDate
            || ($version->effective_until && $version->effective_until->toDateString() <= $businessDate)) {
            throw new PriceBookException(
                PriceBookException::VERSION_NOT_FOUND,
                'The resolved PriceBookVersion is no longer published for the Showtime business date.',
            );
        }

        if ($replace) {
            if (BookingSeat::query()->where('showtime_id', $showtime->id)->exists()) {
                throw new LogicException('Cannot replace Showtime prices after booking history exists.');
            }
            $showtime->ticketPrices()->get()->each->delete();
        } elseif ($showtime->ticketPrices()->exists()) {
            throw new LogicException('Showtime ticket price snapshots already exist.');
        }

        foreach ($snapshots as $snapshot) {
            $showtime->ticketPrices()->create([
                'seat_type_id' => $snapshot->seat_type_id,
                'price_book_version_id' => $snapshot->price_book_version_id,
                'base_price_vnd' => $snapshot->base_price_vnd,
                'adjustment_total_vnd' => $snapshot->adjustment_total_vnd,
                'final_unit_amount_vnd' => $snapshot->final_unit_amount_vnd,
                'breakdown_json' => $snapshot->breakdown_json,
                'pricing_fingerprint' => $snapshot->pricing_fingerprint,
            ]);
        }
    }

    /** @param list<array{showtime:Showtime,snapshots:Collection<int,ShowtimeTicketPrice>}> $items */
    public function persistBatch(array $items): void
    {
        if ($items === []) {
            return;
        }

        $versionIds = collect($items)
            ->flatMap(fn (array $item) => $item['snapshots']->pluck('price_book_version_id'))
            ->map(fn ($id): int => (int) $id)->unique()->sort()->values();
        $versions = PriceBookVersion::query()->whereIn('id', $versionIds)
            ->orderBy('id')->lockForUpdate()->get()->keyBy('id');
        if ($versions->count() !== $versionIds->count()) {
            throw new PriceBookException(PriceBookException::VERSION_NOT_FOUND, 'A resolved PriceBookVersion no longer exists.');
        }

        $showtimeIds = collect($items)->map(fn (array $item): int => (int) $item['showtime']->id);
        if ($showtimeIds->contains(0)
            || $showtimeIds->unique()->count() !== $showtimeIds->count()
            || ShowtimeTicketPrice::query()->whereIn('showtime_id', $showtimeIds)->exists()) {
            throw new LogicException('Each new Showtime requires one complete, previously unpersisted logical price set.');
        }

        $rows = [];
        $now = now();
        foreach ($items as $item) {
            $showtime = $item['showtime'];
            $snapshots = $item['snapshots'];
            if (! $showtime->exists || $snapshots->isEmpty()
                || $snapshots->pluck('price_book_version_id')->unique()->count() !== 1) {
                throw new LogicException('Each new Showtime requires one complete, previously unpersisted logical price set.');
            }
            foreach ($snapshots as $snapshot) {
                $businessDate = (string) data_get($snapshot->breakdown_json, 'business_date');
                $version = $versions->get((int) $snapshot->price_book_version_id);
                if ($version->status !== PriceBookVersion::STATUS_PUBLISHED
                    || $businessDate !== $showtime->show_date->format('Y-m-d')
                    || $version->effective_from?->toDateString() > $businessDate
                    || ($version->effective_until && $version->effective_until->toDateString() <= $businessDate)) {
                    throw new PriceBookException(
                        PriceBookException::VERSION_NOT_FOUND,
                        'The resolved PriceBookVersion is no longer published for a batch Showtime business date.',
                    );
                }
                $rows[] = [
                    'showtime_id' => $showtime->id,
                    'seat_type_id' => $snapshot->seat_type_id,
                    'price_book_version_id' => $snapshot->price_book_version_id,
                    'base_price_vnd' => $snapshot->base_price_vnd,
                    'adjustment_total_vnd' => $snapshot->adjustment_total_vnd,
                    'final_unit_amount_vnd' => $snapshot->final_unit_amount_vnd,
                    'breakdown_json' => json_encode($snapshot->breakdown_json, JSON_THROW_ON_ERROR),
                    'pricing_fingerprint' => $snapshot->pricing_fingerprint,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }
        }

        DB::table('showtime_ticket_prices')->insert($rows);
    }
}
