<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\BookingSeat;
use App\Models\Payment;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class BookingExpirationService
{
    private const RETAINED_PAYMENT_STATUSES = [
        Payment::STATUS_PROCESSING,
        Payment::STATUS_UNRESOLVED,
        Payment::STATUS_REVIEW,
    ];

    public function __construct(
        private readonly BookingSeatLockService $seatLocks,
        private readonly BookingFoodService $food,
    ) {}

    public function expire(int $bookingId): bool
    {
        return DB::transaction(function () use ($bookingId): bool {
            $booking = Booking::query()->lockForUpdate()->find($bookingId);

            if (! $booking
                || $booking->booking_status !== 'pending_payment'
                || $booking->payment_status === 'paid'
                || ! $booking->expires_at
                || $booking->expires_at->isFuture()) {
                return false;
            }

            $payments = Payment::query()
                ->where('booking_id', $booking->id)
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

            if ($payments->contains(fn (Payment $payment): bool => $payment->hasAuthoritativeSuccessEvidence())
                || $payments->contains(
                    fn (Payment $payment): bool => in_array(
                        $payment->status,
                        self::RETAINED_PAYMENT_STATUSES,
                        true,
                    ),
                )) {
                return false;
            }

            BookingSeat::query()
                ->where('booking_id', $booking->id)
                ->where('active_lock_key', BookingSeat::ACTIVE_LOCK_KEY)
                ->orderBy('id')
                ->lockForUpdate()
                ->get(['id']);

            $booking->forceFill(['booking_status' => 'expired'])->save();
            $this->seatLocks->release($booking);
            $this->food->transitionForBooking($booking, 'expired');

            return true;
        });
    }

    public function expireStaleForShowtime(int $showtimeId, ?int $limit = null): int
    {
        return $this->expireCandidates(
            Booking::query()
                ->where('showtime_id', $showtimeId)
                ->whereHas('bookingSeats', fn (Builder $query) => $query
                    ->where('showtime_id', $showtimeId)
                    ->where('active_lock_key', BookingSeat::ACTIVE_LOCK_KEY)),
            $limit,
        );
    }

    public function expireStaleForSeats(int $showtimeId, iterable $seatIds): int
    {
        $normalizedSeatIds = collect($seatIds)
            ->map(fn ($seatId): int => (int) $seatId)
            ->filter(fn (int $seatId): bool => $seatId > 0)
            ->unique()
            ->values();

        if ($normalizedSeatIds->isEmpty()) {
            return 0;
        }

        return $this->expireCandidates(
            Booking::query()
                ->where('showtime_id', $showtimeId)
                ->whereHas('bookingSeats', fn (Builder $query) => $query
                    ->where('showtime_id', $showtimeId)
                    ->whereIn('seat_id', $normalizedSeatIds)
                    ->where('active_lock_key', BookingSeat::ACTIVE_LOCK_KEY)),
            $normalizedSeatIds->count(),
        );
    }

    public function expireStaleForUser(int $userId, ?int $limit = null): int
    {
        return $this->expireCandidates(
            Booking::query()->where('user_id', $userId),
            $limit,
        );
    }

    private function expireCandidates(Builder $query, ?int $limit): int
    {
        $batchSize = min(1000, max(
            1,
            $limit ?? (int) config('booking.expiration_batch_size', 100),
        ));
        $bookingIds = $query
            ->where('booking_status', 'pending_payment')
            ->whereNotNull('expires_at')
            ->where('expires_at', '<=', now())
            ->orderBy('id')
            ->limit($batchSize)
            ->pluck('id');

        return $bookingIds->reduce(
            fn (int $expired, int $bookingId): int => $expired + (int) $this->expire($bookingId),
            0,
        );
    }
}
