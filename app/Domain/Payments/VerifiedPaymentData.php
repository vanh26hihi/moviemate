<?php

namespace App\Domain\Payments;

final readonly class VerifiedPaymentData
{
    public function __construct(
        public int $appId,
        public string $appTransId,
        public int $amount,
        public ?string $zpTransId,
        public ?int $serverTimeMs,
        public string $source,
        public ?string $payloadHash = null,
    ) {}
}
