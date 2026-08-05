<?php

namespace App\Services\Payments;

use App\Domain\Payments\PaymentVerificationResult;
use App\Domain\Payments\VerifiedPaymentData;
use App\Models\Booking;
use App\Models\BookingSeat;
use App\Models\Order;
use App\Models\Payment;
use App\Services\Tickets\TicketDeliveryOutbox;
use Illuminate\Support\Facades\DB;

class VerifiedPaymentService
{
    public function __construct(private readonly TicketDeliveryOutbox $ticketDeliveries) {}

    public function verify(Payment $payment, VerifiedPaymentData $data): PaymentVerificationResult
    {
        return $this->verifyEligiblePayment($payment, $data, false);
    }

    public function verifyReview(Payment $payment, VerifiedPaymentData $data): PaymentVerificationResult
    {
        return $this->verifyEligiblePayment($payment, $data, true);
    }

    private function verifyEligiblePayment(
        Payment $payment,
        VerifiedPaymentData $data,
        bool $allowReview,
    ): PaymentVerificationResult {
        return DB::transaction(function () use ($payment, $data, $allowReview): PaymentVerificationResult {
            $booking = Booking::query()->lockForUpdate()->findOrFail($payment->booking_id);
            $lockedPayment = Payment::query()->lockForUpdate()->findOrFail($payment->getKey());

            if ($lockedPayment->status === Payment::STATUS_SUCCESS) {
                return PaymentVerificationResult::duplicate();
            }

            $eligible = $allowReview
                ? $lockedPayment->status === Payment::STATUS_REVIEW
                : in_array($lockedPayment->status, Payment::RECONCILABLE_STATUSES, true);

            if (! $eligible) {
                return PaymentVerificationResult::rejected(
                    'Payment attempt is not eligible for automatic fulfillment.',
                );
            }

            if (! $this->identityMatches($lockedPayment, $data)) {
                return PaymentVerificationResult::rejected('Invalid payment identity.');
            }

            if ($lockedPayment->amount !== $data->amount) {
                $this->markReview($lockedPayment, $data, 'amount_mismatch');

                return PaymentVerificationResult::rejected('Payment amount mismatch.');
            }

            if ($data->providerTransactionId === null) {
                $this->markReview($lockedPayment, $data, $lockedPayment->provider === 'zalopay'
                    ? 'missing_zp_trans_id' : 'missing_provider_transaction_id');

                return PaymentVerificationResult::rejected('Missing verified provider transaction identity.');
            }

            $storedTransactionId = $this->storedTransactionId($lockedPayment);
            if ($storedTransactionId !== null
                && $storedTransactionId !== $data->providerTransactionId) {
                $this->markReview($lockedPayment, $data, $lockedPayment->provider === 'zalopay'
                    ? 'zp_trans_id_mismatch' : 'provider_transaction_id_mismatch', false);

                return PaymentVerificationResult::rejected('Provider transaction identity mismatch.');
            }

            if ($data->providerTransactionId !== null) {
                $transactionColumn = $lockedPayment->provider === 'zalopay' ? 'zp_trans_id' : 'transaction_id';
                $duplicateTransaction = Payment::query()
                    ->where('provider', $lockedPayment->provider)
                    ->where($transactionColumn, $data->providerTransactionId)
                    ->whereKeyNot($lockedPayment->getKey())
                    ->lockForUpdate()
                    ->exists();

                if ($duplicateTransaction) {
                    $this->markReview($lockedPayment, $data, $lockedPayment->provider === 'zalopay'
                        ? 'duplicate_zp_trans_id' : 'duplicate_provider_transaction_id', false);

                    return PaymentVerificationResult::rejected('Provider transaction belongs to another attempt.');
                }
            }

            $laterAttemptExists = Payment::query()
                ->where('booking_id', $booking->getKey())
                ->where('provider', $lockedPayment->provider)
                ->where('id', '>', $lockedPayment->getKey())
                ->lockForUpdate()
                ->exists();

            if ($laterAttemptExists) {
                $this->markReview($lockedPayment, $data, 'incompatible_later_attempt');

                return PaymentVerificationResult::rejected(
                    'A later payment attempt exists for this booking.',
                );
            }

            $seatLocks = $booking->bookingSeats()->lockForUpdate()->get();
            $seatsAreOwned = $seatLocks->isNotEmpty()
                && $seatLocks->every(
                    fn (BookingSeat $seat): bool => $seat->showtime_id === $booking->showtime_id
                        && $seat->active_lock_key === BookingSeat::ACTIVE_LOCK_KEY,
                );

            if ($booking->booking_status === 'expired'
                || ! $booking->expires_at
                || $booking->expires_at->isPast()) {
                $this->markReview($lockedPayment, $data, 'late_payment_after_expiration');

                return PaymentVerificationResult::rejected('Payment arrived after booking expiration.');
            }

            if (! $seatsAreOwned) {
                $this->markReview($lockedPayment, $data, 'seat_ownership_lost');

                return PaymentVerificationResult::rejected('Booking no longer owns its reserved seats.');
            }

            if ($booking->payment_status === 'paid'
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
                'payment_method' => $lockedPayment->provider,
                'payment_status' => 'paid',
                'booking_status' => 'paid',
                'paid_at' => $now,
            ])->save();

            $foodOrder = Order::query()
                ->where('booking_id', $booking->id)
                ->where('status', 'pending')
                ->lockForUpdate()
                ->first();
            $foodOrder?->forceFill(['status' => 'paid'])->save();
            $this->ticketDeliveries->enqueueVerifiedBooking($booking);

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

        if ($storeTransactionId && $data->providerTransactionId !== null) {
            $fields[$payment->provider === 'zalopay' ? 'zp_trans_id' : 'transaction_id'] = $data->providerTransactionId;
        }

        if ($payment->provider === 'vnpay') {
            $fields += [
                'response_code' => $data->responseCode,
                'transaction_status' => $data->transactionStatus,
                'bank_code' => $data->bankCode,
                'card_type' => $data->cardType,
                'provider_paid_at' => $data->providerPaidAt,
            ];
        }

        if (in_array($data->source, ['callback', 'ipn'], true)) {
            $fields['callback_received_at'] = now();
            $fields['callback_payload_hash'] = $data->payloadHash;
        } elseif ($data->source === 'query') {
            $fields['query_response_hash'] = $data->payloadHash;
        }

        $payment->forceFill($fields);
    }

    private function identityMatches(Payment $payment, VerifiedPaymentData $data): bool
    {
        if ($payment->provider !== $data->provider) {
            return false;
        }

        return match ($payment->provider) {
            'zalopay' => $data->appId !== null
                && $payment->app_id === $data->appId
                && $payment->app_trans_id === $data->merchantReference,
            'vnpay' => $payment->order_code === $data->merchantReference,
            default => false,
        };
    }

    private function storedTransactionId(Payment $payment): ?string
    {
        return $payment->provider === 'zalopay' ? $payment->zp_trans_id : $payment->transaction_id;
    }
}
