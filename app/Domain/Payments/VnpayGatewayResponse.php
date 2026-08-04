<?php

namespace App\Domain\Payments;

final readonly class VnpayGatewayResponse
{
    /** @param array<string, string> $payload */
    public function __construct(
        public array $payload,
        public string $hash,
    ) {}
}
