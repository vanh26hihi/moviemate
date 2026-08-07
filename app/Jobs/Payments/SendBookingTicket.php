<?php

namespace App\Jobs\Payments;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Artisan;

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
        Artisan::call('bookings:send-pending-tickets', [
            '--booking' => $this->bookingId,
            '--batch' => 1,
        ]);
    }
}
