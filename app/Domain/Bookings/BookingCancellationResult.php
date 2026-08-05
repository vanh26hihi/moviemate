<?php

namespace App\Domain\Bookings;

final readonly class BookingCancellationResult
{
    private function __construct(
        public bool $cancelled,
        public bool $alreadyCancelled,
    ) {}

    public static function cancelled(): self
    {
        return new self(true, false);
    }

    public static function alreadyCancelled(): self
    {
        return new self(false, true);
    }

    public static function notCancellable(): self
    {
        return new self(false, false);
    }
}
