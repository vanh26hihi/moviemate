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
    ) {}

    public function confirm(array $draft, ?int $userId, ?string $provider = null, string $clientIp = '127.0.0.1'): UnifiedBookingCheckoutResult
    {
        $provider ??= (string) config('payment.driver', 'vnpay');
        $this->payments->assertAvailable($provider);
        // BookingCheckoutService commits the entire booking/seat/food aggregate before
        // this service performs any network I/O through PaymentInitiationService.
        $checkout = $this->bookings->createPendingBooking(
            (int) $draft['showtime_id'],
            $draft['seat_ids'],
            $userId,
            $draft['customer_email'],
            $draft['checkout_token'],
            $draft['food_items'],
            discountCodes: $draft['discount_codes'] ?? [],
            pointsToUse: (int) ($draft['points_to_use'] ?? 0),
        );

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
