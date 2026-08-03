<?php

namespace App\Services\Payments;

use App\Domain\Payments\PaymentVerificationResult;
use App\Domain\Payments\VerifiedPaymentData;
use App\Jobs\Payments\SendBookingTicket;
use App\Models\Booking;
use App\Models\Payment;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class VerifiedPaymentService
{
    public function verify(Payment $payment, VerifiedPaymentData $data): PaymentVerificationResult
    {
        return DB::transaction(function () use ($payment, $data): PaymentVerificationResult {
            $lockedPayment = Payment::query()->lockForUpdate()->findOrFail($payment->getKey());
            $booking = Booking::query()->lockForUpdate()->findOrFail($lockedPayment->booking_id);

            if ($lockedPayment->provider !== 'zalopay'
                || $lockedPayment->app_id !== $data->appId
                || $lockedPayment->app_trans_id !== $data->appTransId) {
                return PaymentVerificationResult::rejected('Invalid payment identity.');
            }

            if ($lockedPayment->amount !== $data->amount) {
                $this->markReview($lockedPayment, $data, 'amount_mismatch');

                return PaymentVerificationResult::rejected('Payment amount mismatch.');
            }

            if ($data->zpTransId !== null) {
                $duplicateTransaction = Payment::query()
                    ->where('provider', 'zalopay')
                    ->where('zp_trans_id', $data->zpTransId)
                    ->whereKeyNot($lockedPayment->getKey())
                    ->lockForUpdate()
                    ->exists();

                if ($duplicateTransaction) {
                    $this->markReview($lockedPayment, $data, 'duplicate_zp_trans_id', false);

                    return PaymentVerificationResult::rejected('ZaloPay transaction belongs to another attempt.');
                }
            }

            if ($lockedPayment->status === Payment::STATUS_SUCCESS) {
                if ($lockedPayment->zp_trans_id !== null
                    && $data->zpTransId !== null
                    && $lockedPayment->zp_trans_id !== $data->zpTransId) {
                    return PaymentVerificationResult::rejected('Verified transaction identity mismatch.');
                }

                $this->applyAuditFields($lockedPayment, $data);
                $lockedPayment->save();

                return PaymentVerificationResult::duplicate();
            }

            $releasedSeatExists = $booking->bookingSeats()->exists()
                && $booking->bookingSeats()->whereNull('active_lock_key')->exists();

            if ($booking->booking_status === 'expired'
                || ! $booking->expires_at
                || $booking->expires_at->isPast()
                || $releasedSeatExists) {
                $this->markReview($lockedPayment, $data, 'late_payment_after_expiration');

                return PaymentVerificationResult::rejected('Payment arrived after booking expiration.');
            }

            if ($lockedPayment->status !== Payment::STATUS_PENDING
                || $booking->payment_status === 'paid'
                || $booking->booking_status !== 'pending_payment') {
                $this->markReview($lockedPayment, $data, 'booking_not_payable');

                return PaymentVerificationResult::rejected('Booking is no longer payable.');
            }

            $now = now();
            $this->applyAuditFields($lockedPayment, $data);
            $lockedPayment->forceFill([
                'status' => Payment::STATUS_SUCCESS,
                'verified_at' => $now,
                'paid_at' => $now,
                'failed_at' => null,
                'failure_reason' => null,
            ])->save();

            $booking->forceFill([
                'payment_method' => 'zalopay',
                'payment_status' => 'paid',
                'booking_status' => 'paid',
                'paid_at' => $now,
            ])->save();

            DB::afterCommit(function () use ($booking): void {
                try {
                    SendBookingTicket::dispatch($booking->id);
                } catch (Throwable $exception) {
                    Log::error('Paid booking ticket dispatch failed and can be retried separately.', [
                        'booking_id' => $booking->id,
                        'exception' => $exception::class,
                    ]);
                }
            });

            return PaymentVerificationResult::transitioned();
        });
    }

    private function markReview(
        Payment $payment,
        VerifiedPaymentData $data,
        string $reason,
        bool $storeTransactionId = true,
    ): void {
        $this->applyAuditFields($payment, $data, $storeTransactionId);
        $payment->forceFill([
            'status' => Payment::STATUS_REVIEW,
            'failure_reason' => $reason,
            'failed_at' => now(),
        ])->save();
    }

    private function applyAuditFields(
        Payment $payment,
        VerifiedPaymentData $data,
        bool $storeTransactionId = true,
    ): void {
        $fields = [
            'server_time_ms' => $data->serverTimeMs,
        ];

        if ($storeTransactionId && $data->zpTransId !== null) {
            $fields['zp_trans_id'] = $data->zpTransId;
        }

        if ($data->source === 'callback') {
            $fields['callback_received_at'] = now();
            $fields['callback_payload_hash'] = $data->payloadHash;
        } elseif ($data->source === 'query') {
            $fields['query_response_hash'] = $data->payloadHash;
        }

        $payment->forceFill($fields);
    }
}
