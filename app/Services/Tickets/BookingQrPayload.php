<?php

namespace App\Services\Tickets;

use App\Models\Booking;

final class BookingQrPayload
{
    public function __construct(private readonly BookingLookupCapability $capabilities) {}

    public function value(Booking $booking): string
    {
        return $this->capabilities->issue($booking);
    }

    public function capabilityFrom(?string $payload): ?string
    {
        if (! is_string($payload) || $payload === '') {
            return null;
        }

        $candidate = trim($payload);

        return $this->capabilities->bookingId($candidate) !== null ? $candidate : null;
    }
}
