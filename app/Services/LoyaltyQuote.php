<?php

namespace App\Services;

final readonly class LoyaltyQuote
{
    public function __construct(
        public int $availablePoints,
        public int $pointsUsed,
        public int $pointValueVnd,
        public int $discountAmount,
        public int $finalAmount,
    ) {}
}
