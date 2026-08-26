<?php

namespace App\Services\Seats;

use App\Models\SeatHold;
use App\Models\Showtime;
use Carbon\Carbon;
use Carbon\CarbonInterface;

final class SeatHoldExpirationPolicy
{
    public const DEFAULT_WARNING_MINUTES = 2;

    public function hoursRemaining(SeatHold $hold): float
    {
        if (! $hold->expires_at) {
            return 0.0;
        }

        return max(0.0, (float) now()->diffInSeconds($hold->expires_at, false) / 3600);
    }

    public function minutesRemaining(SeatHold $hold): int
    {
        if (! $hold->expires_at) {
            return 0;
        }

        return max(0, (int) now()->diffInMinutes($hold->expires_at, false));
    }

    public function shouldExpire(SeatHold $hold): bool
    {
        return ! $hold->expires_at || $hold->expires_at->lte(now());
    }

    public function shouldWarn(SeatHold $hold, int $leadMinutes = self::DEFAULT_WARNING_MINUTES): bool
    {
        if (! $hold->expires_at) {
            return false;
        }

        return $hold->expires_at->lte(now()->addMinutes($leadMinutes));
    }

    public function holdDeadline(Showtime $showtime, int $windowMinutes = 7): CarbonInterface
    {
        $startsAt = Carbon::parse($showtime->show_date->format('Y-m-d').' '.$showtime->show_time, 'Asia/Ho_Chi_Minh');
        $bookingDeadline = $startsAt->addMinutes(30);
        $holdExpiry = now()->addMinutes($windowMinutes);

        return $holdExpiry->greaterThan($bookingDeadline) ? $bookingDeadline : $holdExpiry;
    }

    public function unexpectedSeatIds(iterable $holds): array
    {
        $seatIds = [];

        foreach ($holds as $hold) {
            if (! $hold instanceof SeatHold) {
                continue;
            }

            if ($this->shouldExpire($hold)) {
                $seatIds[] = (int) $hold->seat_id;
            }
        }

        return $seatIds;
    }
}
