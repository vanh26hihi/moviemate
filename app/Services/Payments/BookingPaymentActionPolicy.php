<?php

namespace App\Services\Payments;

use App\Models\Booking;
use App\Models\Payment;

final class BookingPaymentActionPolicy
{
    /**
     * @return array{can_resume:bool,can_cancel_local:bool,can_cancel_payos:bool,badge_label:string,badge_class:string}
     */
    public function evaluate(Booking $booking): array
    {
        $payments = $booking->relationLoaded('payments')
            ? $booking->payments
            : $booking->payments()->get();
        $activeAttempts = $payments
            ->filter(fn (Payment $payment): bool => in_array($payment->status, Payment::UNSAFE_RETRY_STATUSES, true))
            ->sortByDesc('id')
            ->values();
        $active = $activeAttempts->first();
        $hasAuthoritativeSuccess = $payments->contains(
            fn (Payment $payment): bool => $payment->hasAuthoritativeSuccessEvidence(),
        );
        $bookingIsPending = $booking->booking_status === 'pending_payment'
            && $booking->payment_status === 'unpaid'
            && $booking->expires_at?->isFuture() === true
            && ! $hasAuthoritativeSuccess;
        $hasSingleActiveAttempt = $activeAttempts->count() <= 1;
        $attemptIsPending = $active?->status === Payment::STATUS_PENDING
            && $active->expires_at?->isFuture() === true;
        $canResume = $bookingIsPending
            && $hasSingleActiveAttempt
            && $attemptIsPending
            && in_array($active?->provider, Payment::SUPPORTED_PROVIDERS, true);
        $canCancelLocal = $bookingIsPending
            && $hasSingleActiveAttempt
            && ($active === null
                || ($attemptIsPending && in_array($active->provider, ['vnpay', 'zalopay'], true)));
        $canCancelPayOs = $bookingIsPending
            && $hasSingleActiveAttempt
            && $attemptIsPending
            && $active?->provider === 'payos';

        [$badgeLabel, $badgeClass] = $this->badge($booking, $active);

        return [
            'can_resume' => $canResume,
            'can_cancel_local' => $canCancelLocal,
            'can_cancel_payos' => $canCancelPayOs,
            'badge_label' => $badgeLabel,
            'badge_class' => $badgeClass,
        ];
    }

    /** @return array{string,string} */
    private function badge(Booking $booking, ?Payment $active): array
    {
        if ($booking->booking_status !== 'pending_payment') {
            if ($booking->booking_status === 'cancelled'
                && ($booking->relationLoaded('showtimeCancellationImpact')
                    ? $booking->showtimeCancellationImpact !== null
                    : $booking->showtimeCancellationImpact()->exists())) {
                return ['Suất chiếu bị rạp hủy', 'bg-red-100 text-red-700'];
            }

            return [$booking->status_label, match ($booking->booking_status) {
                'paid' => 'bg-brand-start text-white',
                'used' => 'bg-blue-100 text-blue-700',
                'cancelled' => 'bg-red-100 text-red-700',
                default => 'bg-gray-100 text-gray-700',
            }];
        }

        return match ($active?->status) {
            Payment::STATUS_PROCESSING, Payment::STATUS_UNRESOLVED => [
                'Đang xác minh',
                'bg-yellow-100 text-yellow-700',
            ],
            Payment::STATUS_REVIEW => ['Cần hỗ trợ', 'bg-yellow-100 text-yellow-700'],
            default => ['Chờ thanh toán', 'bg-yellow-100 text-yellow-700'],
        };
    }
}
