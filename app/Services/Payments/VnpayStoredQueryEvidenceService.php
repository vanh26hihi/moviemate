<?php

namespace App\Services\Payments;

use App\Models\ActivityLog;
use App\Models\Booking;
use App\Models\Payment;
use App\Services\ActivityLogger;
use App\Services\BookingCancellationService;
use App\Services\BookingExpirationService;
use Illuminate\Support\Facades\DB;
use LogicException;

final class VnpayStoredQueryEvidenceService
{
    private const TERMINAL_REASON = 'vnpay_terminal_expired';

    public function __construct(
        private readonly ActivityLogger $activities,
        private readonly BookingCancellationService $cancellations,
        private readonly BookingExpirationService $expiration,
    ) {}

    public function reconcileTerminalExpiration(Payment $payment): string
    {
        DB::transaction(function () use ($payment): void {
            $booking = Booking::query()->lockForUpdate()->findOrFail($payment->booking_id);
            $payments = Payment::query()
                ->where('booking_id', $booking->id)
                ->orderBy('id')
                ->lockForUpdate()
                ->get();
            $locked = $payments->firstWhere('id', $payment->id);

            if (! $locked
                || $locked->provider !== 'vnpay'
                || $booking->payment_status !== 'unpaid'
                || $payments->contains(fn (Payment $candidate): bool => $candidate->hasAuthoritativeSuccessEvidence())) {
                throw new LogicException('Payment is not eligible for stored VNPAY evidence reconciliation.');
            }

            $alreadyApplied = $locked->status === Payment::STATUS_FAILED
                && $locked->failure_reason === self::TERMINAL_REASON;
            $isReview = $locked->status === Payment::STATUS_REVIEW
                && in_array($locked->failure_reason, ['query_unknown_status', self::TERMINAL_REASON], true);

            if (! $alreadyApplied && (! $isReview || $booking->booking_status !== 'pending_payment')) {
                throw new LogicException('Payment is not in the guarded review state.');
            }

            $this->assertStoredEvidence($locked);

            if ($alreadyApplied) {
                return;
            }

            $locked->forceFill([
                'status' => Payment::STATUS_FAILED,
                'failure_reason' => self::TERMINAL_REASON,
                'failed_at' => now(),
            ])->save();

            $this->activities->log(
                'payment.vnpay_stored_evidence_applied',
                $locked,
                ['payment_status' => Payment::STATUS_REVIEW],
                ['payment_status' => Payment::STATUS_FAILED],
                [
                    'payment_id' => $locked->id,
                    'booking_id' => $booking->id,
                    'provider' => 'vnpay',
                    'result' => 'terminal_expired',
                    'reason' => self::TERMINAL_REASON,
                ],
            );
        });

        $fresh = $payment->fresh();
        if ($fresh->status === Payment::STATUS_FAILED
            && $fresh->booking()->value('sales_channel') !== Booking::SALES_CHANNEL_COUNTER) {
            $this->cancellations->cancel(
                $fresh->booking_id,
                self::TERMINAL_REASON,
                'booking.payment_cancelled',
            );
            $this->expiration->expire($fresh->booking_id);
        }

        return $payment->fresh()->status;
    }

    private function assertStoredEvidence(Payment $payment): void
    {
        if ($payment->response_code !== '00'
            || $payment->transaction_status !== '08'
            || ! is_string($payment->query_response_hash)
            || preg_match('/^[a-f0-9]{64}$/Di', $payment->query_response_hash) !== 1) {
            throw new LogicException('Stored payment outcome is incomplete or not terminal expiration.');
        }

        $event = ActivityLog::query()
            ->where('action', 'payment.vnpay_query_attempted')
            ->where('subject_type', $payment->getMorphClass())
            ->where('subject_id', (string) $payment->id)
            ->latest('id')
            ->lockForUpdate()
            ->first();
        $context = $event?->context;

        if (! is_array($context)
            || ($context['payment_id'] ?? null) !== $payment->id
            || ($context['booking_id'] ?? null) !== $payment->booking_id
            || ($context['provider'] ?? null) !== 'vnpay'
            || ! is_string($context['txn_ref'] ?? null)
            || ! hash_equals($payment->order_code, $context['txn_ref'])
            || ($context['http_status'] ?? null) !== 200
            || ($context['provider_response_code'] ?? null) !== '00'
            || ($context['provider_transaction_status'] ?? null) !== '08'
            || ($context['checksum_verification'] ?? null) !== 'match'
            || ($context['response_has_checksum'] ?? null) !== true
            || ($context['error_category'] ?? null) !== 'provider_query_success') {
            throw new LogicException('Authenticated VNPAY QueryDr evidence was not found.');
        }
    }
}
