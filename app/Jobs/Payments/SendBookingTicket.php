<?php

namespace App\Jobs\Payments;

use App\Mail\BookingTicketMail;
use App\Models\Booking;
use App\Services\BookingTokenService;
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
        $claim = DB::transaction(function () use ($claimedAt): ?array {
            $booking = Booking::query()->lockForUpdate()->find($this->bookingId);

            if (! $booking
                || $booking->payment_status !== 'paid'
                || $booking->booking_status !== 'paid'
                || $booking->ticket_emailed_at !== null) {
                return null;
            }

            $guestAccessToken = null;
            $fields = ['ticket_emailed_at' => $claimedAt];
            if ($booking->user_id === null) {
                $guestAccessToken = app(BookingTokenService::class)->issueGuestAccessToken();
                $fields['guest_access_token_hash'] = hash('sha256', $guestAccessToken);
                $fields['guest_access_expires_at'] = now()->addMinutes(
                    max(1, (int) config('booking.guest_access_ttl_minutes', 1440)),
                );
            }

            $booking->forceFill($fields)->save();

            return [$booking, $guestAccessToken];
        });

        if (! $claim) {
            return;
        }

        [$booking, $guestAccessToken] = $claim;

        $recipient = $booking->recipient_email;

        if (! is_string($recipient) || $recipient === '') {
            $this->releaseClaim($claimedAt);
            throw new RuntimeException('A paid booking has no ticket recipient email.');
        }

        try {
            $ticketAccessUrl = $guestAccessToken === null
                ? route('user.bookings.ticket', $booking)
                : route('user.bookings.access.show', $booking)
                    .'#token='.rawurlencode($guestAccessToken).'&destination=ticket';
            Mail::to($recipient)->send(new BookingTicketMail($booking, $ticketAccessUrl));
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
