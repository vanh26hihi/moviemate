<?php

namespace App\Domain\Payments;

final readonly class PayOsGatewayResponse
{
    /** @param array<string, mixed> $data */
    public function __construct(
        public string $code,
        public array $data,
        public string $hash,
    ) {}
}
