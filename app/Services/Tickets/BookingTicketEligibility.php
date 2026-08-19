<?php

namespace App\Services\Tickets;

use App\Models\Booking;
use App\Models\Payment;
use Illuminate\Database\Eloquent\Builder;

final class BookingTicketEligibility
{
    public function isUsable(Booking $booking): bool
    {
        return $booking->payment_status === 'paid'
            && $booking->booking_status === 'paid'
            && ! $this->isCinemaCancelled($booking)
            && $this->verifiedPayment($booking) !== null;
    }

    public function isDeliverable(Booking $booking): bool
    {
        return $booking->payment_status === 'paid'
            && $booking->booking_status === 'paid'
            && ! $this->isCinemaCancelled($booking)
            && $this->verifiedPayment($booking) !== null;
    }

    public function isPrintable(Booking $booking): bool
    {
        return $this->isDeliverable($booking);
    }

    public function applyPrintableBookingFilter(Builder $query): Builder
    {
        return $query
            ->where('bookings.payment_status', 'paid')
            ->where('bookings.booking_status', 'paid')
            ->whereDoesntHave('showtimeCancellationImpact')
            ->whereHas('showtime', fn (Builder $showtime): Builder => $showtime->where('status', 'active'))
            ->whereHas('payments', fn (Builder $payments): Builder => $this->applyAuthoritativePaymentEvidence($payments));
    }

    public function verifiedPayment(Booking $booking): ?Payment
    {
        if ($booking->relationLoaded('payments')) {
            return $booking->payments
                ->filter(fn (Payment $payment): bool => $payment->hasAuthoritativeSuccessEvidence())
                ->sortByDesc('id')
                ->first();
        }

        $query = $this->applyAuthoritativePaymentEvidence($booking->payments()->getQuery())
            ->latest('id');

        return $query->first();
    }

    private function applyAuthoritativePaymentEvidence(Builder $query): Builder
    {
        return $query
            ->where('status', Payment::STATUS_SUCCESS)
            ->where(function ($query): void {
                $query->whereNotNull('verified_at')
                    ->orWhere(function ($counter): void {
                        $counter->where('provider', Payment::PROVIDER_COUNTER_CASH)
                            ->whereNotNull('settled_at')
                            ->whereNotNull('settled_by_user_id');
                    });
            });
    }

    private function isCinemaCancelled(Booking $booking): bool
    {
        $showtimeCancelled = $booking->relationLoaded('showtime')
            ? $booking->showtime?->status === 'cancelled'
            : $booking->showtime()->where('status', 'cancelled')->exists();
        $hasImpact = $booking->relationLoaded('showtimeCancellationImpact')
            ? $booking->showtimeCancellationImpact !== null
            : $booking->showtimeCancellationImpact()->exists();

        return $showtimeCancelled || $hasImpact;
    }
}
