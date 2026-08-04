<?php

namespace App\Domain\Payments;

final readonly class PaymentReviewResolutionResult
{
    public function __construct(
        public string $category,
        public string $status,
        public string $message,
    ) {}
}
