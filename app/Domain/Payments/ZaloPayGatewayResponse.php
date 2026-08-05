<?php

namespace App\Domain\Payments;

final readonly class ZaloPayGatewayResponse
{
    public function __construct(
        public array $payload,
        public string $hash,
    ) {}
}
