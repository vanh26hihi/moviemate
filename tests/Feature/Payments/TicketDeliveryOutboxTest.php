<?php

namespace Tests\Feature\Payments;

use App\Mail\BookingTicketMail;
use App\Models\BookingTicketDelivery;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
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

            return true;
        });
    }

    public function test_mail_failure_keeps_paid_state_and_delivery_retryable(): void
    {
        $payment = $this->verifiedPayment();
        Mail::shouldReceive('to')->once()->andThrow(new RuntimeException('SMTP unavailable'));

        $this->artisan('bookings:send-pending-tickets')->assertSuccessful();

        $delivery = BookingTicketDelivery::query()->sole();
        $this->assertSame(BookingTicketDelivery::STATUS_FAILED, $delivery->status);
        $this->assertNull($delivery->sent_at);
        $this->assertSame(1, $delivery->attempts);
        $this->assertSame('paid', $payment->booking->fresh()->payment_status);
        $this->assertSame('paid', $payment->booking->fresh()->booking_status);
        $this->assertNull($payment->booking->fresh()->ticket_emailed_at);
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

    public function test_log_mailer_is_rejected_in_production_without_losing_outbox(): void
    {
        Log::spy();
        $payment = $this->verifiedPayment();
        $this->app->detectEnvironment(static fn (): string => 'production');
        config(['mail.default' => 'log']);

        $this->artisan('bookings:send-pending-tickets')->assertSuccessful();

        $delivery = BookingTicketDelivery::query()->sole();
        $this->assertSame(BookingTicketDelivery::STATUS_FAILED, $delivery->status);
        $this->assertSame('unsafe_production_mailer', $delivery->last_error_code);
        $this->assertNull($delivery->sent_at);
        $this->assertSame('paid', $payment->booking->fresh()->payment_status);
        $this->assertNull($payment->booking->fresh()->ticket_emailed_at);
        Log::shouldHaveReceived('warning')->withArgs(function (string $message, array $context): bool {
            $serialized = json_encode([$message, $context]);

            return ! str_contains($serialized, 'guest-access')
                && ! str_contains($serialized, '#token=')
                && ! array_key_exists('recipient', $context);
        })->atLeast()->once();
    }

    private function verifiedPayment()
    {
        $payment = $this->pendingPayment();
        $this->postJson(route('payments.zalopay.callback'), $this->callbackBody($payment))
            ->assertJsonPath('return_code', 1);

        return $payment->fresh();
    }
}
