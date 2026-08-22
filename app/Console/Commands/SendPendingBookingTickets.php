<?php

namespace App\Console\Commands;

use App\Exceptions\UnsafeProductionMailConfiguration;
use App\Mail\BookingTicketMail;
use App\Models\Booking;
use App\Models\BookingTicketDelivery;
use App\Services\ActivityLogger;
use App\Services\Admin\AdminTicketDeliveryQuery;
use App\Services\BookingTokenService;
use App\Services\Mail\ProductionMailTransportGuard;
use App\Services\Mail\TicketMailConfigurationInspector;
use App\Services\Tickets\BookingQrPayload;
use App\Services\Tickets\BookingTicketEligibility;
use App\Services\Tickets\TicketQrCode;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use RuntimeException;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Throwable;

class SendPendingBookingTickets extends Command
{
    protected $signature = 'bookings:send-pending-tickets {--batch= : Maximum deliveries to claim} {--booking= : Process only this booking ID}';

    protected $description = 'Send paid booking tickets from the durable delivery outbox';

    public function handle(
        BookingTokenService $tokens,
        ProductionMailTransportGuard $mailGuard,
        TicketMailConfigurationInspector $mailConfiguration,
        ActivityLogger $activities,
        AdminTicketDeliveryQuery $deliveryQuery,
    ): int {
        try {
            $mailGuard->assertSafeForProduction();
        } catch (UnsafeProductionMailConfiguration $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $configuration = $mailConfiguration->inspect();
        if (! $configuration['ready']) {
            $this->error('Ticket mail delivery is blocked: '.$configuration['category'].'.');

            return self::FAILURE;
        }

        $configuredBatch = (int) config('payment.ticket_delivery.batch_size', 100);
        $batch = $this->option('batch') === null
            ? $configuredBatch
            : (int) $this->option('batch');
        $batch = max(1, min(1000, $batch));
        $counts = ['sent' => 0, 'failed' => 0];

        for ($i = 0; $i < $batch; $i++) {
            $claim = $this->claimNext($this->option('booking') === null ? null : (int) $this->option('booking'));
            if ($claim === null) {
                break;
            }

            try {
                $this->send($claim, $tokens);
            } catch (Throwable $exception) {
                $this->failDelivery($claim, $exception);
                $failed = $claim->fresh();
                $deliveryQuery->forgetBadge();

                if ($failed?->status === BookingTicketDelivery::STATUS_FAILED) {
                    $activities->log('ticket_delivery.send_failed', $failed, [
                        'delivery_status' => BookingTicketDelivery::STATUS_PROCESSING,
                    ], [
                        'delivery_status' => $failed->status,
                    ], [
                        'booking_id' => $failed->booking_id,
                        'delivery_id' => $failed->id,
                        'attempt_number' => $failed->attempts,
                        'error_category' => $failed->last_error_code,
                    ]);
                }
                $counts['failed']++;

                continue;
            }

            $sent = $claim->fresh();
            $deliveryQuery->forgetBadge();
            $activities->log('ticket_delivery.send_succeeded', $sent, [
                'delivery_status' => BookingTicketDelivery::STATUS_PROCESSING,
            ], [
                'delivery_status' => $sent->status,
            ], [
                'booking_id' => $sent->booking_id,
                'delivery_id' => $sent->id,
                'attempt_number' => $sent->attempts,
            ]);
            $counts['sent']++;
        }

        $this->info("Sent: {$counts['sent']}; failed: {$counts['failed']}");

        return self::SUCCESS;
    }

    private function claimNext(?int $bookingId = null): ?BookingTicketDelivery
    {
        return DB::transaction(function () use ($bookingId): ?BookingTicketDelivery {
            $now = now();
            $leaseSeconds = max(30, (int) config('payment.ticket_delivery.lease_seconds', 300));
            $staleStartedAt = $now->copy()->subSeconds($leaseSeconds);
            $delivery = BookingTicketDelivery::query()
                ->when($bookingId !== null, fn ($query) => $query->where('booking_id', $bookingId))
                ->whereNull('sent_at')
                ->where(function ($query) use ($now, $staleStartedAt): void {
                    $query->where(function ($ready) use ($now): void {
                        $ready->whereIn('status', [
                            BookingTicketDelivery::STATUS_PENDING,
                            BookingTicketDelivery::STATUS_FAILED,
                        ])->where(function ($available) use ($now): void {
                            $available->whereNull('available_at')->orWhere('available_at', '<=', $now);
                        });
                    })->orWhere(function ($expiredLease) use ($now, $staleStartedAt): void {
                        $expiredLease->where('status', BookingTicketDelivery::STATUS_PROCESSING)
                            ->where(function ($stale) use ($now, $staleStartedAt): void {
                                $stale->where('lease_expires_at', '<=', $now)
                                    ->orWhere(function ($missingLease) use ($staleStartedAt): void {
                                        $missingLease->whereNull('lease_expires_at')
                                            ->where(function ($missingClaimTime) use ($staleStartedAt): void {
                                                $missingClaimTime->whereNull('processing_started_at')
                                                    ->orWhere('processing_started_at', '<=', $staleStartedAt);
                                            });
                                    });
                            });
                    });
                })
                ->orderBy('available_at')
                ->orderBy('id')
                ->lockForUpdate()
                ->first();

            if ($delivery === null) {
                return null;
            }

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
            'showtime.presentationFormat',
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

        $bookingQrPayload = app(BookingQrPayload::class)->value($booking);
        $ticketQrPng = app(TicketQrCode::class)->png($bookingQrPayload);
        Mail::to($recipient)->send(new BookingTicketMail($booking, $ticketAccessUrl, $ticketQrPng));

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
                || ! app(BookingTicketEligibility::class)->isDeliverable($booking)) {
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
        ]);
    }

    private function errorCode(Throwable $exception): string
    {
        $message = $exception->getMessage();
        if ($exception instanceof TransportExceptionInterface) {
            $normalized = strtolower($message);

            return str_contains($normalized, 'authenticat') || str_contains($normalized, '535')
                ? 'smtp_authentication_failed'
                : 'smtp_connection_failed';
        }

        if ($exception instanceof RuntimeException
            && preg_match('/^[a-z0-9_]{1,100}$/D', $message) === 1) {
            return $message;
        }

        return str($exception::class)->afterLast('\\')->snake()->limit(100, '')->toString();
    }
}
