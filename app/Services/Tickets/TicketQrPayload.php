<?php

namespace App\Services\Tickets;

use App\Models\AdmissionTicket;
use App\Models\Booking;

final class TicketQrPayload
{
    public function __construct(private readonly TicketCheckinCapability $capabilities) {}

    public function url(AdmissionTicket|Booking $subject): string
    {
        return route('tickets.verify', ['capability' => $this->capabilities->issue($subject)]);
    }

    public function capabilityFrom(?string $payload): ?string
    {
        if (! is_string($payload) || $payload === '') {
            return null;
        }

        if ($this->capabilities->ticketId($payload) !== null) {
            return $payload;
        }

        $path = parse_url($payload, PHP_URL_PATH);
        if (! is_string($path)) {
            return null;
        }

        $candidate = rawurldecode((string) str($path)->afterLast('/'));
        if ($this->capabilities->ticketId($candidate) === null) {
            return null;
        }

        $expected = route('tickets.verify', ['capability' => $candidate]);

        return hash_equals($expected, $payload) ? $candidate : null;
    }
}
