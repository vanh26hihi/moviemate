<?php

namespace App\Services\Tickets;

use App\Exceptions\BookingTokenConfigurationException;
use App\Models\AdmissionTicket;
use App\Models\Booking;

final class TicketCheckinCapability
{
    private const VERSION = 'v2';

    public function __construct(private readonly TicketArtifactProvisioner $provisioner) {}

    public function issue(AdmissionTicket|Booking $subject): string
    {
        $ticket = $this->ticket($subject);

        return self::VERSION.'.'.$ticket->getKey().'.'.$this->signature($ticket);
    }

    public function ticketId(?string $capability): ?int
    {
        if (! is_string($capability)
            || preg_match('/^'.self::VERSION.'\.([1-9][0-9]{0,18})\.[A-Za-z0-9_-]{43}$/D', $capability, $matches) !== 1) {
            return null;
        }

        $id = filter_var($matches[1], FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);

        return is_int($id) ? $id : null;
    }

    public function bookingId(?string $capability): ?int
    {
        return $this->ticketId($capability);
    }

    public function isValid(AdmissionTicket|Booking $subject, ?string $capability): bool
    {
        $ticket = $this->ticket($subject);
        if ($this->ticketId($capability) !== $ticket->getKey()) {
            return false;
        }

        $parts = explode('.', (string) $capability);

        return isset($parts[2]) && hash_equals($this->signature($ticket), $parts[2]);
    }

    private function signature(AdmissionTicket $ticket): string
    {
        $ticket->loadMissing('booking');
        $payload = implode(':', [
            self::VERSION,
            $ticket->getKey(),
            $ticket->ticket_code,
            $ticket->booking_id,
            $ticket->booking_seat_id,
            $ticket->booking->getRawOriginal('paid_at') ?? '',
        ]);

        return rtrim(strtr(base64_encode(hash_hmac('sha256', $payload, $this->key(), true)), '+/', '-_'), '=');
    }

    private function ticket(AdmissionTicket|Booking $subject): AdmissionTicket
    {
        if ($subject instanceof AdmissionTicket) {
            return $subject;
        }

        $this->provisioner->provision($subject);

        return $subject->admissionTickets()->orderBy('id')->firstOrFail();
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
