<?php

namespace App\Services\Payments;

use App\Domain\Payments\VerifiedPaymentData;
use App\Models\Booking;
use App\Models\Payment;
use App\Services\BookingCancellationService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

final class PayOsPaymentStateService
{
    public function __construct(
        private readonly VerifiedPaymentService $verifiedPayments,
        private readonly BookingCancellationService $cancellations,
    ) {}

    /** @param array<string, mixed> $data */
    public function apply(
        Payment $payment,
        array $data,
        string $source,
        string $payloadHash,
        bool $allowReview = false,
    ): string {
        $identity = $this->identity($data);
        if ($identity === null) {
            return $this->markReview($payment, 'payos_response_schema_invalid', $source, $payloadHash);
        }

        $preflight = $this->preflight($payment, $identity, $source, $payloadHash, $allowReview);
        if ($preflight !== null) {
            return $preflight;
        }

        return match ($identity['status']) {
            'PAID' => $this->paid($payment, $identity, $source, $payloadHash, $allowReview),
            'CANCELLED' => $this->cancelled($payment, $identity, $source, $payloadHash),
            'PENDING' => $this->retain($payment, Payment::STATUS_PENDING, 'payos_pending', $identity, $source, $payloadHash, $allowReview),
            'PROCESSING' => $this->retain($payment, Payment::STATUS_PROCESSING, 'payos_processing', $identity, $source, $payloadHash, $allowReview),
            default => $this->markReview($payment, 'payos_unknown_status', $source, $payloadHash, $identity),
        };
    }

    /** @param array<string, mixed> $data
     * @return array{orderCode:int,amount:int,status:string,paymentLinkId:string,currency:?string,reference:?string,amountPaid:?int,code:?string,transactionDateTime:?string,transactions:array}|null
     */
    private function identity(array $data): ?array
    {
        $paymentLinkId = $data['paymentLinkId'] ?? $data['id'] ?? null;
        $status = $data['status'] ?? null;
        if (! is_int($data['orderCode'] ?? null)
            || $data['orderCode'] <= 0
            || ! is_int($data['amount'] ?? null)
            || $data['amount'] <= 0
            || ! is_string($paymentLinkId)
            || preg_match('/^[A-Za-z0-9_-]{8,100}$/D', $paymentLinkId) !== 1
            || ! is_string($status)
            || preg_match('/^[A-Z_]{2,30}$/D', $status) !== 1) {
            return null;
        }

        $currency = $data['currency'] ?? null;
        $reference = $data['reference'] ?? $this->transactionReference($data['transactions'] ?? []);

        return [
            'orderCode' => $data['orderCode'],
            'amount' => $data['amount'],
            'status' => $status,
            'paymentLinkId' => $paymentLinkId,
            'currency' => is_string($currency) ? strtoupper($currency) : null,
            'reference' => is_string($reference) && preg_match('/^[A-Za-z0-9._-]{1,128}$/D', $reference) === 1
                ? $reference : null,
            'amountPaid' => is_int($data['amountPaid'] ?? null) ? $data['amountPaid'] : null,
            'code' => is_string($data['code'] ?? null) ? mb_substr($data['code'], 0, 20) : null,
            'transactionDateTime' => is_string($data['transactionDateTime'] ?? null)
                ? $data['transactionDateTime'] : null,
            'transactions' => is_array($data['transactions'] ?? null) ? $data['transactions'] : [],
        ];
    }

    /** @param array<string, mixed> $identity */
    private function preflight(
        Payment $payment,
        array $identity,
        string $source,
        string $payloadHash,
        bool $allowReview,
    ): ?string {
        return DB::transaction(function () use ($payment, $identity, $source, $payloadHash, $allowReview): ?string {
            Booking::query()->lockForUpdate()->findOrFail($payment->booking_id);
            $locked = Payment::query()->lockForUpdate()->findOrFail($payment->id);
            if ($locked->status === Payment::STATUS_SUCCESS) {
                return Payment::STATUS_SUCCESS;
            }
            if ($locked->status === Payment::STATUS_FAILED
                && $locked->failure_reason === 'payos_cancelled'
                && $identity['status'] === 'PAID') {
                $locked->forceFill($this->auditFields($identity, $source, $payloadHash) + [
                    'status' => Payment::STATUS_REVIEW,
                    'failure_reason' => 'late_paid_after_payos_cancelled',
                    'failed_at' => now(),
                ])->save();

                return Payment::STATUS_REVIEW;
            }
            $eligible = in_array($locked->status, Payment::RECONCILABLE_STATUSES, true)
                || ($allowReview && $locked->status === Payment::STATUS_REVIEW);
            if (! $eligible) {
                return $locked->status;
            }

            $reason = match (true) {
                $locked->provider !== 'payos' || $locked->order_code !== (string) $identity['orderCode'] => 'payos_order_code_mismatch',
                $locked->amount !== $identity['amount'] => 'amount_mismatch',
                $identity['currency'] !== null && $identity['currency'] !== 'VND' => 'payos_currency_mismatch',
                is_string($locked->transaction_code) && $locked->transaction_code !== ''
                    && $locked->transaction_code !== $identity['paymentLinkId'] => 'payos_payment_link_mismatch',
                $source === 'query' && $identity['status'] === 'PAID'
                    && $identity['amountPaid'] !== $locked->amount => 'payos_amount_paid_mismatch',
                default => null,
            };
            if ($reason !== null) {
                $locked->forceFill($this->auditFields($identity, $source, $payloadHash) + [
                    'status' => Payment::STATUS_REVIEW,
                    'failure_reason' => $reason,
                    'failed_at' => now(),
                ])->save();

                return Payment::STATUS_REVIEW;
            }

            if (! is_string($locked->transaction_code) || $locked->transaction_code === '') {
                $locked->forceFill(['transaction_code' => $identity['paymentLinkId']])->save();
            }

            return null;
        });
    }

    /** @param array<string, mixed> $identity */
    private function paid(
        Payment $payment,
        array $identity,
        string $source,
        string $payloadHash,
        bool $allowReview,
    ): string {
        if ($identity['reference'] === null) {
            return $this->markReview($payment, 'missing_provider_transaction_id', $source, $payloadHash, $identity);
        }

        $data = new VerifiedPaymentData(
            provider: 'payos',
            merchantReference: (string) $identity['orderCode'],
            amount: $identity['amount'],
            providerTransactionId: $identity['reference'],
            source: $source === 'webhook' ? 'callback' : 'query',
            payloadHash: $payloadHash,
            responseCode: $identity['code'] ?? '00',
            transactionStatus: 'PAID',
            providerPaidAt: $this->paidAt($identity['transactionDateTime']),
        );
        $result = $allowReview
            ? $this->verifiedPayments->verifyReview($payment, $data)
            : $this->verifiedPayments->verify($payment, $data);

        return $result->accepted ? Payment::STATUS_SUCCESS : $payment->fresh()->status;
    }

    /** @param array<string, mixed> $identity */
    private function cancelled(
        Payment $payment,
        array $identity,
        string $source,
        string $payloadHash,
    ): string {
        $status = DB::transaction(function () use ($payment, $identity, $source, $payloadHash): string {
            Booking::query()->lockForUpdate()->findOrFail($payment->booking_id);
            $locked = Payment::query()->lockForUpdate()->findOrFail($payment->id);
            if ($locked->status === Payment::STATUS_SUCCESS) {
                return Payment::STATUS_SUCCESS;
            }
            if (! in_array($locked->status, Payment::RECONCILABLE_STATUSES, true)
                && $locked->status !== Payment::STATUS_FAILED) {
                return $locked->status;
            }
            $locked->forceFill($this->auditFields($identity, $source, $payloadHash) + [
                'status' => Payment::STATUS_FAILED,
                'failure_reason' => 'payos_cancelled',
                'failed_at' => $locked->failed_at ?? now(),
            ])->save();

            return Payment::STATUS_FAILED;
        });

        if ($status === Payment::STATUS_FAILED) {
            $this->cancellations->cancel($payment->booking_id);
        }

        return $status;
    }

    /** @param array<string, mixed> $identity */
    private function retain(
        Payment $payment,
        string $status,
        string $reason,
        array $identity,
        string $source,
        string $payloadHash,
        bool $allowReview,
    ): string {
        return DB::transaction(function () use ($payment, $status, $reason, $identity, $source, $payloadHash, $allowReview): string {
            Booking::query()->lockForUpdate()->findOrFail($payment->booking_id);
            $locked = Payment::query()->lockForUpdate()->findOrFail($payment->id);
            if ($locked->status === Payment::STATUS_SUCCESS) {
                return Payment::STATUS_SUCCESS;
            }
            if ($allowReview && $locked->status === Payment::STATUS_REVIEW) {
                $locked->forceFill($this->auditFields($identity, $source, $payloadHash))->save();

                return Payment::STATUS_REVIEW;
            }
            if (in_array($locked->status, Payment::RECONCILABLE_STATUSES, true)) {
                $locked->forceFill($this->auditFields($identity, $source, $payloadHash) + [
                    'status' => $status,
                    'failure_reason' => $reason,
                    'failed_at' => null,
                ])->save();
            }

            return $locked->status;
        });
    }

    /** @param array<string, mixed>|null $identity */
    private function markReview(
        Payment $payment,
        string $reason,
        string $source,
        string $payloadHash,
        ?array $identity = null,
    ): string {
        return DB::transaction(function () use ($payment, $reason, $source, $payloadHash, $identity): string {
            Booking::query()->lockForUpdate()->findOrFail($payment->booking_id);
            $locked = Payment::query()->lockForUpdate()->findOrFail($payment->id);
            if ($locked->status === Payment::STATUS_SUCCESS) {
                return Payment::STATUS_SUCCESS;
            }
            if (in_array($locked->status, Payment::UNSAFE_RETRY_STATUSES, true)) {
                $audit = $identity === null
                    ? [$source === 'webhook' ? 'callback_payload_hash' : 'query_response_hash' => $payloadHash]
                    : $this->auditFields($identity, $source, $payloadHash);
                $locked->forceFill($audit + [
                    'status' => Payment::STATUS_REVIEW,
                    'failure_reason' => $reason,
                    'failed_at' => now(),
                ])->save();
            }

            return $locked->status;
        });
    }

    /** @param array<string, mixed> $identity
     * @return array<string, mixed>
     */
    private function auditFields(array $identity, string $source, string $payloadHash): array
    {
        return [
            'transaction_code' => $identity['paymentLinkId'],
            'transaction_status' => $identity['status'],
            'response_code' => $identity['code'],
            $source === 'webhook' ? 'callback_received_at' : 'last_queried_at' => now(),
            $source === 'webhook' ? 'callback_payload_hash' : 'query_response_hash' => $payloadHash,
        ];
    }

    private function transactionReference(mixed $transactions): ?string
    {
        if (! is_array($transactions)) {
            return null;
        }
        if (is_string($transactions['reference'] ?? null)) {
            return $transactions['reference'];
        }
        foreach (array_reverse($transactions) as $transaction) {
            if (is_array($transaction) && is_string($transaction['reference'] ?? null)) {
                return $transaction['reference'];
            }
        }

        return null;
    }

    private function paidAt(mixed $value): ?CarbonImmutable
    {
        if (! is_string($value) || strlen($value) > 40) {
            return null;
        }
        try {
            return CarbonImmutable::parse($value, 'Asia/Ho_Chi_Minh');
        } catch (\Throwable) {
            return null;
        }
    }
}
