<?php

namespace App\Services\Seats;

use App\Models\Booking;
use App\Models\Payment;
use App\Models\SeatIncidentImpact;
use Illuminate\Support\Collection;

final class SeatIncidentImpactClassifier
{
    /** @param Collection<int, Payment>|null $payments */
    public function classify(Booking $booking, ?Collection $payments = null): string
    {
        $payments ??= $booking->relationLoaded('payments')
            ? $booking->payments
            : $booking->payments()->get();

        if ($this->isAuthoritativelyPaid($booking, $payments)) {
            return SeatIncidentImpact::PAID;
        }

        if ($payments->contains(fn (Payment $payment): bool => in_array($payment->status, [
            Payment::STATUS_PROCESSING,
            Payment::STATUS_UNRESOLVED,
            Payment::STATUS_REVIEW,
        ], true))) {
            return SeatIncidentImpact::RETAINED_PAYMENT;
        }

        if ($booking->booking_status === 'pending_payment' && $booking->payment_status === 'unpaid') {
            return SeatIncidentImpact::ORDINARY_HOLD;
        }

        // Any inconsistent active ownership is preserved for manual financial review.
        return $booking->booking_status === 'cancelled' || $booking->booking_status === 'expired'
            ? SeatIncidentImpact::RELEASED
            : SeatIncidentImpact::RETAINED_PAYMENT;
    }

    /** @param Collection<int, Payment> $payments */
    private function isAuthoritativelyPaid(Booking $booking, Collection $payments): bool
    {
        return $booking->booking_status === 'paid'
            && $booking->payment_status === 'paid'
            && $payments->contains(fn (Payment $payment): bool => $payment->hasAuthoritativeSuccessEvidence());
    }
}
