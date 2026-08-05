<?php

namespace App\Domain\Payments;

use Carbon\CarbonInterface;

final readonly class VerifiedPaymentData
{
    public function __construct(
        public string $provider,
        public string $merchantReference,
        public int $amount,
        public ?string $providerTransactionId,
        public string $source,
        public ?string $payloadHash = null,
        public ?int $appId = null,
        public ?int $serverTimeMs = null,
        public ?string $responseCode = null,
        public ?string $transactionStatus = null,
        public ?string $bankCode = null,
        public ?string $cardType = null,
        public ?CarbonInterface $providerPaidAt = null,
    ) {}
}
