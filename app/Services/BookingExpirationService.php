<?php

namespace App\Services;

use App\Models\Booking;
use Illuminate\Support\Facades\DB;

class BookingExpirationService
{
    public function __construct(private readonly BookingSeatLockService $seatLocks) {}

    public function expire(int $bookingId): bool
    {
        return DB::transaction(function () use ($bookingId): bool {
            $booking = Booking::query()->lockForUpdate()->find($bookingId);

            if (! $booking
                || $booking->booking_status !== 'pending_payment'
                || ! $booking->expires_at
                || $booking->expires_at->isFuture()) {
                return false;
            }

            $booking->update(['booking_status' => 'expired']);
            $this->seatLocks->release($booking);

            return true;
        });
    }
}
