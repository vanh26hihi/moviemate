<?php

namespace App\Services\Payments;

use App\Domain\Payments\PaymentReviewResolutionResult;
use App\Domain\Payments\VerifiedPaymentData;
use App\Domain\Payments\ZaloPayConfig;
use App\Exceptions\PaymentConfigurationException;
use App\Exceptions\PayOsResponseException;
use App\Exceptions\PayOsTransportException;
use App\Exceptions\VnpayResponseException;
use App\Exceptions\VnpayTransportException;
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
        private readonly VnpayQueryService $vnpayQueries,
    ) {}

    public function resolve(Payment $payment, User $actor): PaymentReviewResolutionResult
    {
        $event = DB::transaction(function () use ($payment, $actor): PaymentReviewEvent {
            Booking::query()->lockForUpdate()->findOrFail($payment->booking_id);
            $lockedPayment = Payment::query()->lockForUpdate()->findOrFail($payment->getKey());

            if ($lockedPayment->status !== Payment::STATUS_REVIEW) {
                throw new LogicException('Chỉ giao dịch đang chờ kiểm tra mới có thể được đối soát thủ công.');
            }

            if (! in_array($lockedPayment->provider, Payment::SUPPORTED_PROVIDERS, true)) {
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

        if ($payment->provider === 'vnpay') {
            return $this->resolveVnpay($payment, $event);
        }
        if ($payment->provider === 'payos') {
            return $this->resolvePayOs($payment, $event);
        }

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

    private function resolveVnpay(
        Payment $payment,
        PaymentReviewEvent $event,
    ): PaymentReviewResolutionResult {
        try {
            $status = $this->vnpayQueries->reconcileReview($payment);
        } catch (VnpayTransportException) {
            return $this->finish(
                $event,
                'uncertain',
                'transport_error',
                'Chưa kết nối được VNPAY. Giao dịch vẫn ở trạng thái cần kiểm tra và có thể thử lại sau.',
            );
        } catch (VnpayResponseException) {
            return $this->finish(
                $event,
                'uncertain',
                'invalid_response',
                'VNPAY trả về phản hồi không hợp lệ. Giao dịch vẫn ở trạng thái cần kiểm tra.',
            );
        }

        $fresh = $payment->fresh();
        $category = match (true) {
            $status === Payment::STATUS_SUCCESS => 'authoritative_success',
            $fresh->failure_reason === 'query_authentication_error' => 'authentication_error',
            in_array($fresh->failure_reason, [
                'amount_mismatch',
                'query_amount_invalid',
                'query_amount_mismatch',
                'query_identity_mismatch',
                'provider_transaction_id_mismatch',
                'duplicate_provider_transaction_id',
                'late_payment_after_expiration',
                'seat_ownership_lost',
                'booking_not_payable',
            ], true) => 'validation_rejected',
            in_array($fresh->failure_reason, ['query_failed', 'vnpay_terminal_failed'], true) => 'not_successful',
            default => 'uncertain',
        };
        $code = collect([$fresh->response_code, $fresh->transaction_status])
            ->filter(fn ($value): bool => is_string($value) && $value !== '')
            ->join('/');

        return $this->finish(
            $event,
            $category,
            $code === '' ? null : $code,
            $status === Payment::STATUS_SUCCESS
                ? 'VNPAY đã xác nhận giao dịch hợp lệ và đơn đặt vé đã được hoàn tất an toàn.'
                : 'Kết quả VNPAY chưa đủ điều kiện hoàn tất đơn. Giao dịch vẫn được giữ để kiểm tra.',
        );
    }

    private function resolvePayOs(
        Payment $payment,
        PaymentReviewEvent $event,
    ): PaymentReviewResolutionResult {
        try {
            $status = app(PayOsPaymentReconciliationService::class)->reconcileReview($payment);
        } catch (PaymentConfigurationException) {
            return $this->finish(
                $event,
                'authentication_error',
                'configuration_invalid',
                'Cáº¥u hÃ¬nh payOS khÃ´ng há»£p lá»‡. Giao dá»‹ch váº«n á»Ÿ tráº¡ng thÃ¡i cáº§n kiá»ƒm tra; hÃ£y xÃ¡c minh cáº¥u hÃ¬nh nhÃ  cung cáº¥p trÆ°á»›c khi thá»­ láº¡i.',
            );
        } catch (PayOsTransportException) {
            return $this->finish(
                $event,
                'uncertain',
                'transport_error',
                'Chưa kết nối được payOS. Giao dịch vẫn ở trạng thái cần kiểm tra và có thể thử lại sau.',
            );
        } catch (PayOsResponseException) {
            return $this->finish(
                $event,
                'uncertain',
                'invalid_response',
                'payOS trả về phản hồi không hợp lệ. Giao dịch vẫn ở trạng thái cần kiểm tra.',
            );
        }

        $fresh = $payment->fresh();
        $category = match (true) {
            $status === Payment::STATUS_SUCCESS => 'authoritative_success',
            in_array($fresh->failure_reason, [
                'amount_mismatch',
                'payos_amount_paid_mismatch',
                'payos_currency_mismatch',
                'payos_order_code_mismatch',
                'payos_payment_link_mismatch',
                'provider_transaction_id_mismatch',
                'duplicate_provider_transaction_id',
                'late_payment_after_expiration',
                'seat_ownership_lost',
                'booking_not_payable',
            ], true) => 'validation_rejected',
            $fresh->failure_reason === 'payos_cancelled' => 'not_successful',
            default => 'uncertain',
        };

        return $this->finish(
            $event,
            $category,
            is_string($fresh->response_code) ? $fresh->response_code : null,
            $status === Payment::STATUS_SUCCESS
                ? 'payOS đã xác nhận giao dịch hợp lệ và đơn đặt vé đã được hoàn tất an toàn.'
                : 'Kết quả payOS chưa đủ điều kiện hoàn tất đơn. Giao dịch được giữ để kiểm tra.',
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
