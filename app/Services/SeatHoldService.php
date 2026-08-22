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
            ->where('booking_status', 'pending_payment')
            ->where('payment_status', 'unpaid')
            ->when($showtimeId, fn ($query) => $query->where('showtime_id', $showtimeId))
            ->pluck('id');

        $expired = 0;
        foreach ($ids as $id) {
            DB::transaction(function () use ($id, &$expired) {
                $booking = Booking::with(['payment', 'foodOrder'])->lockForUpdate()->find($id);
                if (! $booking || $booking->booking_status !== 'pending_payment' || $booking->payment_status !== 'unpaid') {
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
        $normalizedSeatIds = $this->normalizeSeatIds($seatIds);

        return DB::transaction(function () use ($user, $showtime, $normalizedSeatIds) {
            SeatHold::where('expires_at', '<=', now())->delete();

            $conflict = SeatHold::where('showtime_id', $showtime->id)
                ->whereIn('seat_id', $normalizedSeatIds)
                ->where('user_id', '!=', $user->id)
                ->where('expires_at', '>', now())
                ->lockForUpdate()->exists();

            if ($conflict) {
                throw ValidationException::withMessages(['selected_seats' => 'Một hoặc nhiều ghế vừa được người khác giữ. Vui lòng chọn lại.']);
            }

            $existingHolds = SeatHold::where('user_id', $user->id)
                ->where('showtime_id', $showtime->id)
                ->whereIn('seat_id', $normalizedSeatIds)
                ->where('expires_at', '>', now())
                ->lockForUpdate()->get();

            $seatIdsAlreadyHeld = $existingHolds->pluck('seat_id')->map(fn ($seatId) => (int) $seatId)->all();
            $expiresAt = $this->calculateExpiration($showtime, $existingHolds, $normalizedSeatIds, $seatIdsAlreadyHeld);

            SeatHold::where('user_id', $user->id)
                ->where('showtime_id', $showtime->id)
                ->whereNotIn('seat_id', $normalizedSeatIds)
                ->delete();

            foreach ($normalizedSeatIds as $seatId) {
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
        $normalizedSeatIds = $this->normalizeSeatIds($seatIds);
        $holds = SeatHold::where('user_id', $user->id)->where('showtime_id', $showtime->id)
            ->whereIn('seat_id', $normalizedSeatIds)->where('expires_at', '>', now())->lockForUpdate()->get();

        if ($holds->count() !== count($normalizedSeatIds)) {
            throw ValidationException::withMessages(['seat_ids' => 'Thời gian giữ ghế đã hết hoặc ghế không còn được giữ cho bạn. Vui lòng chọn lại.']);
        }

        return $holds->min('expires_at');
    }

    public function release(User $user, Showtime $showtime, array $seatIds): void
    {
        $normalizedSeatIds = $this->normalizeSeatIds($seatIds);

        if ($normalizedSeatIds === []) {
            return;
        }

        SeatHold::where('user_id', $user->id)
            ->where('showtime_id', $showtime->id)
            ->whereIn('seat_id', $normalizedSeatIds)
            ->delete();
    }

    public function activeHeldSeatIds(Showtime $showtime, ?int $exceptUserId = null): array
    {
        return SeatHold::where('showtime_id', $showtime->id)->where('expires_at', '>', now())
            ->when($exceptUserId, fn ($query) => $query->where('user_id', '!=', $exceptUserId))
            ->pluck('seat_id')->map(fn ($seatId) => (int) $seatId)->values()->all();
    }

    public function getSeatHoldSummary(User $user, Showtime $showtime): array
    {
        $holds = SeatHold::query()
            ->where('user_id', $user->id)
            ->where('showtime_id', $showtime->id)
            ->where('expires_at', '>', now())
            ->orderBy('seat_id')
            ->get();

        $seatIds = $holds->pluck('seat_id')->map(fn ($seatId) => (int) $seatId)->values()->all();

        return [
            'count' => count($seatIds),
            'seat_ids' => $seatIds,
            'expires_at' => $holds->isNotEmpty() ? $holds->min('expires_at') : null,
            'is_active' => $holds->isNotEmpty(),
        ];
    }

    protected function normalizeSeatIds(array $seatIds): array
    {
        $normalized = collect($seatIds)
            ->map(fn ($seatId) => (int) $seatId)
            ->filter(fn (int $seatId) => $seatId > 0)
            ->unique()
            ->values()
            ->all();

        if ($normalized === []) {
            throw ValidationException::withMessages(['selected_seats' => 'Vui lòng chọn ít nhất một ghế để giữ.']);
        }

        return $normalized;
    }

    protected function calculateExpiration(Showtime $showtime, $existingHolds, array $requestedSeatIds, array $heldSeatIds): \Carbon\CarbonInterface
    {
        if ($existingHolds->count() === count($requestedSeatIds) && $heldSeatIds === $requestedSeatIds) {
            return $existingHolds->min('expires_at');
        }

        $expiresAt = now()->addMinutes(self::HOLD_MINUTES);
        $startsAt = \Carbon\Carbon::parse($showtime->show_date->format('Y-m-d').' '.$showtime->show_time, 'Asia/Ho_Chi_Minh');
        $bookingDeadline = $startsAt->addMinutes(30);

        if ($expiresAt->greaterThan($bookingDeadline)) {
            return $bookingDeadline;
        }

        return $expiresAt;
    }
}
