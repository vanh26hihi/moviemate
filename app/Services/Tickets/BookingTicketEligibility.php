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

    public function verifiedPayment(Booking $booking): ?Payment
    {
        $query = $booking->payments()
            ->where('status', Payment::STATUS_SUCCESS)
            ->latest('id');

        return $query->first();
    }
}
