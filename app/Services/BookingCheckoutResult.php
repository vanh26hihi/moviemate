<?php

namespace App\Services;

use App\Models\Booking;

class BookingCheckoutResult
{
    public function __construct(
        public readonly Booking $booking,
        public readonly ?string $guestAccessToken,
        public readonly bool $replayed,
    ) {}
}
