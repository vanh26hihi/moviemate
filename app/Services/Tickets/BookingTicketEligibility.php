<?php

namespace App\Services\Tickets;

use App\Models\Booking;
use App\Models\Payment;

final class BookingTicketEligibility
{
    public function isUsable(Booking $booking): bool
    {
        return $booking->payment_status === 'paid'
            && $booking->booking_status === 'paid'
            && $this->verifiedPayment($booking) !== null;
    }

    public function isDeliverable(Booking $booking): bool
    {
        return $booking->payment_status === 'paid'
            && $booking->booking_status === 'paid'
            && $this->verifiedPayment($booking) !== null;
    }

    public function isPrintable(Booking $booking): bool
    {
        return $this->isDeliverable($booking);
    }

    public function verifiedPayment(Booking $booking): ?Payment
    {
        if ($booking->relationLoaded('payments')) {
            return $booking->payments
                ->filter(fn (Payment $payment): bool => $payment->hasAuthoritativeSuccessEvidence())
                ->sortByDesc('id')
                ->first();
        }

        $query = $booking->payments()
            ->where('status', Payment::STATUS_SUCCESS)
            ->where(function ($query): void {
                $query->whereNotNull('verified_at')
                    ->orWhere(function ($counter): void {
                        $counter->where('provider', Payment::PROVIDER_COUNTER_CASH)
                            ->whereNotNull('settled_at')
                            ->whereNotNull('settled_by_user_id');
                    });
            })
            ->latest('id');

        return $query->first();
    }
}
