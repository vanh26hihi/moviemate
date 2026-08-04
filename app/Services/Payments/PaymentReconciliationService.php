<?php

namespace App\Services\Payments;

use App\Domain\Payments\VerifiedPaymentData;
use App\Domain\Payments\ZaloPayConfig;
use App\Exceptions\ZaloPayAuthenticationException;
use App\Exceptions\ZaloPayResponseException;
use App\Exceptions\ZaloPayTransportException;
use App\Models\Booking;
use App\Models\Payment;
use App\Services\ZaloPay\ZaloPayGateway;
use Illuminate\Support\Facades\DB;

class PaymentReconciliationService
{
    public function __construct(
        private readonly ZaloPayGateway $gateway,
        private readonly ZaloPayConfig $config,
        private readonly VerifiedPaymentService $verifiedPayments,
    ) {}

    public function reconcile(Payment $payment): string
    {
        $queryStarted = DB::transaction(function () use ($payment): bool {
            Booking::query()->lockForUpdate()->findOrFail($payment->booking_id);
            $lockedPayment = Payment::query()->lockForUpdate()->findOrFail($payment->id);

            if (! in_array($lockedPayment->status, Payment::RECONCILABLE_STATUSES, true)) {
                return false;
            }

            if (! $lockedPayment->reconcile_until || $lockedPayment->reconcile_until->isPast()) {
                $lockedPayment->forceFill([
                    'status' => Payment::STATUS_UNRESOLVED,
                    'failed_at' => null,
                    'failure_reason' => 'reconciliation_window_elapsed',
                ])->save();

                return false;
            }

            $lockedPayment->forceFill(['last_queried_at' => now()])->save();

            return true;
        });

        if (! $queryStarted) {
            return $payment->fresh()->status;
        }

        try {
            $response = $this->gateway->query($payment);
        } catch (ZaloPayAuthenticationException $exception) {
            return $this->applyOutcome(
                $payment,
                Payment::STATUS_REVIEW,
                'query_authentication_error',
            );
        } catch (ZaloPayTransportException $exception) {
            $this->recordUnknown($payment, 'query_transport_unknown');

            throw $exception;
        } catch (ZaloPayResponseException $exception) {
            $this->recordUnknown($payment, 'query_response_unknown');

            throw $exception;
        }

        $payload = $response->payload;

        if ($payload['return_code'] === 1) {
            if (! is_int($payload['amount'] ?? null)) {
                $this->recordUnknown($payment, 'query_response_unknown');
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

            if ($result->accepted) {
                return Payment::STATUS_SUCCESS;
            }

            return $this->storeQueryAudit($payment, $payload, $response->hash);
        }

        if ($payload['return_code'] === 3) {
            return $this->storeQueryAudit($payment, $payload, $response->hash);
        }

        if ($payload['return_code'] !== 2) {
            $this->recordUnknown($payment, 'query_response_unknown');
            throw new ZaloPayResponseException('ZaloPay query returned an unsupported return code.');
        }

        $subCode = $payload['sub_return_code'] ?? null;
        if ($subCode === -54) {
            return $this->applyOutcome(
                $payment,
                Payment::STATUS_EXPIRED,
                'query_expired',
                $payload,
                $response->hash,
            );
        }

        if ($subCode === -101) {
            return $this->applyOutcome(
                $payment,
                Payment::STATUS_UNRESOLVED,
                'query_unresolved',
                $payload,
                $response->hash,
            );
        }

        return $this->applyOutcome(
            $payment,
            Payment::STATUS_FAILED,
            'query_failed',
            $payload,
            $response->hash,
        );
    }

    private function recordUnknown(Payment $payment, string $reason): void
    {
        DB::transaction(function () use ($payment, $reason): void {
            Booking::query()->lockForUpdate()->findOrFail($payment->booking_id);
            $lockedPayment = Payment::query()->lockForUpdate()->findOrFail($payment->id);

            if ($lockedPayment->status === Payment::STATUS_SUCCESS) {
                return;
            }

            if (! in_array($lockedPayment->status, Payment::RECONCILABLE_STATUSES, true)) {
                return;
            }

            $lockedPayment->forceFill([
                'status' => Payment::STATUS_UNRESOLVED,
                'failed_at' => null,
                'failure_reason' => $reason,
            ])->save();
        });
    }

    private function applyOutcome(
        Payment $payment,
        string $status,
        string $reason,
        ?array $payload = null,
        ?string $hash = null,
    ): string {
        return DB::transaction(function () use ($payment, $status, $reason, $payload, $hash): string {
            Booking::query()->lockForUpdate()->findOrFail($payment->booking_id);
            $lockedPayment = Payment::query()->lockForUpdate()->findOrFail($payment->id);

            if ($lockedPayment->status === Payment::STATUS_SUCCESS) {
                return Payment::STATUS_SUCCESS;
            }

            if ($payload !== null && $hash !== null) {
                $this->fillQueryAudit($lockedPayment, $payload, $hash);
            }

            if (in_array($lockedPayment->status, Payment::RECONCILABLE_STATUSES, true)) {
                $lockedPayment->forceFill([
                    'status' => $status,
                    'failed_at' => in_array($status, Payment::RECONCILABLE_STATUSES, true)
                        ? null
                        : now(),
                    'failure_reason' => $reason,
                ]);
            }

            $lockedPayment->save();

            return $lockedPayment->status;
        });
    }

    private function storeQueryAudit(Payment $payment, array $payload, string $hash): string
    {
        return DB::transaction(function () use ($payment, $payload, $hash): string {
            Booking::query()->lockForUpdate()->findOrFail($payment->booking_id);
            $lockedPayment = Payment::query()->lockForUpdate()->findOrFail($payment->id);

            if ($lockedPayment->status === Payment::STATUS_SUCCESS) {
                return Payment::STATUS_SUCCESS;
            }

            $this->fillQueryAudit($lockedPayment, $payload, $hash);
            $lockedPayment->save();

            return $lockedPayment->status;
        });
    }

    private function fillQueryAudit(Payment $payment, array $payload, string $hash): void
    {
        $payment->forceFill([
            'provider_return_code' => $payload['return_code'],
            'provider_sub_return_code' => is_int($payload['sub_return_code'] ?? null)
                ? $payload['sub_return_code'] : null,
            'provider_return_message' => $this->message($payload['return_message'] ?? null),
            'provider_sub_return_message' => $this->message($payload['sub_return_message'] ?? null),
            'query_response_hash' => $hash,
        ]);
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
