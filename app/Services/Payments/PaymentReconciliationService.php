<?php

namespace App\Services\Payments;

use App\Domain\Payments\VerifiedPaymentData;
use App\Domain\Payments\ZaloPayConfig;
use App\Exceptions\ZaloPayAuthenticationException;
use App\Exceptions\ZaloPayResponseException;
use App\Models\Payment;
use App\Services\ZaloPay\ZaloPayGateway;

class PaymentReconciliationService
{
    public function __construct(
        private readonly ZaloPayGateway $gateway,
        private readonly ZaloPayConfig $config,
        private readonly VerifiedPaymentService $verifiedPayments,
    ) {}

    public function reconcile(Payment $payment): string
    {
        $payment->forceFill(['last_queried_at' => now()])->save();

        try {
            $response = $this->gateway->query($payment);
        } catch (ZaloPayAuthenticationException $exception) {
            $payment->forceFill([
                'status' => Payment::STATUS_REVIEW,
                'failed_at' => now(),
                'failure_reason' => 'query_authentication_error',
            ])->save();

            return Payment::STATUS_REVIEW;
        }

        $payload = $response->payload;
        $this->storeQueryAudit($payment, $payload, $response->hash);

        if ($payload['return_code'] === 1) {
            if (! is_int($payload['amount'] ?? null)) {
                throw new ZaloPayResponseException('ZaloPay successful query omitted an integer amount.');
            }

            $result = $this->verifiedPayments->verify($payment, new VerifiedPaymentData(
                appId: $this->config->appId,
                appTransId: $payment->app_trans_id,
                amount: $payload['amount'],
                zpTransId: $this->normalizeTransactionId($payload['zp_trans_id'] ?? null),
                serverTimeMs: is_int($payload['server_time'] ?? null) ? $payload['server_time'] : null,
                source: 'query',
                payloadHash: $response->hash,
            ));

            return $result->accepted ? Payment::STATUS_SUCCESS : $payment->fresh()->status;
        }

        if ($payload['return_code'] === 3) {
            return Payment::STATUS_PENDING;
        }

        if ($payload['return_code'] !== 2) {
            throw new ZaloPayResponseException('ZaloPay query returned an unsupported return code.');
        }

        $subCode = $payload['sub_return_code'] ?? null;
        if ($subCode === -54) {
            $payment->forceFill([
                'status' => Payment::STATUS_EXPIRED,
                'failed_at' => now(),
                'failure_reason' => 'query_expired',
            ])->save();

            return Payment::STATUS_EXPIRED;
        }

        if ($subCode === -101) {
            $payment->forceFill([
                'status' => Payment::STATUS_REVIEW,
                'failed_at' => now(),
                'failure_reason' => 'query_unresolved',
            ])->save();

            return Payment::STATUS_REVIEW;
        }

        $payment->forceFill([
            'status' => Payment::STATUS_FAILED,
            'failed_at' => now(),
            'failure_reason' => 'query_failed',
        ])->save();

        return Payment::STATUS_FAILED;
    }

    private function storeQueryAudit(Payment $payment, array $payload, string $hash): void
    {
        $payment->forceFill([
            'provider_return_code' => $payload['return_code'],
            'provider_sub_return_code' => is_int($payload['sub_return_code'] ?? null)
                ? $payload['sub_return_code'] : null,
            'provider_return_message' => $this->message($payload['return_message'] ?? null),
            'provider_sub_return_message' => $this->message($payload['sub_return_message'] ?? null),
            'query_response_hash' => $hash,
        ])->save();
    }

    private function message(mixed $message): ?string
    {
        return is_string($message) ? mb_substr($message, 0, 255) : null;
    }

    private function normalizeTransactionId(mixed $value): ?string
    {
        if (is_int($value)) {
            return (string) $value;
        }

        return is_string($value) && preg_match('/^[0-9]+$/D', $value) ? $value : null;
    }
}
