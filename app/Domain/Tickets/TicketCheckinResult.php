<?php

namespace App\Domain\Tickets;

use App\Models\Booking;
use App\Models\TicketCheckinEvent;

final readonly class TicketCheckinResult
{
    public function __construct(
        public string $result,
        public string $message,
        public ?Booking $booking = null,
        public ?TicketCheckinEvent $event = null,
    ) {}
}
