<?php

namespace App\Services;

use App\Models\Booking;

final class BookingActionPolicy
{
    public function canPay(Booking $booking): bool
    {
        return $booking->booking_status === 'pending_payment'
            && $booking->payment_status === 'unpaid'
            && ($booking->expires_at === null || $booking->expires_at->isFuture());
    }

    public function canCancel(Booking $booking): bool
    {
        return in_array($booking->booking_status, ['pending_payment', 'paid'], true)
            || in_array($booking->payment_status, ['unpaid', 'paid'], true);
    }

    public function canExpire(Booking $booking): bool
    {
        return $booking->booking_status === 'pending_payment'
            && $booking->payment_status === 'unpaid'
            && $booking->expires_at !== null
            && $booking->expires_at->isPast();
    }

    public function canDeliver(Booking $booking): bool
    {
        return $booking->booking_status === 'paid' && $booking->payment_status === 'paid';
    }

    public function canRefund(Booking $booking): bool
    {
        return $booking->booking_status === 'paid' && $booking->payment_status === 'paid';
    }

    public function recommendedAction(Booking $booking): string
    {
        if ($this->canPay($booking)) {
            return 'pay';
        }

        if ($this->canExpire($booking)) {
            return 'expire';
        }

        if ($this->canDeliver($booking)) {
            return 'deliver';
        }

        if ($this->canCancel($booking)) {
            return 'cancel';
        }

        if ($this->canRefund($booking)) {
            return 'refund';
        }

        return 'none';
    }

    /**
     * @return array<string, bool>
     */
    public function reasons(Booking $booking): array
    {
        return [
            'can_pay' => $this->canPay($booking),
            'can_cancel' => $this->canCancel($booking),
            'can_expire' => $this->canExpire($booking),
            'can_deliver' => $this->canDeliver($booking),
            'can_refund' => $this->canRefund($booking),
        ];
    }
}
