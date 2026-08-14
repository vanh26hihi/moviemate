<?php

namespace App\Services;

use App\Exceptions\PaymentInitiationException;
use App\Exceptions\PayOsResponseException;
use App\Exceptions\PayOsTransportException;
use App\Exceptions\VnpayResponseException;
use App\Exceptions\VnpayTransportException;
use App\Exceptions\ZaloPayResponseException;
use App\Exceptions\ZaloPayTransportException;
use App\Services\Payments\PaymentInitiationService;

class UnifiedBookingCheckoutService
{
    public function __construct(
        private readonly BookingCheckoutService $bookings,
        private readonly PaymentInitiationService $payments,
        private readonly ZeroPayableBookingSettlement $zeroPayable,
    ) {}

    public function confirm(array $draft, ?int $userId, ?string $provider = null, string $clientIp = '127.0.0.1'): UnifiedBookingCheckoutResult
    {
        $provider ??= (string) config('payment.driver', 'vnpay');
        // BookingCheckoutService commits the entire booking/seat/food aggregate before
        // this service performs any network I/O through PaymentInitiationService.
        $checkout = $this->bookings->createPendingBooking(
            (int) $draft['showtime_id'],
            $draft['seat_ids'],
            $userId,
            $draft['customer_email'],
            $draft['checkout_token'],
            $draft['food_items'],
            promotionCode: $draft['promotion_code'] ?? null,
        );

        if ((int) $checkout->booking->total_amount === 0) {
            $payment = $this->zeroPayable->settle($checkout->booking);

            return new UnifiedBookingCheckoutResult($checkout, $payment, null);
        }

        $this->payments->assertAvailable($provider);

        try {
            $payment = $this->payments->initiate($checkout->booking, $provider, $clientIp);

            return new UnifiedBookingCheckoutResult(
                $checkout,
                $payment->payment,
                $payment->orderUrl,
            );
        } catch (PaymentInitiationException|PayOsResponseException|PayOsTransportException|ZaloPayResponseException|ZaloPayTransportException|VnpayResponseException|VnpayTransportException $exception) {
            $payment = $checkout->booking->payments()->latest('id')->first();
            if ($payment === null) {
                throw new PaymentInitiationException(
                    'Payment initiation failed before an attempt was created.',
                    previous: $exception,
                );
            }

            return new UnifiedBookingCheckoutResult(
                $checkout,
                $payment,
                null,
                true,
            );
        }
    }
}
