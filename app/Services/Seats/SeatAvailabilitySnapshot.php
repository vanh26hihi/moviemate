<?php

namespace App\Services\Seats;

use App\Models\BookingSeat;
use App\Models\RoomLayout;
use App\Models\RoomLayoutCell;
use App\Models\Showtime;
use Illuminate\Support\Collection;

/**
 * One bounded load of everything the seat-gap policy needs.
 *
 * Availability is always recomputed from the database; seat state supplied by the browser is
 * never trusted. Two bounded queries are used (layout cells + active holds), so adjacency can
 * then be evaluated entirely in memory without per-seat queries.
 */
final class SeatAvailabilitySnapshot
{
    /**
     * @param  Collection<int, RoomLayoutCell>  $cells
     * @param  list<int>  $unavailableSeatIds
     */
    private function __construct(
        public readonly RoomLayout $layout,
        public readonly Collection $cells,
        public readonly array $unavailableSeatIds,
    ) {}

    public static function for(
        Showtime $showtime,
        RoomLayout $layout,
        bool $lockHolds = false,
        ?int $excludeBookingId = null,
    ): self {
        $cells = $layout->relationLoaded('cells') && $layout->cells->isNotEmpty()
            ? $layout->cells
            : $layout->cells()->with('seat:id,status')->get();

        // Seats that are structurally unusable (maintenance / retired) are never bookable.
        $unavailable = $cells
            ->filter(fn ($cell): bool => $cell->cell_type === 'seat'
                && $cell->seat !== null
                && $cell->seat->status !== 'active')
            ->pluck('seat_id')
            ->map(fn ($id): int => (int) $id);

        $holds = BookingSeat::query()
            ->where('showtime_id', $showtime->id)
            ->where('active_lock_key', BookingSeat::ACTIVE_LOCK_KEY)
            ->when($excludeBookingId !== null, fn ($query) => $query->where('booking_id', '!=', $excludeBookingId))
            ->when($lockHolds, fn ($query) => $query->lockForUpdate())
            ->pluck('seat_id')
            ->map(fn ($id): int => (int) $id);

        return new self(
            $layout,
            $cells,
            $unavailable->merge($holds)->unique()->values()->all(),
        );
    }
}
