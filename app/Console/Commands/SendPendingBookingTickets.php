<?php

namespace App\Console\Commands;

use App\Mail\BookingTicketMail;
use App\Models\Booking;
use App\Models\BookingTicketDelivery;
use App\Services\BookingTokenService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use RuntimeException;
use Throwable;

class SendPendingBookingTickets extends Command
{
    protected $signature = 'bookings:send-pending-tickets {--batch= : Maximum deliveries to claim}';

    protected $description = 'Send paid booking tickets from the durable delivery outbox';

    public function handle(BookingTokenService $tokens): int
    {
        $configuredBatch = (int) config('payment.ticket_delivery.batch_size', 100);
        $batch = $this->option('batch') === null
            ? $configuredBatch
            : (int) $this->option('batch');
        $batch = max(1, min(1000, $batch));
        $counts = ['sent' => 0, 'failed' => 0];

        for ($i = 0; $i < $batch; $i++) {
            $claim = $this->claimNext();
            if ($claim === null) {
                break;
            }

            try {
                $this->send($claim, $tokens);
                $counts['sent']++;
            } catch (Throwable $exception) {
                $this->failDelivery($claim, $exception);
                $counts['failed']++;
            }
        }

        $this->info("Sent: {$counts['sent']}; failed: {$counts['failed']}");

        return self::SUCCESS;
    }

    private function claimNext(): ?BookingTicketDelivery
    {
        return DB::transaction(function (): ?BookingTicketDelivery {
            $now = now()->startOfSecond();
            $delivery = BookingTicketDelivery::query()
                ->whereNull('sent_at')
                ->where(function ($query) use ($now): void {
                    $query->where(function ($ready) use ($now): void {
                        $ready->whereIn('status', [
                            BookingTicketDelivery::STATUS_PENDING,
                            BookingTicketDelivery::STATUS_FAILED,
                        ])->where(function ($available) use ($now): void {
                            $available->whereNull('available_at')->orWhere('available_at', '<=', $now);
                        });
                    })->orWhere(function ($expiredLease) use ($now): void {
                        $expiredLease->where('status', BookingTicketDelivery::STATUS_PROCESSING)
                            ->whereNotNull('lease_expires_at')
                            ->where('lease_expires_at', '<=', $now);
                    });
                })
                ->orderBy('available_at')
                ->orderBy('id')
                ->lockForUpdate()
                ->first();

            if ($delivery === null) {
                return null;
            }

            $leaseSeconds = max(30, (int) config('payment.ticket_delivery.lease_seconds', 300));
            $delivery->forceFill([
                'status' => BookingTicketDelivery::STATUS_PROCESSING,
                'attempts' => $delivery->attempts + 1,
                'processing_started_at' => $now,
                'lease_expires_at' => $now->copy()->addSeconds($leaseSeconds),
                'last_error_code' => null,
            ])->save();

            return $delivery->fresh();
        });
    }

    private function send(BookingTicketDelivery $delivery, BookingTokenService $tokens): void
    {
        $mailer = (string) config('mail.default');
        if (app()->environment('production') && in_array($mailer, ['log', 'array'], true)) {
            Log::warning('Ticket delivery rejected an unsafe production mailer.', [
                'delivery_id' => $delivery->getKey(),
                'mailer' => $mailer,
            ]);
            throw new RuntimeException('unsafe_production_mailer');
        }

        $booking = Booking::query()->with([
            'user',
            'showtime.movie',
            'showtime.cinema',
            'showtime.room',
            'bookingSeats.seat',
            'payment',
        ])->find($delivery->booking_id);
        if (! $booking
            || $booking->payment_status !== 'paid'
            || $booking->booking_status !== 'paid') {
            throw new RuntimeException('booking_not_paid');
        }

        $recipient = $booking->recipient_email;
        if (! is_string($recipient) || $recipient === '') {
            throw new RuntimeException('recipient_missing');
        }

        $guestAccessToken = null;
        if ($booking->user_id === null) {
            $guestAccessToken = $tokens->issueGuestAccessToken();
            $booking->forceFill([
                'guest_access_token_hash' => $tokens->hash($guestAccessToken),
                'guest_access_expires_at' => now()->addMinutes(
                    max(1, (int) config('booking.guest_access_ttl_minutes', 1440)),
                ),
            ])->save();
        }

        $ticketAccessUrl = $guestAccessToken === null
            ? route('user.bookings.ticket', $booking)
            : route('user.bookings.access.show', $booking)
                .'#token='.rawurlencode($guestAccessToken).'&destination=ticket';

        Mail::to($recipient)->send(new BookingTicketMail($booking, $ticketAccessUrl));

        DB::transaction(function () use ($delivery, $booking): void {
            $claimedAt = $delivery->processing_started_at;
            $updated = BookingTicketDelivery::query()
                ->whereKey($delivery->getKey())
                ->where('status', BookingTicketDelivery::STATUS_PROCESSING)
                ->where('processing_started_at', $claimedAt)
                ->update([
                    'status' => BookingTicketDelivery::STATUS_SENT,
                    'sent_at' => now(),
                    'available_at' => null,
                    'lease_expires_at' => null,
                    'last_error_code' => null,
                    'updated_at' => now(),
                ]);
            if ($updated !== 1) {
                throw new RuntimeException('delivery_lease_lost');
            }

            Booking::query()->whereKey($booking->getKey())->update([
                'ticket_emailed_at' => now(),
            ]);
        });
    }

    private function failDelivery(BookingTicketDelivery $delivery, Throwable $exception): void
    {
        $base = max(1, (int) config('payment.ticket_delivery.backoff_base_seconds', 60));
        $max = max($base, (int) config('payment.ticket_delivery.backoff_max_seconds', 3600));
        $backoff = min($max, $base * (2 ** min(10, max(0, $delivery->attempts - 1))));
        $code = $this->errorCode($exception);

        BookingTicketDelivery::query()
            ->whereKey($delivery->getKey())
            ->where('status', BookingTicketDelivery::STATUS_PROCESSING)
            ->where('processing_started_at', $delivery->processing_started_at)
            ->update([
                'status' => BookingTicketDelivery::STATUS_FAILED,
                'available_at' => now()->addSeconds($backoff),
                'lease_expires_at' => null,
                'last_error_code' => $code,
                'updated_at' => now(),
            ]);

        Log::warning('Ticket delivery attempt failed and remains retryable.', [
            'delivery_id' => $delivery->getKey(),
            'error_code' => $code,
            'exception' => $exception::class,
        ]);
    }

    private function errorCode(Throwable $exception): string
    {
        $message = $exception->getMessage();
        if ($exception instanceof RuntimeException
            && preg_match('/^[a-z0-9_]{1,100}$/D', $message) === 1) {
            return $message;
        }

        return str($exception::class)->afterLast('\\')->snake()->limit(100, '')->toString();
    }
}
