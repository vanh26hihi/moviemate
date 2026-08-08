<?php

namespace Tests\Feature\Payments;

use App\Mail\BookingTicketMail;
use App\Models\ActivityLog;
use App\Models\BookingTicketDelivery;
use App\Services\BookingTokenService;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Mail\PendingMail;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Mockery;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;

class TicketDeliveryOutboxTest extends PaymentTestCase
{
    public function test_production_paths_contain_no_external_qr_service_reference(): void
    {
        $needle = 'api.'.'qrserver.com';
        foreach (['app', 'config', 'resources', 'routes'] as $directory) {
            foreach (File::allFiles(base_path($directory)) as $file) {
                $this->assertStringNotContainsString($needle, $file->getContents(), $file->getPathname());
            }
        }
    }

    public function test_command_sends_ticket_and_marks_delivery_sent_only_after_mail_success(): void
    {
        Mail::fake();
        $payment = $this->verifiedPayment();

        $this->assertNull($payment->booking->fresh()->ticket_emailed_at);
        $this->artisan('bookings:send-pending-tickets')->assertSuccessful();

        $delivery = BookingTicketDelivery::query()->sole();
        $this->assertSame(BookingTicketDelivery::STATUS_SENT, $delivery->status);
        $this->assertNotNull($delivery->sent_at);
        $this->assertSame(1, $delivery->attempts);
        $this->assertNotNull($payment->booking->fresh()->ticket_emailed_at);
        Mail::assertSent(BookingTicketMail::class, 1);
        $this->assertDatabaseHas('activity_logs', ['action' => 'ticket_delivery.send_succeeded']);
    }

    public function test_email_contains_text_code_and_secure_fragment_link_without_external_qr(): void
    {
        Mail::fake();
        $payment = $this->verifiedPayment();

        $this->artisan('bookings:send-pending-tickets')->assertSuccessful();

        Mail::assertSent(BookingTicketMail::class, function (BookingTicketMail $mail) use ($payment): bool {
            $html = $mail->render();
            $this->assertStringContainsString($payment->booking->booking_code, $html);
            $this->assertStringNotContainsString('api.'.'qrserver.com', $html);
            $this->assertStringStartsWith(
                route('user.bookings.access.show', $payment->booking).'#token=',
                $mail->ticketAccessUrl,
            );
            $this->assertStringContainsString('&destination=ticket', $mail->ticketAccessUrl);
            $this->assertStringNotContainsString('guest_token=', $mail->ticketAccessUrl);
            $this->assertNull(parse_url($mail->ticketAccessUrl, PHP_URL_QUERY));

            return true;
        });
    }

    public function test_mail_failure_keeps_paid_state_and_delivery_retryable(): void
    {
        $payment = $this->verifiedPayment();
        $this->configureProductionMailer('smtp', [
            'smtp' => ['transport' => 'smtp'],
        ]);
        Mail::shouldReceive('to')->once()->andThrow(new RuntimeException('SMTP unavailable'));

        $this->artisan('bookings:send-pending-tickets')->assertSuccessful();

        $delivery = BookingTicketDelivery::query()->sole();
        $this->assertSame(BookingTicketDelivery::STATUS_FAILED, $delivery->status);
        $this->assertNull($delivery->sent_at);
        $this->assertSame(1, $delivery->attempts);
        $this->assertNotNull($delivery->available_at);
        $this->assertNull($delivery->lease_expires_at);
        $this->assertSame('paid', $payment->booking->fresh()->payment_status);
        $this->assertSame('paid', $payment->booking->fresh()->booking_status);
        $this->assertNull($payment->booking->fresh()->ticket_emailed_at);
        $this->assertDatabaseHas('activity_logs', ['action' => 'ticket_delivery.send_failed']);
        $serialized = json_encode(ActivityLog::query()->where('action', 'ticket_delivery.send_failed')->sole()->getAttributes());
        $this->assertStringNotContainsString('SMTP unavailable', $serialized);
        $this->assertStringNotContainsString('RuntimeException', $serialized);
    }

    public function test_failed_delivery_is_retried_after_backoff(): void
    {
        $payment = $this->verifiedPayment();
        Mail::shouldReceive('to')->once()->andThrow(new RuntimeException('SMTP unavailable'));
        $this->artisan('bookings:send-pending-tickets')->assertSuccessful();

        BookingTicketDelivery::query()->update(['available_at' => now()->subSecond()]);
        $this->app->forgetInstance('mail.manager');
        Mail::clearResolvedInstance('mail.manager');
        Mail::fake();
        $this->artisan('bookings:send-pending-tickets')->assertSuccessful();

        $delivery = BookingTicketDelivery::query()->sole();
        $this->assertSame(BookingTicketDelivery::STATUS_SENT, $delivery->status);
        $this->assertSame(2, $delivery->attempts);
        Mail::assertSent(BookingTicketMail::class, 1);
        $this->assertSame('paid', $payment->booking->fresh()->payment_status);
    }

    public function test_used_booking_can_receive_existing_ticket_without_reactivating_qr(): void
    {
        Mail::fake();
        $payment = $this->verifiedPayment();
        $payment->booking->forceFill(['booking_status' => 'used', 'used_at' => now()])->save();

        $this->artisan('bookings:send-pending-tickets')->assertSuccessful();

        $this->assertSame(BookingTicketDelivery::STATUS_SENT, BookingTicketDelivery::query()->sole()->status);
        $this->assertSame('used', $payment->booking->fresh()->booking_status);
        Mail::assertSent(BookingTicketMail::class, 1);
    }

    public function test_transport_failure_preserves_guest_session_and_retry_reuses_email_credential(): void
    {
        $this->withoutVite();
        $this->withoutMiddleware(PreventRequestForgery::class);
        $scenario = $this->bookingScenario(false);
        $checkoutToken = app(BookingTokenService::class)->issueCheckoutToken();
        $reservation = $this->reserve(
            $scenario,
            [$scenario['seats'][0]->id],
            token: $checkoutToken,
        );
        $payment = $this->pendingPayment($reservation->booking);
        $this->postJson(route('payments.zalopay.callback'), $this->callbackBody($payment))
            ->assertJsonPath('return_code', 1);
        $booking = $reservation->booking->fresh();
        $guestHashBefore = $booking->getRawOriginal('guest_access_token_hash');
        $this->post(route('user.bookings.access.exchange', $booking), [
            'token' => $reservation->guestAccessToken,
            'destination' => 'ticket',
        ])->assertOk();
        $this->get(route('user.bookings.ticket', $booking))->assertOk();

        $this->configureProductionMailer('smtp', ['smtp' => ['transport' => 'smtp']]);
        $firstRawToken = null;
        $pending = Mockery::mock(PendingMail::class);
        $pending->shouldReceive('send')
            ->once()
            ->with(Mockery::on(function (BookingTicketMail $mail) use (&$firstRawToken): bool {
                $firstRawToken = $this->fragmentToken($mail->ticketAccessUrl);

                return is_string($firstRawToken);
            }))
            ->andThrow(new RuntimeException('SMTP unavailable'));
        Mail::shouldReceive('to')->once()->andReturn($pending);
        Log::spy();

        $this->artisan('bookings:send-pending-tickets')->assertSuccessful();

        $booking->refresh();
        $this->assertIsString($firstRawToken);
        $this->assertSame($guestHashBefore, $booking->getRawOriginal('guest_access_token_hash'));
        $this->assertNotNull($booking->ticket_email_token_nonce);
        $this->assertSame(
            hash('sha256', $firstRawToken),
            $booking->getRawOriginal('ticket_email_token_hash'),
        );
        $this->assertTrue($booking->ticket_email_token_expires_at->isFuture());
        $this->assertStringNotContainsString($firstRawToken, json_encode($booking->getAttributes()));
        $this->get(route('user.bookings.ticket', $booking))->assertOk();
        Log::shouldHaveReceived('warning')->once()->withArgs(
            function (string $message, array $context) use ($firstRawToken): bool {
                return ! str_contains($message.json_encode($context), $firstRawToken);
            },
        );

        $credentialBeforeRetry = [
            'nonce' => $booking->getRawOriginal('ticket_email_token_nonce'),
            'hash' => $booking->getRawOriginal('ticket_email_token_hash'),
            'expires_at' => $booking->getRawOriginal('ticket_email_token_expires_at'),
        ];
        BookingTicketDelivery::query()->update(['available_at' => now()->subSecond()]);
        $this->app->forgetInstance('mail.manager');
        Mail::clearResolvedInstance('mail.manager');
        Mail::fake();

        $this->artisan('bookings:send-pending-tickets')->assertSuccessful();

        $sentRawToken = null;
        Mail::assertSent(BookingTicketMail::class, function (BookingTicketMail $mail) use (&$sentRawToken): bool {
            $this->assertNull(parse_url($mail->ticketAccessUrl, PHP_URL_QUERY));
            $sentRawToken = $this->fragmentToken($mail->ticketAccessUrl);

            return is_string($sentRawToken);
        });
        $booking->refresh();
        $this->assertSame($firstRawToken, $sentRawToken);
        $this->assertSame($credentialBeforeRetry, [
            'nonce' => $booking->getRawOriginal('ticket_email_token_nonce'),
            'hash' => $booking->getRawOriginal('ticket_email_token_hash'),
            'expires_at' => $booking->getRawOriginal('ticket_email_token_expires_at'),
        ]);
        $this->app['session']->forget('guest_booking_capabilities');
        $this->post(route('user.bookings.access.exchange', $booking), [
            'token' => $sentRawToken,
            'destination' => 'ticket',
        ])->assertOk();
        $this->get(route('user.bookings.ticket', $booking))->assertOk();
    }

    public function test_expired_processing_lease_is_reclaimed(): void
    {
        Mail::fake();
        $this->verifiedPayment();
        BookingTicketDelivery::query()->update([
            'status' => BookingTicketDelivery::STATUS_PROCESSING,
            'attempts' => 1,
            'processing_started_at' => now()->subMinutes(10),
            'lease_expires_at' => now()->subMinute(),
        ]);

        $this->artisan('bookings:send-pending-tickets')->assertSuccessful();

        $delivery = BookingTicketDelivery::query()->sole();
        $this->assertSame(BookingTicketDelivery::STATUS_SENT, $delivery->status);
        $this->assertSame(2, $delivery->attempts);
        Mail::assertSent(BookingTicketMail::class, 1);
    }

    public function test_old_processing_claim_without_a_lease_is_reclaimed(): void
    {
        Mail::fake();
        $this->verifiedPayment();
        BookingTicketDelivery::query()->update([
            'status' => BookingTicketDelivery::STATUS_PROCESSING,
            'attempts' => 1,
            'processing_started_at' => now()->subMinutes(10),
            'lease_expires_at' => null,
        ]);

        $this->artisan('bookings:send-pending-tickets')->assertSuccessful();

        $delivery = BookingTicketDelivery::query()->sole();
        $this->assertSame(BookingTicketDelivery::STATUS_SENT, $delivery->status);
        $this->assertSame(2, $delivery->attempts);
        Mail::assertSent(BookingTicketMail::class, 1);
    }

    public function test_missing_outbox_requires_explicit_authorized_recovery_command(): void
    {
        $this->seedRbac();
        $payment = $this->verifiedPayment();
        BookingTicketDelivery::query()->delete();
        $operator = $this->userWithRole('manager');

        $this->artisan('payments:recover-ticket-delivery', [
            'payment' => $payment->id,
            '--actor' => $operator->id,
        ])->assertSuccessful()->expectsOutputToContain('Created pending ticket delivery');

        $delivery = BookingTicketDelivery::query()->sole();
        $this->assertSame($payment->booking_id, $delivery->booking_id);
        $this->assertSame(BookingTicketDelivery::STATUS_PENDING, $delivery->status);
        $this->assertSame(0, $delivery->attempts);
    }

    public function test_recovery_command_rejects_unauthorized_actor_without_writing(): void
    {
        $this->seedRbac();
        $payment = $this->verifiedPayment();
        BookingTicketDelivery::query()->delete();
        $customer = $this->userWithRole('user');

        $this->artisan('payments:recover-ticket-delivery', [
            'payment' => $payment->id,
            '--actor' => $customer->id,
        ])->assertFailed()->expectsOutputToContain('bookings.operate permission is required');

        $this->assertDatabaseCount('booking_ticket_deliveries', 0);
    }

    #[DataProvider('unsafeCommandGraphProvider')]
    public function test_unsafe_production_graph_is_rejected_before_claiming(
        string $default,
        array $mailers,
    ): void {
        Mail::fake();
        $payment = $this->verifiedPayment();
        $before = BookingTicketDelivery::query()->sole();
        $beforeState = $before->getRawOriginal();
        $bookingBeforeState = $payment->booking->fresh()->getRawOriginal();
        $paymentBeforeState = $payment->fresh()->getRawOriginal();
        $this->configureProductionMailer($default, $mailers);
        Log::spy();

        $this->artisan('bookings:send-pending-tickets')->assertFailed();

        $delivery = BookingTicketDelivery::query()->sole();
        $this->assertSame($beforeState, $delivery->getRawOriginal());
        $this->assertSame(BookingTicketDelivery::STATUS_PENDING, $delivery->status);
        $this->assertSame(0, $delivery->attempts);
        $this->assertSame($beforeState['available_at'], $delivery->getRawOriginal('available_at'));
        $this->assertSame($beforeState['processing_started_at'], $delivery->getRawOriginal('processing_started_at'));
        $this->assertSame($beforeState['lease_expires_at'], $delivery->getRawOriginal('lease_expires_at'));
        $this->assertSame($beforeState['last_error_code'], $delivery->getRawOriginal('last_error_code'));
        $this->assertNull($delivery->processing_started_at);
        $this->assertNull($delivery->lease_expires_at);
        $this->assertNull($delivery->sent_at);
        $booking = $payment->booking->fresh();
        $this->assertSame($bookingBeforeState, $booking->getRawOriginal());
        $this->assertSame($bookingBeforeState['ticket_emailed_at'], $booking->getRawOriginal('ticket_emailed_at'));
        $this->assertSame($bookingBeforeState['guest_access_token_hash'], $booking->getRawOriginal('guest_access_token_hash'));
        $this->assertSame($bookingBeforeState['guest_access_expires_at'], $booking->getRawOriginal('guest_access_expires_at'));
        $this->assertSame($bookingBeforeState['payment_status'], $booking->getRawOriginal('payment_status'));
        $this->assertSame($bookingBeforeState['booking_status'], $booking->getRawOriginal('booking_status'));
        $this->assertSame($paymentBeforeState, $payment->fresh()->getRawOriginal());
        Mail::assertNothingSent();
        Log::shouldNotHaveReceived('warning');
        Log::shouldNotHaveReceived('error');
    }

    public static function unsafeCommandGraphProvider(): array
    {
        return [
            'direct log' => [
                'delivery',
                [
                    'delivery' => ['transport' => 'log'],
                    'smtp' => ['transport' => 'smtp'],
                ],
            ],
            'failover branch' => [
                'delivery',
                [
                    'delivery' => ['transport' => 'failover', 'mailers' => ['smtp', 'audit']],
                    'smtp' => ['transport' => 'smtp'],
                    'audit' => ['transport' => 'log'],
                ],
            ],
            'nested unsafe branch' => [
                'delivery',
                [
                    'delivery' => ['transport' => 'failover', 'mailers' => ['smtp', 'backup']],
                    'smtp' => ['transport' => 'smtp'],
                    'backup' => ['transport' => 'roundrobin', 'mailers' => ['memory']],
                    'memory' => ['transport' => 'array'],
                ],
            ],
        ];
    }

    public function test_safe_production_composite_continues_through_success_path(): void
    {
        Mail::fake();
        $payment = $this->verifiedPayment();
        $this->configureProductionMailer('delivery', [
            'delivery' => ['transport' => 'failover', 'mailers' => ['primary', 'backup']],
            'primary' => ['transport' => 'smtp'],
            'backup' => ['transport' => 'smtp'],
        ]);

        $this->artisan('bookings:send-pending-tickets')->assertSuccessful();

        $delivery = BookingTicketDelivery::query()->sole();
        $this->assertSame(BookingTicketDelivery::STATUS_SENT, $delivery->status);
        $this->assertSame(1, $delivery->attempts);
        $this->assertNotNull($payment->booking->fresh()->ticket_emailed_at);
        Mail::assertSent(BookingTicketMail::class, 1);
    }

    private function verifiedPayment()
    {
        $payment = $this->pendingPayment();
        $this->postJson(route('payments.zalopay.callback'), $this->callbackBody($payment))
            ->assertJsonPath('return_code', 1);

        return $payment->fresh();
    }

    private function configureProductionMailer(string $default, array $mailers): void
    {
        foreach ($mailers as &$mailer) {
            if (is_array($mailer) && ($mailer['transport'] ?? null) === 'smtp') {
                $mailer += ['host' => 'smtp.example.test', 'port' => 2525];
            }
        }
        unset($mailer);

        $this->app->detectEnvironment(static fn (): string => 'production');
        config([
            'mail.default' => $default,
            'mail.driver' => null,
            'mail.mailers' => $mailers,
            'mail.production_allowed_transports' => 'smtp',
        ]);
    }

    private function fragmentToken(string $url): ?string
    {
        parse_str((string) parse_url($url, PHP_URL_FRAGMENT), $fragment);
        $token = $fragment['token'] ?? null;

        return is_string($token) ? $token : null;
    }
}
