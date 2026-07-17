<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\SeatHold;
use App\Models\Showtime;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SeatHoldService
{
    public const HOLD_MINUTES = 7;

    public function expireStale(?int $showtimeId = null): int
    {
        SeatHold::where('expires_at', '<=', now())->delete();
        $ids = Booking::query()
            ->where('booking_status', 'pending')
            ->where('payment_status', 'pending')
            ->where(fn ($query) => $query->whereNull('hold_expires_at')->orWhere('hold_expires_at', '<=', now()))
            ->when($showtimeId, fn ($query) => $query->where('showtime_id', $showtimeId))
            ->pluck('id');

        $expired = 0;
        foreach ($ids as $id) {
            DB::transaction(function () use ($id, &$expired) {
                $booking = Booking::with(['payment', 'foodOrder'])->lockForUpdate()->find($id);
                if (! $booking || $booking->booking_status !== 'pending' || $booking->payment_status !== 'pending'
                    || ($booking->hold_expires_at && $booking->hold_expires_at->isFuture())) {
                    return;
                }

                $booking->update(['booking_status' => 'expired', 'payment_status' => 'failed']);
                $booking->payment?->update(['status' => 'failed']);
                $booking->foodOrder?->update(['status' => 'cancelled']);
                app(LoyaltyPointService::class)->restoreRedeemedPoints($booking);
                $booking->bookingSeats()->delete();
                $expired++;
            });
        }

        return $expired;
    }

    public function holdSeats(User $user, Showtime $showtime, array $seatIds): \Carbon\CarbonInterface
    {
        return DB::transaction(function () use ($user, $showtime, $seatIds) {
            SeatHold::where('expires_at', '<=', now())->delete();

            $conflict = SeatHold::where('showtime_id', $showtime->id)
                ->whereIn('seat_id', $seatIds)
                ->where('user_id', '!=', $user->id)
                ->where('expires_at', '>', now())
                ->lockForUpdate()->exists();

            if ($conflict) {
                throw ValidationException::withMessages(['selected_seats' => 'Một hoặc nhiều ghế vừa được người khác giữ. Vui lòng chọn lại.']);
            }

            $existingHolds = SeatHold::where('user_id', $user->id)->where('showtime_id', $showtime->id)
                ->whereIn('seat_id', $seatIds)->where('expires_at', '>', now())->lockForUpdate()->get();

            if ($existingHolds->count() === count($seatIds)) {
                $expiresAt = $existingHolds->min('expires_at');
            } else {
                $expiresAt = now()->addMinutes(self::HOLD_MINUTES);
                $startsAt = \Carbon\Carbon::parse($showtime->show_date->format('Y-m-d').' '.$showtime->show_time, 'Asia/Ho_Chi_Minh');
                $bookingDeadline = $startsAt->addMinutes(30);
                if ($expiresAt->greaterThan($bookingDeadline)) $expiresAt = $bookingDeadline;
            }

            SeatHold::where('user_id', $user->id)->where('showtime_id', $showtime->id)->whereNotIn('seat_id', $seatIds)->delete();
            foreach ($seatIds as $seatId) {
                SeatHold::updateOrCreate(
                    ['showtime_id' => $showtime->id, 'seat_id' => $seatId],
                    ['user_id' => $user->id, 'expires_at' => $expiresAt]
                );
            }

            return $expiresAt;
        });
    }

    public function assertHeldBy(User $user, Showtime $showtime, array $seatIds): \Carbon\CarbonInterface
    {
        $holds = SeatHold::where('user_id', $user->id)->where('showtime_id', $showtime->id)
            ->whereIn('seat_id', $seatIds)->where('expires_at', '>', now())->lockForUpdate()->get();

        if ($holds->count() !== count($seatIds)) {
            throw ValidationException::withMessages(['seat_ids' => 'Thời gian giữ ghế đã hết hoặc ghế không còn được giữ cho bạn. Vui lòng chọn lại.']);
        }

        return $holds->min('expires_at');
    }

    public function release(User $user, Showtime $showtime, array $seatIds): void
    {
        SeatHold::where('user_id', $user->id)->where('showtime_id', $showtime->id)->whereIn('seat_id', $seatIds)->delete();
    }

    public function activeHeldSeatIds(Showtime $showtime, ?int $exceptUserId = null): array
    {
        return SeatHold::where('showtime_id', $showtime->id)->where('expires_at', '>', now())
            ->when($exceptUserId, fn ($query) => $query->where('user_id', '!=', $exceptUserId))
            ->pluck('seat_id')->all();
    }
}
