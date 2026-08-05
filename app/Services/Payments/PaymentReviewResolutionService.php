<?php

namespace App\Services\Payments;

use App\Domain\Payments\PaymentReviewResolutionResult;
use App\Domain\Payments\VerifiedPaymentData;
use App\Domain\Payments\ZaloPayConfig;
use App\Exceptions\ZaloPayAuthenticationException;
use App\Exceptions\ZaloPayResponseException;
use App\Exceptions\ZaloPayTransportException;
use App\Models\Booking;
use App\Models\Payment;
use App\Models\PaymentReviewEvent;
use App\Models\User;
use App\Services\ZaloPay\ZaloPayGateway;
use Illuminate\Support\Facades\DB;
use LogicException;

class PaymentReviewResolutionService
{
    public function __construct(
        private readonly ZaloPayGateway $gateway,
        private readonly ZaloPayConfig $config,
        private readonly VerifiedPaymentService $verifiedPayments,
    ) {}

    public function resolve(Payment $payment, User $actor): PaymentReviewResolutionResult
    {
        $event = DB::transaction(function () use ($payment, $actor): PaymentReviewEvent {
            Booking::query()->lockForUpdate()->findOrFail($payment->booking_id);
            $lockedPayment = Payment::query()->lockForUpdate()->findOrFail($payment->getKey());

            if ($lockedPayment->status !== Payment::STATUS_REVIEW) {
                throw new LogicException('Chỉ giao dịch đang chờ kiểm tra mới có thể được đối soát thủ công.');
            }

            if ($lockedPayment->provider !== 'zalopay') {
                throw new LogicException('Nhà cung cấp thanh toán này không hỗ trợ đối soát đơn hàng thủ công.');
            }

            return PaymentReviewEvent::query()->create([
                'payment_id' => $lockedPayment->id,
                'actor_user_id' => $actor->id,
                'action' => 'query_existing_provider_order',
                'previous_status' => $lockedPayment->status,
                'resulting_status' => $lockedPayment->status,
                'provider_result_category' => 'query_started',
                'provider_result_code' => null,
            ]);
        });

        try {
            $response = $this->gateway->query($payment->fresh());
        } catch (ZaloPayAuthenticationException) {
            return $this->finish(
                $event,
                'authentication_error',
                'authentication_rejected',
                'Provider authentication failed. Payment remains in review; verify merchant configuration before retrying.',
            );
        } catch (ZaloPayTransportException) {
            return $this->finish(
                $event,
                'uncertain',
                'transport_error',
                'Provider could not be reached. Payment remains in review; retry the existing-order query later.',
            );
        } catch (ZaloPayResponseException) {
            return $this->finish(
                $event,
                'uncertain',
                'invalid_response',
                'Provider returned an invalid response. Payment remains in review; escalate if repeated.',
            );
        }

        $payload = $response->payload;
        $code = $this->providerCode($payload);

        if ($payload['return_code'] !== 1) {
            $category = $payload['return_code'] === 3 ? 'uncertain' : 'not_successful';
            $message = $payload['return_code'] === 3
                ? 'Provider still reports an uncertain result. Payment remains in review; retry later.'
                : 'Provider does not report authoritative success. Payment remains in review; investigate late-payment or refund handling before permitting replacement.';

            return $this->finish($event, $category, $code, $message);
        }

        if (! is_int($payload['amount'] ?? null)) {
            return $this->finish(
                $event,
                'uncertain',
                $code,
                'Provider success omitted a valid amount. Payment remains in review; escalate for provider investigation.',
            );
        }

        $result = $this->verifiedPayments->verifyReview($payment, new VerifiedPaymentData(
            provider: 'zalopay',
            merchantReference: $payment->app_trans_id,
            amount: $payload['amount'],
            providerTransactionId: $this->normalizeTransactionId($payload['zp_trans_id'] ?? null),
            source: 'query',
            payloadHash: $response->hash,
            appId: $this->config->appId,
            serverTimeMs: is_int($payload['server_time'] ?? null) ? $payload['server_time'] : null,
        ));

        return $this->finish(
            $event,
            $result->accepted ? 'authoritative_success' : 'validation_rejected',
            $code,
            $result->accepted
                ? 'Authoritative provider success was verified and the valid booking was fulfilled.'
                : $result->message.' Payment remains in review; do not create a replacement until the discrepancy is resolved.',
        );
    }

    private function finish(
        PaymentReviewEvent $event,
        string $category,
        ?string $code,
        string $message,
    ): PaymentReviewResolutionResult {
        $status = DB::transaction(function () use ($event, $category, $code): string {
            $lockedEvent = PaymentReviewEvent::query()->lockForUpdate()->findOrFail($event->id);
            $lockedPayment = Payment::query()->lockForUpdate()->findOrFail($lockedEvent->payment_id);
            $lockedEvent->forceFill([
                'resulting_status' => $lockedPayment->status,
                'provider_result_category' => $category,
                'provider_result_code' => $code,
            ])->save();

            return $lockedPayment->status;
        });

        return new PaymentReviewResolutionResult($category, $status, $message);
    }

    /** @param array<string, mixed> $payload */
    private function providerCode(array $payload): string
    {
        $code = (string) $payload['return_code'];
        if (is_int($payload['sub_return_code'] ?? null)) {
            $code .= '/'.$payload['sub_return_code'];
        }

        return $code;
    }

    private function normalizeTransactionId(mixed $value): ?string
    {
        if (is_int($value)) {
            return (string) $value;
        }

        return is_string($value) && preg_match('/^[0-9]+$/D', $value) ? $value : null;
    }
}
