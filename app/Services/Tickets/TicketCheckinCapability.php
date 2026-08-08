<?php

namespace App\Services\Tickets;

use App\Exceptions\BookingTokenConfigurationException;
use App\Models\Booking;

final class TicketCheckinCapability
{
    private const VERSION = 'v1';

    public function issue(Booking $booking): string
    {
        return self::VERSION.'.'.$booking->getKey().'.'.$this->signature($booking);
    }

    public function bookingId(?string $capability): ?int
    {
        if (! is_string($capability)
            || preg_match('/^'.self::VERSION.'\.([1-9][0-9]{0,18})\.[A-Za-z0-9_-]{43}$/D', $capability, $matches) !== 1) {
            return null;
        }

        $id = filter_var($matches[1], FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);

        return is_int($id) ? $id : null;
    }

    public function isValid(Booking $booking, ?string $capability): bool
    {
        if ($this->bookingId($capability) !== $booking->getKey()) {
            return false;
        }

        $parts = explode('.', (string) $capability);

        return isset($parts[2]) && hash_equals($this->signature($booking), $parts[2]);
    }

    private function signature(Booking $booking): string
    {
        $payload = implode(':', [
            self::VERSION,
            $booking->getKey(),
            $booking->booking_code,
            $booking->showtime_id,
            $booking->getRawOriginal('paid_at') ?? '',
        ]);

        return rtrim(strtr(base64_encode(hash_hmac('sha256', $payload, $this->key(), true)), '+/', '-_'), '=');
    }

    private function key(): string
    {
        $configured = config('app.key');
        if (! is_string($configured) || $configured === '') {
            throw new BookingTokenConfigurationException('APP_KEY must be configured before ticket check-in capabilities can be used.');
        }

        $key = str_starts_with($configured, 'base64:')
            ? base64_decode(substr($configured, 7), true)
            : $configured;
        if (! is_string($key) || strlen($key) < 32) {
            throw new BookingTokenConfigurationException('APP_KEY must provide at least 256 bits of key material.');
        }

        return hash_hmac('sha256', 'moviemate/ticket-checkin/v1', $key, true);
    }
}
