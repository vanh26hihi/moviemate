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
            && in_array($booking->booking_status, ['paid', 'used'], true)
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
                ->where('status', Payment::STATUS_SUCCESS)
                ->filter(fn (Payment $payment): bool => $payment->verified_at !== null)
                ->sortByDesc('id')
                ->first();
        }

        $query = $booking->payments()
            ->where('status', Payment::STATUS_SUCCESS)
            ->whereNotNull('verified_at')
            ->latest('id');

        return $query->first();
    }
}
