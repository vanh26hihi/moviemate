<?php

namespace App\Services\Payments;

use App\Domain\Money\VndAmount;
use App\Domain\Payments\PayOsConfig;
use App\Exceptions\PaymentInitiationException;
use App\Exceptions\PayOsResponseException;
use App\Exceptions\PayOsTransportException;
use App\Models\Booking;
use App\Models\BookingSeat;
use App\Models\Order;
use App\Models\Payment;
use App\Services\PayOs\PayOsGateway;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Throwable;

final class PayOsPaymentInitiationService
{
    public function __construct(
        private readonly PayOsConfig $config,
        private readonly PaymentReturnTokenService $returnTokens,
        private readonly PayOsGateway $gateway,
        private readonly PayOsPaymentReconciliationService $reconciliation,
    ) {}

    public function initiate(Booking $booking): PaymentInitiationResult
    {
        try {
            [$payment, $replayed] = DB::transaction(function () use ($booking): array {
                $lockedBooking = Booking::query()->lockForUpdate()->findOrFail($booking->id);
                $amount = $this->authoritativeAmount($lockedBooking);

                $active = Payment::query()
                    ->where('booking_id', $lockedBooking->id)
                    ->whereIn('status', Payment::UNSAFE_RETRY_STATUSES)
                    ->latest('id')->lockForUpdate()->first();
                if ($active) {
                    if ($active->provider !== 'payos') {
                        throw new PaymentInitiationException('Another payment provider attempt is still unresolved.');
                    }

                    return [$active, true];
                }

                $expiresAt = $lockedBooking->expires_at?->copy();
                if (! $expiresAt || $expiresAt->getTimestamp() <= now()->getTimestamp() + 59) {
                    throw new PaymentInitiationException('Booking does not have enough safe payment time remaining.');
                }

                $payment = Payment::createForProvider('payos', [
                    'booking_id' => $lockedBooking->id,
                    'payment_method' => 'payos',
                    'amount' => $amount,
                    'currency' => 'VND',
                    'status' => Payment::STATUS_PENDING,
                    'expires_at' => $expiresAt,
                    'reconcile_until' => $expiresAt->copy()->addHours(
                        max(1, (int) config('payment.reconciliation_grace_hours', 24)),
                    ),
                    'provider_transaction_created_at' => now(),
                ]);
                $orderCode = (string) $payment->id;
                $description = 'MM'.strtoupper(base_convert($orderCode, 10, 36));
                if (strlen($description) > 9) {
                    throw new PaymentInitiationException('Unable to allocate a short payOS description.');
                }
                $payment->forceFill([
                    'order_code' => $orderCode,
                    'description' => $description,
                ])->save();

                return [$payment, false];
            });
        } catch (UniqueConstraintViolationException $exception) {
            $active = Payment::query()
                ->where('booking_id', $booking->id)
                ->whereIn('status', Payment::UNSAFE_RETRY_STATUSES)
                ->latest('id')->first();
            if (! $active || $active->provider !== 'payos') {
                throw new PaymentInitiationException('Another payment attempt became active.', previous: $exception);
            }
            $payment = $active;
            $replayed = true;
        }

        if ($replayed && $this->validStoredCheckoutUrl($payment)) {
            return new PaymentInitiationResult($payment, $payment->payment_url, true);
        }
        if ($replayed) {
            if ($payment->status === Payment::STATUS_REVIEW) {
                throw new PaymentInitiationException('The existing payOS attempt requires review.');
            }
            $this->reconciliation->reconcile($payment);
            $payment->refresh();

            return new PaymentInitiationResult(
                $payment,
                $this->validStoredCheckoutUrl($payment) ? $payment->payment_url : null,
                true,
            );
        }

        $state = $this->returnTokens->issue($payment);
        $returnUrl = route('payments.payos.return', ['state' => $state]);
        $cancelUrl = route('payments.payos.cancel', ['state' => $state]);
        $this->config->assertMerchantUrl($returnUrl);
        $this->config->assertMerchantUrl($cancelUrl);

        try {
            $response = $this->gateway->create($payment, $returnUrl, $cancelUrl);
        } catch (PayOsTransportException|PayOsResponseException $exception) {
            $payment->forceFill([
                'status' => Payment::STATUS_UNRESOLVED,
                'failure_reason' => $exception instanceof PayOsTransportException
                    ? 'create_transport_unknown' : 'create_response_unknown',
                'failed_at' => null,
            ])->save();
            throw $exception;
        }

        $data = $response->data;
        $checkoutUrl = $data['checkoutUrl'] ?? null;
        $paymentLinkId = $data['paymentLinkId'] ?? null;
        $status = $data['status'] ?? null;
        if (($data['orderCode'] ?? null) !== (int) $payment->order_code
            || ($data['amount'] ?? null) !== $payment->amount
            || ($data['currency'] ?? 'VND') !== 'VND'
            || ! is_string($paymentLinkId)
            || preg_match('/^[A-Za-z0-9_-]{8,100}$/D', $paymentLinkId) !== 1
            || ! in_array($status, ['PENDING', 'PROCESSING'], true)
            || ! is_string($checkoutUrl)
            || ! $this->config->validCheckoutUrl($checkoutUrl)) {
            $payment->forceFill([
                'status' => Payment::STATUS_REVIEW,
                'failure_reason' => 'create_response_identity_mismatch',
                'failed_at' => now(),
                'create_response_hash' => $response->hash,
            ])->save();
            throw new PayOsResponseException('payOS payment link identity was rejected.');
        }

        $payment->forceFill([
            'transaction_code' => $paymentLinkId,
            'transaction_status' => $status,
            'response_code' => $response->code,
            'status' => $status === 'PROCESSING' ? Payment::STATUS_PROCESSING : Payment::STATUS_PENDING,
            'payment_url' => $checkoutUrl,
            'create_response_hash' => $response->hash,
            'failure_reason' => null,
        ])->save();

        return new PaymentInitiationResult($payment->fresh(), $checkoutUrl, false);
    }

    private function authoritativeAmount(Booking $booking): int
    {
        if ($booking->payment_status === 'paid' || $booking->booking_status === 'paid') {
            throw new PaymentInitiationException('Booking is already paid.');
        }
        if ($booking->booking_status !== 'pending_payment' || ! $booking->expires_at || $booking->expires_at->isPast()) {
            throw new PaymentInitiationException('Booking is no longer payable.');
        }

        $seatRows = BookingSeat::query()->where('booking_id', $booking->id)
            ->orderBy('id')->lockForUpdate()->get();
        if ($seatRows->isEmpty() || $seatRows->contains(
            fn (BookingSeat $seat): bool => $seat->showtime_id !== $booking->showtime_id
                || $seat->active_lock_key !== BookingSeat::ACTIVE_LOCK_KEY,
        )) {
            throw new PaymentInitiationException('Booking no longer owns its reserved seats.');
        }

        try {
            $total = VndAmount::fromDatabase($booking->getRawOriginal('total_amount'));
            $seatSubtotal = VndAmount::fromDatabase($booking->getRawOriginal('seat_subtotal'));
            $foodSubtotal = VndAmount::fromDatabase($booking->getRawOriginal('food_subtotal'));
            $seatTotal = $seatRows->reduce(
                fn (VndAmount $sum, BookingSeat $seat): VndAmount => $sum->add(
                    VndAmount::fromDatabase($seat->getRawOriginal('price')),
                ),
                VndAmount::zero(),
            );
            $order = Order::query()->where('booking_id', $booking->id)->lockForUpdate()->first();
            $orderTotal = $order
                ? VndAmount::fromDatabase($order->getRawOriginal('subtotal'))
                : VndAmount::zero();
        } catch (Throwable $exception) {
            throw new PaymentInitiationException('Stored booking pricing is invalid.', previous: $exception);
        }

        if (! $seatTotal->equals($seatSubtotal)
            || ! $orderTotal->equals($foodSubtotal)
            || ! $seatSubtotal->add($foodSubtotal)->equals($total)
            || $total->value() <= 0) {
            throw new PaymentInitiationException('Stored booking pricing failed server verification.');
        }

        return $total->value();
    }

    private function validStoredCheckoutUrl(Payment $payment): bool
    {
        return is_string($payment->payment_url)
            && $this->config->validCheckoutUrl($payment->payment_url)
            && $payment->expires_at?->isFuture() === true;
    }
}
