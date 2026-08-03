<?php

namespace App\Services\Payments;

use App\Models\Payment;

final readonly class PaymentInitiationResult
{
    public function __construct(
        public Payment $payment,
        public ?string $orderUrl,
        public bool $replayed,
    ) {}
}
