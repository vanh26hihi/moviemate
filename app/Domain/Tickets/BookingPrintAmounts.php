<?php

namespace App\Domain\Tickets;

use App\Models\AdmissionTicket;
use OutOfBoundsException;

final readonly class BookingPrintAmounts
{
    /** @param array<int, int> $ticketAmounts */
    public function __construct(
        public array $ticketAmounts,
        public ?int $foodVoucherAmount,
        public int $total,
    ) {}

    public function forTicket(AdmissionTicket|int $ticket): int
    {
        $ticketId = $ticket instanceof AdmissionTicket ? (int) $ticket->getKey() : $ticket;

        if (! array_key_exists($ticketId, $this->ticketAmounts)) {
            throw new OutOfBoundsException('The admission ticket does not belong to this print allocation.');
        }

        return $this->ticketAmounts[$ticketId];
    }

    public function allocatedTotal(): int
    {
        return array_sum($this->ticketAmounts) + ($this->foodVoucherAmount ?? 0);
    }
}
