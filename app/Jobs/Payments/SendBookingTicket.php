<?php

namespace App\Jobs\Payments;

use App\Models\BookingTicketDelivery;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Compatibility shim for old queued jobs. Delivery itself is performed only by
 * the leased durable-outbox command.
 */
class SendBookingTicket implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $uniqueFor = 3600;

    public function __construct(public int $bookingId)
    {
        $this->afterCommit = true;
    }

    public function uniqueId(): string
    {
        return (string) $this->bookingId;
    }

    public function handle(): void
    {
        BookingTicketDelivery::query()->firstOrCreate(
            ['booking_id' => $this->bookingId],
            [
                'status' => BookingTicketDelivery::STATUS_PENDING,
                'attempts' => 0,
                'available_at' => now(),
            ],
        );
    }
}
