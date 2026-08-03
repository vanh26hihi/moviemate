<?php

namespace App\Jobs\Payments;

use App\Mail\BookingTicketMail;
use App\Models\Booking;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use RuntimeException;
use Throwable;

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
        $claimedAt = now()->startOfSecond();
        $booking = DB::transaction(function () use ($claimedAt): ?Booking {
            $booking = Booking::query()->lockForUpdate()->find($this->bookingId);

            if (! $booking
                || $booking->payment_status !== 'paid'
                || $booking->booking_status !== 'paid'
                || $booking->ticket_emailed_at !== null) {
                return null;
            }

            $booking->forceFill(['ticket_emailed_at' => $claimedAt])->save();

            return $booking;
        });

        if (! $booking) {
            return;
        }

        $recipient = $booking->recipient_email;

        if (! is_string($recipient) || $recipient === '') {
            $this->releaseClaim($claimedAt);
            throw new RuntimeException('A paid booking has no ticket recipient email.');
        }

        try {
            Mail::to($recipient)->send(new BookingTicketMail($booking));
        } catch (Throwable $exception) {
            $this->releaseClaim($claimedAt);
            throw $exception;
        }
    }

    private function releaseClaim($claimedAt): void
    {
        Booking::query()
            ->whereKey($this->bookingId)
            ->where('ticket_emailed_at', $claimedAt)
            ->update(['ticket_emailed_at' => null]);
    }
}
