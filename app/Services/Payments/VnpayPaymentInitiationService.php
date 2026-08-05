<?php

namespace App\Services\Payments;

use App\Domain\Money\VndAmount;
use App\Domain\Payments\VnpayConfig;
use App\Exceptions\PaymentInitiationException;
use App\Models\Booking;
use App\Models\BookingSeat;
use App\Models\Order;
use App\Models\Payment;
use App\Services\Vnpay\VnpayPaymentUrlBuilder;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

class VnpayPaymentInitiationService
{
    public function __construct(
        private readonly VnpayConfig $config,
        private readonly PaymentReturnTokenService $returnTokens,
        private readonly VnpayPaymentUrlBuilder $urls,
    ) {}

    public function initiate(Booking $booking, string $clientIp): PaymentInitiationResult
    {
        try {
            [$payment, $replayed] = DB::transaction(function () use ($booking): array {
                $lockedBooking = Booking::query()->lockForUpdate()->findOrFail($booking->getKey());
                $this->assertPayable($lockedBooking);

                $activeAttempt = Payment::query()
                    ->where('booking_id', $lockedBooking->id)
                    ->where('provider', 'vnpay')
                    ->whereIn('status', Payment::UNSAFE_RETRY_STATUSES)
                    ->latest('id')
                    ->lockForUpdate()
                    ->first();
                if ($activeAttempt) {
                    if ($activeAttempt->status === Payment::STATUS_REVIEW) {
                        throw new PaymentInitiationException(
                            'The existing VNPAY attempt requires review and cannot be replaced.',
                        );
                    }

                    if (! $activeAttempt->expires_at || $activeAttempt->expires_at->isPast()) {
                        $activeAttempt->forceFill([
                            'status' => Payment::STATUS_EXPIRED,
                            'failed_at' => now(),
                            'failure_reason' => 'payment_attempt_expired',
                        ])->save();
                    } else {
                        return [$activeAttempt, true];
                    }
                }

                $amount = $this->authoritativeAmount($lockedBooking);
                $createdAt = now();
                $expiresAt = $createdAt->copy()->addMinutes($this->config->paymentTtlMinutes);
                if ($lockedBooking->expires_at->lt($expiresAt)) {
                    $expiresAt = $lockedBooking->expires_at->copy();
                }
                if ($expiresAt->isPast()) {
                    throw new PaymentInitiationException('Booking is no longer payable.');
                }

                $payment = Payment::createForProvider('vnpay', [
                    'booking_id' => $lockedBooking->id,
                    'payment_method' => 'vnpay',
                    'order_code' => $this->transactionReference(),
                    'amount' => $amount,
                    'currency' => 'VND',
                    'status' => Payment::STATUS_PENDING,
                    'description' => 'MovieMate booking '.$lockedBooking->booking_code,
                    'expires_at' => $expiresAt,
                    'reconcile_until' => $expiresAt->copy()->addHours(
                        max(1, (int) config('payment.reconciliation_grace_hours', 24)),
                    ),
                    'provider_transaction_created_at' => $createdAt,
                ]);

                return [$payment, false];
            });
        } catch (UniqueConstraintViolationException $exception) {
            $active = Payment::query()
                ->where('booking_id', $booking->getKey())
                ->where('provider', 'vnpay')
                ->whereIn('status', Payment::UNSAFE_RETRY_STATUSES)
                ->latest('id')
                ->first();
            if (! $active) {
                throw new PaymentInitiationException('Unable to allocate a unique VNPAY transaction reference.', previous: $exception);
            }
            $payment = $active;
            $replayed = true;
        }

        $payment->loadMissing('booking');
        $state = $this->returnTokens->issue($payment);
        $returnUrl = $this->config->returnUrl($state);
        try {
            $url = $this->urls->build($payment, $returnUrl, $clientIp);
        } catch (PaymentInitiationException $exception) {
            Log::warning('VNPAY PAY request validation failed before redirect.', [
                'payment_id' => $payment->id,
                'booking_id' => $payment->booking_id,
                'provider' => 'vnpay',
                'tmn_code' => $this->maskedTmnCode(),
                'txn_ref' => $payment->order_code,
                'amount' => $payment->amount,
                'endpoint_host' => parse_url($this->config->paymentUrl, PHP_URL_HOST),
                'return_host' => parse_url($returnUrl, PHP_URL_HOST),
                'client_ip_family' => filter_var($clientIp, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) !== false
                    ? 'IPv4'
                    : (filter_var($clientIp, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6) !== false ? 'IPv6' : 'invalid'),
                'bank_code' => $this->config->bankCode === '' ? 'omitted' : 'configured',
                'request_contract_valid' => false,
                'reason' => $exception->getMessage(),
            ]);

            throw $exception;
        }

        return new PaymentInitiationResult($payment, $url, $replayed);
    }

    private function assertPayable(Booking $booking): void
    {
        if ($booking->payment_status === 'paid' || $booking->booking_status === 'paid') {
            throw new PaymentInitiationException('Booking is already paid.');
        }
        if ($booking->booking_status !== 'pending_payment'
            || ! $booking->expires_at
            || $booking->expires_at->isPast()) {
            throw new PaymentInitiationException('Booking is no longer payable.');
        }

        $seats = BookingSeat::query()
            ->where('booking_id', $booking->id)
            ->orderBy('id')
            ->lockForUpdate()
            ->get();
        if ($seats->isEmpty() || $seats->contains(
            fn (BookingSeat $seat): bool => $seat->showtime_id !== $booking->showtime_id
                || $seat->active_lock_key !== BookingSeat::ACTIVE_LOCK_KEY,
        )) {
            throw new PaymentInitiationException('Booking no longer owns its reserved seats.');
        }
    }

    private function authoritativeAmount(Booking $booking): int
    {
        try {
            $bookingTotal = VndAmount::fromDatabase($booking->getRawOriginal('total_amount'));
            $seatSubtotal = VndAmount::fromDatabase($booking->getRawOriginal('seat_subtotal'));
            $foodSubtotal = VndAmount::fromDatabase($booking->getRawOriginal('food_subtotal'));
            $seatRows = BookingSeat::query()->where('booking_id', $booking->id)->lockForUpdate()->get();
            $seatRowsTotal = $seatRows->reduce(
                fn (VndAmount $total, BookingSeat $seat): VndAmount => $total->add(
                    VndAmount::fromDatabase($seat->getRawOriginal('price')),
                ),
                VndAmount::zero(),
            );
            $order = Order::query()->where('booking_id', $booking->id)->lockForUpdate()->first();
            $orderSubtotal = $order
                ? VndAmount::fromDatabase($order->getRawOriginal('subtotal'))
                : VndAmount::zero();
        } catch (Throwable $exception) {
            throw new PaymentInitiationException('Stored booking pricing is invalid.', previous: $exception);
        }

        if (! $seatRowsTotal->equals($seatSubtotal)
            || ! $orderSubtotal->equals($foodSubtotal)
            || ! $seatSubtotal->add($foodSubtotal)->equals($bookingTotal)
            || $bookingTotal->value() <= 0) {
            throw new PaymentInitiationException('Stored booking pricing failed server verification.');
        }

        return $bookingTotal->value();
    }

    private function transactionReference(): string
    {
        return 'MM'.now(VnpayConfig::TIMEZONE)->format('ymdHis').strtoupper(Str::random(18));
    }

    private function maskedTmnCode(): string
    {
        return substr($this->config->tmnCode, 0, 2).'****'.substr($this->config->tmnCode, -2);
    }
}
