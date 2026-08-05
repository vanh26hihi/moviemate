<?php

namespace App\Domain\Payments;

final readonly class PaymentVerificationResult
{
    public function __construct(
        public bool $accepted,
        public bool $transitioned,
        public string $message,
    ) {}

    public static function transitioned(): self
    {
        return new self(true, true, 'Payment verified.');
    }

    public static function duplicate(): self
    {
        return new self(true, false, 'Payment was already verified.');
    }

    public static function rejected(string $message): self
    {
        return new self(false, false, $message);
    }
}
