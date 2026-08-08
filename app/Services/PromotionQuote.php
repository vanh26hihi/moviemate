<?php

namespace App\Services;

use Illuminate\Support\Collection;

final readonly class PromotionQuote
{
    public function __construct(public int $grossAmount, public int $discountAmount, public int $finalAmount, public Collection $lines) {}
}
