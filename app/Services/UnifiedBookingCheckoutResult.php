<?php

namespace App\Services;

use App\Models\Payment;

final readonly class UnifiedBookingCheckoutResult
{
    public function __construct(
        public BookingCheckoutResult $checkout,
        public ?Payment $payment,
        public ?string $orderUrl,
        public bool $paymentPendingReview = false,
    ) {}
}
