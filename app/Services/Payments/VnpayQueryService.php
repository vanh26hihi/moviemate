<?php

namespace App\Services\Payments;

use App\Domain\Payments\VerifiedPaymentData;
use App\Domain\Payments\VnpayConfig;
use App\Exceptions\VnpayAuthenticationException;
use App\Exceptions\VnpayResponseException;
use App\Exceptions\VnpayTransportException;
use App\Models\Booking;
use App\Models\Payment;
use App\Services\Vnpay\VnpayGateway;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

class VnpayQueryService
{
    public function __construct(
        private readonly VnpayGateway $gateway,
        private readonly VnpayConfig $config,
        private readonly VerifiedPaymentService $verifiedPayments,
    ) {}

    public function reconcile(Payment $payment): string
    {
        return $this->reconcileEligiblePayment($payment, false);
    }

    public function reconcileReview(Payment $payment): string
    {
        return $this->reconcileEligiblePayment($payment, true);
    }

    private function reconcileEligiblePayment(Payment $payment, bool $allowReview): string
    {
        if (! $this->beginQuery($payment, $allowReview)) {
            return $payment->fresh()->status;
        }

        try {
            $response = $this->gateway->query($payment->fresh());
        } catch (VnpayAuthenticationException $exception) {
            return $this->applyOutcome($payment, Payment::STATUS_REVIEW, 'query_authentication_error', allowReview: $allowReview);
        } catch (VnpayTransportException $exception) {
            $this->applyOutcome($payment, Payment::STATUS_UNRESOLVED, 'query_transport_unknown', allowReview: $allowReview);
            throw $exception;
        } catch (VnpayResponseException $exception) {
            $this->applyOutcome($payment, Payment::STATUS_UNRESOLVED, 'query_response_unknown', allowReview: $allowReview);
            throw $exception;
        }

        $payload = $response->payload;
        if (($payload['vnp_TmnCode'] ?? null) !== $this->config->tmnCode
            || ($payload['vnp_TxnRef'] ?? null) !== $payment->order_code) {
            return $this->applyOutcome($payment, Payment::STATUS_REVIEW, 'query_identity_mismatch', $payload, $response->hash, $allowReview);
        }

        $responseCode = $payload['vnp_ResponseCode'] ?? null;
        $transactionStatus = $payload['vnp_TransactionStatus'] ?? null;
        if (! is_string($responseCode) || ! is_string($transactionStatus)) {
            return $this->applyOutcome($payment, Payment::STATUS_REVIEW, 'query_response_schema_invalid', $payload, $response->hash, $allowReview);
        }

        if ($responseCode === '00' && $transactionStatus === '00') {
            $amount = $this->providerAmount($payload['vnp_Amount'] ?? null);
            if ($amount === null) {
                return $this->applyOutcome($payment, Payment::STATUS_REVIEW, 'query_amount_invalid', $payload, $response->hash, $allowReview);
            }

            $verifiedData = new VerifiedPaymentData(
                provider: 'vnpay',
                merchantReference: $payment->order_code,
                amount: $amount,
                providerTransactionId: $this->transactionId($payload['vnp_TransactionNo'] ?? null),
                source: 'query',
                payloadHash: $response->hash,
                responseCode: $responseCode,
                transactionStatus: $transactionStatus,
                bankCode: $this->shortText($payload['vnp_BankCode'] ?? null, 20),
                providerPaidAt: $this->payDate($payload['vnp_PayDate'] ?? null),
            );
            $result = $allowReview
                ? $this->verifiedPayments->verifyReview($payment, $verifiedData)
                : $this->verifiedPayments->verify($payment, $verifiedData);

            return $result->accepted ? Payment::STATUS_SUCCESS : $payment->fresh()->status;
        }

        return match ($transactionStatus) {
            '01' => $this->storePending($payment, $payload, $response->hash, $allowReview),
            '02' => $this->applyOutcome($payment, Payment::STATUS_FAILED, 'query_failed', $payload, $response->hash, $allowReview),
            '04', '07' => $this->applyOutcome($payment, Payment::STATUS_REVIEW, 'query_requires_review', $payload, $response->hash, $allowReview),
            default => $this->applyOutcome($payment, Payment::STATUS_REVIEW, 'query_unknown_status', $payload, $response->hash, $allowReview),
        };
    }

    private function beginQuery(Payment $payment, bool $allowReview): bool
    {
        return DB::transaction(function () use ($payment, $allowReview): bool {
            Booking::query()->lockForUpdate()->findOrFail($payment->booking_id);
            $locked = Payment::query()->lockForUpdate()->findOrFail($payment->id);
            $isReview = $allowReview && $locked->status === Payment::STATUS_REVIEW;
            if (! $isReview && ! in_array($locked->status, Payment::RECONCILABLE_STATUSES, true)) {
                return false;
            }
            if (! $isReview && (! $locked->reconcile_until || $locked->reconcile_until->isPast())) {
                $locked->forceFill(['status' => Payment::STATUS_UNRESOLVED, 'failure_reason' => 'reconciliation_window_elapsed'])->save();

                return false;
            }
            $locked->forceFill(['last_queried_at' => now()])->save();

            return true;
        });
    }

    private function applyOutcome(
        Payment $payment,
        string $status,
        string $reason,
        ?array $payload = null,
        ?string $hash = null,
        bool $allowReview = false,
    ): string {
        return DB::transaction(function () use ($payment, $status, $reason, $payload, $hash, $allowReview): string {
            Booking::query()->lockForUpdate()->findOrFail($payment->booking_id);
            $locked = Payment::query()->lockForUpdate()->findOrFail($payment->id);
            if ($locked->status === Payment::STATUS_SUCCESS) {
                return Payment::STATUS_SUCCESS;
            }
            $isReview = $allowReview && $locked->status === Payment::STATUS_REVIEW;
            if (! $isReview && ! in_array($locked->status, Payment::RECONCILABLE_STATUSES, true)) {
                return $locked->status;
            }
            $fields = [
                'status' => $isReview ? Payment::STATUS_REVIEW : $status,
                'failure_reason' => $reason,
                'failed_at' => $isReview || ! in_array($status, Payment::RECONCILABLE_STATUSES, true) ? now() : null,
            ];
            if ($payload !== null) {
                $fields += [
                    'response_code' => $this->shortText($payload['vnp_ResponseCode'] ?? null, 20),
                    'transaction_status' => $this->shortText($payload['vnp_TransactionStatus'] ?? null, 20),
                    'bank_code' => $this->shortText($payload['vnp_BankCode'] ?? null, 20),
                    'query_response_hash' => $hash,
                ];
            }
            $locked->forceFill($fields)->save();

            return $locked->status;
        });
    }

    /** @param array<string, string> $payload */
    private function storePending(Payment $payment, array $payload, string $hash, bool $allowReview): string
    {
        return DB::transaction(function () use ($payment, $payload, $hash, $allowReview): string {
            Booking::query()->lockForUpdate()->findOrFail($payment->booking_id);
            $locked = Payment::query()->lockForUpdate()->findOrFail($payment->id);
            $isReview = $allowReview && $locked->status === Payment::STATUS_REVIEW;
            if ($locked->status === Payment::STATUS_SUCCESS
                || (! $isReview && ! in_array($locked->status, Payment::RECONCILABLE_STATUSES, true))) {
                return $locked->status;
            }
            $locked->forceFill([
                'response_code' => $this->shortText($payload['vnp_ResponseCode'] ?? null, 20),
                'transaction_status' => $this->shortText($payload['vnp_TransactionStatus'] ?? null, 20),
                'bank_code' => $this->shortText($payload['vnp_BankCode'] ?? null, 20),
                'query_response_hash' => $hash,
                'failure_reason' => 'query_pending',
            ])->save();

            return $locked->status;
        });
    }

    private function providerAmount(mixed $value): ?int
    {
        if (! is_string($value) || preg_match('/^[0-9]{3,15}$/D', $value) !== 1 || ! str_ends_with($value, '00')) {
            return null;
        }
        $vnd = substr($value, 0, -2);

        return strlen($vnd) < strlen((string) PHP_INT_MAX) ? (int) $vnd : null;
    }

    private function transactionId(mixed $value): ?string
    {
        return is_string($value) && preg_match('/^[0-9]+$/D', $value) === 1 ? $value : null;
    }

    private function payDate(mixed $value): ?CarbonImmutable
    {
        if (! is_string($value) || preg_match('/^\d{14}$/D', $value) !== 1) {
            return null;
        }
        try {
            return CarbonImmutable::createFromFormat('!YmdHis', $value, VnpayConfig::TIMEZONE);
        } catch (\Throwable) {
            return null;
        }
    }

    private function shortText(mixed $value, int $length): ?string
    {
        return is_string($value) ? mb_substr($value, 0, $length) : null;
    }
}
