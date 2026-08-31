<?php

namespace App\Services\Seats;

use App\Models\BookingSeat;
use App\Models\RoomLayout;
use App\Models\SeatHold;
use App\Models\Showtime;

final class SeatAvailabilityService
{
    /**
     * Build a compact seat status summary for a showtime and room layout.
     *
     * @return array{
     *   total: int,
     *   available: list<int>,
     *   held: list<int>,
     *   blocked: list<int>,
     *   sold: list<int>,
     *   status_by_seat: array<int, string>,
     *   available_count: int,
     *   held_count: int,
     *   blocked_count: int,
     *   sold_count: int
     * }
     */
    public function summary(Showtime $showtime, RoomLayout $layout): array
    {
        $cells = $layout->relationLoaded('cells') && $layout->cells->isNotEmpty()
            ? $layout->cells
            : $layout->cells()->with('seat:id,status')->get();

        $seatIds = $cells
            ->filter(fn ($cell): bool => $cell->cell_type === 'seat' && $cell->seat !== null)
            ->pluck('seat_id')
            ->map(fn ($seatId): int => (int) $seatId)
            ->unique()
            ->values()
            ->all();

        $soldIds = BookingSeat::query()
            ->where('showtime_id', $showtime->id)
            ->where('active_lock_key', BookingSeat::ACTIVE_LOCK_KEY)
            ->pluck('seat_id')
            ->map(fn ($seatId): int => (int) $seatId)
            ->unique()
            ->values()
            ->all();

        $heldIds = SeatHold::query()
            ->where('showtime_id', $showtime->id)
            ->where('expires_at', '>', now())
            ->pluck('seat_id')
            ->map(fn ($seatId): int => (int) $seatId)
            ->unique()
            ->values()
            ->all();

        $blockedIds = $cells
            ->filter(fn ($cell): bool => $cell->cell_type === 'seat'
                && $cell->seat !== null
                && $cell->seat->status !== 'active')
            ->pluck('seat_id')
            ->map(fn ($seatId): int => (int) $seatId)
            ->unique()
            ->values()
            ->all();

        $blockedSet = $this->toIdSet($blockedIds);
        $heldSet = $this->toIdSet($heldIds);
        $soldSet = $this->toIdSet($soldIds);
        $allSet = $this->toIdSet($seatIds);

        $available = array_values(array_diff(array_keys($allSet), array_keys($blockedSet + $heldSet + $soldSet)));
        $sold = array_values(array_diff(array_intersect(array_keys($allSet), array_keys($soldSet)), array_keys($blockedSet)));
        $blocked = array_values(array_keys($blockedSet));
        $held = array_values(array_diff(array_intersect(array_keys($allSet), array_keys($heldSet)), array_keys($blockedSet + $soldSet)));

        $statusBySeat = [];
        foreach ($seatIds as $seatId) {
            if (isset($blockedSet[$seatId])) {
                $statusBySeat[$seatId] = 'blocked';
            } elseif (isset($soldSet[$seatId])) {
                $statusBySeat[$seatId] = 'sold';
            } elseif (isset($heldSet[$seatId])) {
                $statusBySeat[$seatId] = 'held';
            } else {
                $statusBySeat[$seatId] = 'available';
            }
        }

        return [
            'total' => count($seatIds),
            'available' => $available,
            'held' => $held,
            'blocked' => $blocked,
            'sold' => $sold,
            'status_by_seat' => $statusBySeat,
            'available_count' => count($available),
            'held_count' => count($held),
            'blocked_count' => count($blocked),
            'sold_count' => count($sold),
        ];
    }

    /**
     * Returns the status for a single seat.
     */
    public function statusFor(Showtime $showtime, RoomLayout $layout, int $seatId): string
    {
        $summary = $this->summary($showtime, $layout);
        $status = $summary['status_by_seat'][$seatId] ?? 'unknown';

        return $status;
    }

    /**
     * Checks whether a seat is currently available for booking.
     */
    public function isAvailable(Showtime $showtime, RoomLayout $layout, int $seatId): bool
    {
        return $this->statusFor($showtime, $layout, $seatId) === 'available';
    }

    /**
     * Returns all currently free seat IDs in the layout.
     *
     * @return list<int>
     */
    public function availableSeatIds(Showtime $showtime, RoomLayout $layout): array
    {
        return $this->summary($showtime, $layout)['available'];
    }

    /** @return array<int, bool> */
    private function toIdSet(array $seatIds): array
    {
        $set = [];
        foreach ($seatIds as $seatId) {
            $converted = (int) $seatId;
            if ($converted > 0) {
                $set[$converted] = true;
            }
        }

        return $set;
    }
}
