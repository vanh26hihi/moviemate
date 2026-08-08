<?php

namespace App\Domain\Tickets;

use App\Models\BookingTicketDelivery;

final readonly class TicketDeliveryRetryResult
{
    public function __construct(
        public string $category,
        public BookingTicketDelivery $delivery,
        public bool $changed,
    ) {}
}
