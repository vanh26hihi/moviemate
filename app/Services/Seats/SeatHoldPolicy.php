<?php

namespace App\Services\Seats;

use App\Models\SeatHold;
use App\Models\Showtime;
use App\Models\User;
use Carbon\CarbonInterface;

final class SeatHoldPolicy
{
    public const DEFAULT_WARNING_MINUTES = 2;

    /**
     * Normalizes the incoming selection and guards against empty or invalid seat IDs.
     *
     * @return list<int>
     */
    public function normalizeSeatIds(iterable $seatIds): array
    {
        $normalized = collect($seatIds)
            ->map(fn ($seatId): int => (int) $seatId)
            ->filter(fn (int $seatId): bool => $seatId > 0)
            ->unique()
            ->values()
            ->all();

        return $normalized;
    }

    /**
     * Returns the seats currently held by the user for the showtime.
     *
     * @return list<int>
     */
    public function ownedSeatIds(User $user, Showtime $showtime): array
    {
        return SeatHold::query()
            ->where('user_id', $user->id)
            ->where('showtime_id', $showtime->id)
            ->where('expires_at', '>', now())
            ->orderBy('seat_id')
            ->pluck('seat_id')
            ->map(fn ($seatId): int => (int) $seatId)
            ->values()
            ->all();
    }

    /**
     * Returns the summary of a user's active hold for a showtime.
     *
     * @return array{count:int, seat_ids:list<int>, expires_at:?CarbonInterface, is_active:bool}
     */
    public function summary(User $user, Showtime $showtime): array
    {
        $holds = SeatHold::query()
            ->where('user_id', $user->id)
            ->where('showtime_id', $showtime->id)
            ->where('expires_at', '>', now())
            ->orderBy('expires_at')
            ->get();

        $seatIds = $holds->pluck('seat_id')->map(fn ($seatId): int => (int) $seatId)->values()->all();

        return [
            'count' => count($seatIds),
            'seat_ids' => $seatIds,
            'expires_at' => $holds->isNotEmpty() ? $holds->min('expires_at') : null,
            'is_active' => $holds->isNotEmpty(),
        ];
    }

    /**
     * Evaluates a proposed selection before issuing a new hold.
     *
     * @return array{
     *   valid: bool,
     *   selected: list<int>,
     *   conflicts: list<int>,
     *   owned: list<int>,
     *   message: ?string,
     *   expires_in_minutes: ?int,
     * }
     */
    public function evaluateSelection(User $user, Showtime $showtime, iterable $seatIds): array
    {
        $selected = $this->normalizeSeatIds($seatIds);
        $owned = $this->ownedSeatIds($user, $showtime);

        $conflicts = SeatHold::query()
            ->where('showtime_id', $showtime->id)
            ->whereIn('seat_id', $selected)
            ->where('user_id', '!=', $user->id)
            ->where('expires_at', '>', now())
            ->pluck('seat_id')
            ->map(fn ($seatId): int => (int) $seatId)
            ->unique()
            ->values()
            ->all();

        $expiresAt = SeatHold::query()
            ->where('user_id', $user->id)
            ->where('showtime_id', $showtime->id)
            ->whereIn('seat_id', $selected)
            ->where('expires_at', '>', now())
            ->min('expires_at');

        return [
            'valid' => $conflicts === [],
            'selected' => $selected,
            'conflicts' => $conflicts,
            'owned' => $owned,
            'message' => $conflicts === [] ? null : 'Một hoặc nhiều ghế đang được người khác giữ. Vui lòng chọn lại.',
            'expires_in_minutes' => $expiresAt ? max(0, (int) now()->diffInMinutes($expiresAt, false)) : null,
        ];
    }

    /**
     * Detects seats that are close to expiration so the UI can show a short warning.
     *
     * @return list<array{seat_id:int, expires_at:CarbonInterface}>
     */
    public function seatsAboutToExpire(User $user, Showtime $showtime, int $minutesBeforeExpiry = self::DEFAULT_WARNING_MINUTES): array
    {
        $threshold = now()->addMinutes($minutesBeforeExpiry);

        return SeatHold::query()
            ->where('user_id', $user->id)
            ->where('showtime_id', $showtime->id)
            ->where('expires_at', '>', now())
            ->where('expires_at', '<=', $threshold)
            ->orderBy('expires_at')
            ->get()
            ->map(fn (SeatHold $hold): array => [
                'seat_id' => (int) $hold->seat_id,
                'expires_at' => $hold->expires_at,
            ])
            ->values()
            ->all();
    }

    /**
     * Counts how many active holds are still available for this user and showtime.
     */
    public function activeHoldCount(User $user, Showtime $showtime): int
    {
        return SeatHold::query()
            ->where('user_id', $user->id)
            ->where('showtime_id', $showtime->id)
            ->where('expires_at', '>', now())
            ->count();
    }

    /**
     * Returns whether the requested seat set can be re-held without violating a hold conflict.
     */
    public function canRehold(User $user, Showtime $showtime, iterable $seatIds): bool
    {
        $selected = $this->normalizeSeatIds($seatIds);

        if ($selected === []) {
            return false;
        }

        return SeatHold::query()
            ->where('showtime_id', $showtime->id)
            ->whereIn('seat_id', $selected)
            ->where('user_id', '!=', $user->id)
            ->where('expires_at', '>', now())
            ->doesntExist();
    }
}
