<?php

namespace App\Services;

use App\Exceptions\PaymentInitiationException;
use App\Exceptions\ZaloPayResponseException;
use App\Exceptions\ZaloPayTransportException;
use App\Services\Payments\PaymentInitiationService;

class UnifiedBookingCheckoutService
{
    public function __construct(
        private readonly BookingCheckoutService $bookings,
        private readonly PaymentInitiationService $payments,
    ) {}

    public function confirm(array $draft, ?int $userId): UnifiedBookingCheckoutResult
    {
        // BookingCheckoutService commits the entire booking/seat/food aggregate before
        // this service performs any network I/O through PaymentInitiationService.
        $checkout = $this->bookings->createPendingBooking(
            (int) $draft['showtime_id'],
            $draft['seat_ids'],
            $userId,
            $draft['customer_email'],
            $draft['checkout_token'],
            $draft['food_items'],
        );

        try {
            $payment = $this->payments->initiate($checkout->booking);

            return new UnifiedBookingCheckoutResult(
                $checkout,
                $payment->payment,
                $payment->orderUrl,
            );
        } catch (PaymentInitiationException|ZaloPayResponseException|ZaloPayTransportException) {
            return new UnifiedBookingCheckoutResult(
                $checkout,
                $checkout->booking->payments()->latest('id')->first(),
                null,
                true,
            );
        }
    }
}
