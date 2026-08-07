<?php

namespace App\Services\Payments;

use App\Domain\Payments\PayOsConfig;
use App\Domain\Payments\VnpayConfig;
use App\Exceptions\PaymentInitiationException;
use App\Models\Booking;
use App\Models\BookingSeat;
use App\Models\Payment;
use App\Services\BookingExpirationService;
use App\Services\Vnpay\VnpayPaymentUrlBuilder;
use Illuminate\Support\Facades\DB;

final class PaymentResumeService
{
    public function __construct(
        private readonly BookingExpirationService $expiration,
        private readonly PaymentReturnTokenService $returnTokens,
    ) {}

    public function resume(Booking $booking, string $clientIp): PaymentInitiationResult
    {
        $this->expiration->expire($booking->id);

        return DB::transaction(function () use ($booking, $clientIp): PaymentInitiationResult {
            $lockedBooking = Booking::query()->lockForUpdate()->findOrFail($booking->id);
            $payments = Payment::query()
                ->where('booking_id', $lockedBooking->id)
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

            if ($lockedBooking->booking_status !== 'pending_payment'
                || $lockedBooking->payment_status !== 'unpaid'
                || ! $lockedBooking->expires_at
                || $lockedBooking->expires_at->isPast()
                || $payments->contains(fn (Payment $payment): bool => $payment->hasAuthoritativeSuccessEvidence())) {
                throw new PaymentInitiationException('Booking is no longer resumable.');
            }

            $activeAttempts = $payments
                ->filter(fn (Payment $payment): bool => in_array($payment->status, Payment::UNSAFE_RETRY_STATUSES, true))
                ->sortByDesc('id')
                ->values();
            if ($activeAttempts->count() !== 1) {
                throw new PaymentInitiationException('Booking does not have one resumable payment attempt.');
            }

            /** @var Payment $payment */
            $payment = $activeAttempts->first();
            if ($payment->status !== Payment::STATUS_PENDING
                || ! in_array($payment->provider, Payment::SUPPORTED_PROVIDERS, true)
                || ! $payment->expires_at
                || $payment->expires_at->isPast()) {
                throw new PaymentInitiationException('The current payment attempt is protected and cannot be resumed.');
            }

            $seats = BookingSeat::query()
                ->where('booking_id', $lockedBooking->id)
                ->orderBy('id')
                ->lockForUpdate()
                ->get();
            if ($seats->isEmpty() || $seats->contains(
                fn (BookingSeat $seat): bool => $seat->showtime_id !== $lockedBooking->showtime_id
                    || $seat->active_lock_key !== BookingSeat::ACTIVE_LOCK_KEY,
            )) {
                throw new PaymentInitiationException('Booking no longer owns its reserved seats.');
            }

            $payment->setRelation('booking', $lockedBooking);
            $url = match ($payment->provider) {
                'vnpay' => $this->vnpayUrl($payment, $clientIp),
                'payos' => $this->payOsUrl($payment),
                'zalopay' => $this->zaloPayUrl($payment),
            };

            return new PaymentInitiationResult($payment, $url, true);
        });
    }

    private function vnpayUrl(Payment $payment, string $clientIp): string
    {
        $config = app(VnpayConfig::class);
        $returnUrl = $config->returnUrl($this->returnTokens->issue($payment));

        return app(VnpayPaymentUrlBuilder::class)->build($payment, $returnUrl, $clientIp);
    }

    private function payOsUrl(Payment $payment): string
    {
        if (! is_string($payment->payment_url)
            || ! app(PayOsConfig::class)->validCheckoutUrl($payment->payment_url)) {
            throw new PaymentInitiationException('The existing payOS checkout link is unavailable.');
        }

        return $payment->payment_url;
    }

    private function zaloPayUrl(Payment $payment): string
    {
        $url = is_string($payment->order_url) ? $payment->order_url : '';
        $parts = parse_url($url);
        if (! is_array($parts)
            || strtolower((string) ($parts['scheme'] ?? '')) !== 'https'
            || ! is_string($parts['host'] ?? null)
            || isset($parts['user'], $parts['pass'])) {
            throw new PaymentInitiationException('The existing ZaloPay checkout URL is unavailable.');
        }

        return $url;
    }
}
