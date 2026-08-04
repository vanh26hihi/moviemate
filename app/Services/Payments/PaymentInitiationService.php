<?php

namespace App\Services\Payments;

use App\Domain\Payments\AppTransIdGenerator;
use App\Domain\Payments\VndAmount;
use App\Domain\Payments\ZaloPayConfig;
use App\Exceptions\PaymentInitiationException;
use App\Exceptions\ZaloPayResponseException;
use App\Exceptions\ZaloPayTransportException;
use App\Models\Booking;
use App\Models\Payment;
use App\Services\ZaloPay\ZaloPayGateway;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

class PaymentInitiationService
{
    public function __construct(
        private readonly ZaloPayConfig $config,
        private readonly AppTransIdGenerator $transactionIds,
        private readonly PaymentReturnTokenService $returnTokens,
        private readonly ZaloPayGateway $gateway,
        private readonly PaymentReconciliationService $reconciliation,
    ) {}

    public function initiate(Booking $booking): PaymentInitiationResult
    {
        [$payment, $replayed] = DB::transaction(function () use ($booking): array {
            $lockedBooking = Booking::query()->lockForUpdate()->findOrFail($booking->getKey());

            if ($lockedBooking->payment_status === 'paid' || $lockedBooking->booking_status === 'paid') {
                throw new PaymentInitiationException('Booking is already paid.');
            }

            if ($lockedBooking->booking_status !== 'pending_payment'
                || ! $lockedBooking->expires_at
                || $lockedBooking->expires_at->isPast()) {
                throw new PaymentInitiationException('Booking is no longer payable.');
            }

            $activeAttempt = Payment::query()
                ->where('booking_id', $lockedBooking->id)
                ->where('provider', 'zalopay')
                ->whereIn('status', Payment::UNSAFE_RETRY_STATUSES)
                ->latest('id')
                ->lockForUpdate()
                ->first();

            if ($activeAttempt) {
                return [$activeAttempt, true];
            }

            try {
                $amount = VndAmount::fromDecimal($lockedBooking->getRawOriginal('total_amount'));
            } catch (InvalidArgumentException $exception) {
                throw new PaymentInitiationException($exception->getMessage(), previous: $exception);
            }

            $now = now();
            $attemptExpiry = $now->copy()->addSeconds($this->config->expireDurationSeconds);
            if ($lockedBooking->expires_at->lt($attemptExpiry)) {
                $attemptExpiry = $lockedBooking->expires_at->copy();
            }

            $payment = Payment::createForProvider('zalopay', [
                'booking_id' => $lockedBooking->id,
                'payment_method' => 'zalopay',
                'app_id' => $this->config->appId,
                'app_trans_id' => $this->transactionIds->generate(),
                'app_user' => 'moviemate-'.Str::lower(Str::random(16)),
                'app_time_ms' => (int) floor(microtime(true) * 1000),
                'amount' => $amount,
                'currency' => 'VND',
                'status' => Payment::STATUS_PENDING,
                'description' => 'MovieMate booking '.$lockedBooking->booking_code,
                'expires_at' => $attemptExpiry,
                'reconcile_until' => $attemptExpiry->copy()->addHours(
                    max(1, (int) config('payment.reconciliation_grace_hours', 24)),
                ),
            ]);

            $payment->forceFill(['order_code' => $payment->app_trans_id])->save();

            return [$payment, false];
        });

        if ($replayed
            && in_array($payment->status, Payment::RECONCILABLE_STATUSES, true)
            && $payment->expires_at?->isFuture()
            && is_string($payment->order_url)
            && $payment->order_url !== '') {
            return new PaymentInitiationResult($payment, $payment->order_url, true);
        }

        if ($replayed) {
            if (! in_array($payment->status, Payment::RECONCILABLE_STATUSES, true)) {
                throw new PaymentInitiationException(
                    'The existing payment attempt requires manual review and cannot be replaced.',
                );
            }

            $this->reconciliation->reconcile($payment);

            $payment->refresh();
            if ($payment->expires_at?->isFuture()
                && is_string($payment->order_url)
                && $payment->order_url !== '') {
                return new PaymentInitiationResult($payment, $payment->order_url, true);
            }

            throw new PaymentInitiationException('The existing payment attempt is being reconciled.');
        }

        $returnUrl = $this->returnUrl($payment);

        try {
            $response = $this->gateway->create($payment, $returnUrl);
        } catch (ZaloPayTransportException|ZaloPayResponseException $exception) {
            $payment->forceFill([
                'status' => Payment::STATUS_UNRESOLVED,
                'failed_at' => null,
                'failure_reason' => $exception instanceof ZaloPayTransportException
                    ? 'create_transport_unknown' : 'create_response_unknown',
            ])->save();

            throw $exception;
        }

        $payload = $response->payload;
        $this->storeCreateResponse($payment, $payload, $response->hash);

        if ($payload['return_code'] === 1) {
            $orderUrl = $payload['order_url'] ?? null;

            if (! is_string($orderUrl) || filter_var($orderUrl, FILTER_VALIDATE_URL) === false) {
                $payment->forceFill([
                    'status' => Payment::STATUS_UNRESOLVED,
                    'failed_at' => null,
                    'failure_reason' => 'create_missing_order_url',
                ])->save();
                throw new PaymentInitiationException('ZaloPay did not provide a valid order URL.');
            }

            $payment->forceFill(['order_url' => $orderUrl, 'payment_url' => $orderUrl])->save();

            return new PaymentInitiationResult($payment->fresh(), $orderUrl, false);
        }

        if (($payload['sub_return_code'] ?? null) === -68) {
            $this->reconciliation->reconcile($payment);

            return new PaymentInitiationResult($payment->fresh(), $payment->fresh()->order_url, false);
        }

        if ($payload['return_code'] !== 2) {
            $payment->forceFill([
                'status' => Payment::STATUS_UNRESOLVED,
                'failed_at' => null,
                'failure_reason' => 'create_response_unknown',
            ])->save();

            throw new PaymentInitiationException('ZaloPay returned an uncertain create order result.');
        }

        $payment->forceFill([
            'status' => Payment::STATUS_FAILED,
            'failed_at' => now(),
            'failure_reason' => 'create_rejected',
        ])->save();

        throw new PaymentInitiationException('ZaloPay rejected the create order request.');
    }

    private function returnUrl(Payment $payment): string
    {
        $separator = str_contains($this->config->redirectUrl, '?') ? '&' : '?';

        return $this->config->redirectUrl.$separator.http_build_query([
            'state' => $this->returnTokens->issue($payment),
        ], '', '&', PHP_QUERY_RFC3986);
    }

    private function storeCreateResponse(Payment $payment, array $payload, string $hash): void
    {
        $payment->forceFill([
            'provider_return_code' => $payload['return_code'],
            'provider_sub_return_code' => is_int($payload['sub_return_code'] ?? null)
                ? $payload['sub_return_code'] : null,
            'provider_return_message' => $this->message($payload['return_message'] ?? null),
            'provider_sub_return_message' => $this->message($payload['sub_return_message'] ?? null),
            'zp_trans_token' => $this->string($payload['zp_trans_token'] ?? null),
            'order_token' => $this->string($payload['order_token'] ?? null),
            'qr_code' => $this->string($payload['qr_code'] ?? null),
            'create_response_hash' => $hash,
        ])->save();
    }

    private function message(mixed $value): ?string
    {
        return is_string($value) ? mb_substr($value, 0, 255) : null;
    }

    private function string(mixed $value): ?string
    {
        return is_string($value) && $value !== '' ? $value : null;
    }
}
