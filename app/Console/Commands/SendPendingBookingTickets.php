<?php

namespace App\Console\Commands;

use App\Exceptions\UnsafeProductionMailConfiguration;
use App\Mail\BookingTicketMail;
use App\Models\Booking;
use App\Models\BookingTicketDelivery;
use App\Services\BookingTokenService;
use App\Services\Mail\ProductionMailTransportGuard;
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

    public function handle(
        BookingTokenService $tokens,
        ProductionMailTransportGuard $mailGuard,
    ): int {
        try {
            $mailGuard->assertSafeForProduction();
        } catch (UnsafeProductionMailConfiguration $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

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
        [$booking, $ticketEmailToken] = $this->prepareBookingForDelivery($delivery, $tokens);
        $booking->load([
            'user',
            'showtime.movie',
            'showtime.cinema',
            'showtime.room',
            'bookingSeats.seat',
            'payment',
        ]);

        $recipient = $booking->recipient_email;
        if (! is_string($recipient) || $recipient === '') {
            throw new RuntimeException('recipient_missing');
        }

        $ticketAccessUrl = $ticketEmailToken === null
            ? route('user.bookings.ticket', $booking)
            : route('user.bookings.access.show', $booking)
                .'#token='.rawurlencode($ticketEmailToken).'&destination=ticket';

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

    /** @return array{0: Booking, 1: ?string} */
    private function prepareBookingForDelivery(
        BookingTicketDelivery $delivery,
        BookingTokenService $tokens,
    ): array {
        return DB::transaction(function () use ($delivery, $tokens): array {
            $lockedDelivery = BookingTicketDelivery::query()
                ->whereKey($delivery->getKey())
                ->lockForUpdate()
                ->first();
            if (! $lockedDelivery
                || $lockedDelivery->status !== BookingTicketDelivery::STATUS_PROCESSING
                || $lockedDelivery->getRawOriginal('processing_started_at')
                    !== $delivery->getRawOriginal('processing_started_at')) {
                throw new RuntimeException('delivery_lease_lost');
            }

            $booking = Booking::query()
                ->whereKey($lockedDelivery->booking_id)
                ->lockForUpdate()
                ->first();
            if (! $booking
                || $booking->payment_status !== 'paid'
                || $booking->booking_status !== 'paid') {
                throw new RuntimeException('booking_not_paid');
            }

            $ticketEmailToken = $booking->user_id === null
                ? $this->ticketEmailToken($booking, $tokens)
                : null;

            return [$booking, $ticketEmailToken];
        });
    }

    private function ticketEmailToken(Booking $booking, BookingTokenService $tokens): string
    {
        $nonce = $booking->ticket_email_token_nonce;
        $hash = $booking->ticket_email_token_hash;
        if (is_string($nonce)
            && is_string($hash)
            && $booking->ticket_email_token_expires_at?->isFuture()) {
            try {
                $token = $tokens->ticketEmailTokenForNonce($booking->getKey(), $nonce);
                if ($tokens->verifyHash($hash, $token)) {
                    return $token;
                }
            } catch (\InvalidArgumentException) {
                // A malformed or incomplete credential is replaced while the row is locked.
            }
        }

        $credential = $tokens->issueTicketEmailCredential($booking->getKey());
        $booking->forceFill([
            'ticket_email_token_nonce' => $credential['nonce'],
            'ticket_email_token_hash' => $tokens->hash($credential['token']),
            'ticket_email_token_expires_at' => now()->addMinutes(
                max(1, (int) config('booking.ticket_email_access_ttl_minutes', 10080)),
            ),
        ])->save();

        return $credential['token'];
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
