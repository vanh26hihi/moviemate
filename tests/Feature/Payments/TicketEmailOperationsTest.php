<?php

namespace Tests\Feature\Payments;

use App\Mail\BookingTicketMail;
use App\Models\BookingTicketDelivery;
use App\Services\BookingTokenService;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Mail;

class TicketEmailOperationsTest extends PaymentTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->seedRbac();
    }

    public function test_log_mailer_is_fail_closed_before_outbox_claim(): void
    {
        Mail::fake();
        $payment = $this->verifiedPayment();
        $before = BookingTicketDelivery::query()->sole()->getRawOriginal();
        config(['mail.default' => 'log']);

        $this->artisan('bookings:send-pending-tickets')
            ->expectsOutputToContain('MAILER_IS_LOG_ONLY')
            ->assertFailed();

        $this->assertSame($before, BookingTicketDelivery::query()->sole()->getRawOriginal());
        $this->assertNull($payment->booking->fresh()->ticket_emailed_at);
        Mail::assertNothingSent();
    }

    public function test_success_page_does_not_describe_a_log_transport_record_as_inbox_delivery(): void
    {
        Mail::fake();
        [$owner, $payment] = $this->verifiedOwnerPayment();
        $this->artisan('bookings:send-pending-tickets')->assertSuccessful();
        config(['mail.default' => 'log']);

        $this->actingAs($owner)->get(route('user.bookings.success', $payment->booking))
            ->assertOk()
            ->assertSee('Cấu hình gửi thư chưa sẵn sàng')
            ->assertSee('Hãy cấu hình mail rồi yêu cầu gửi lại.');
    }

    public function test_owner_can_requeue_a_sent_delivery_once_without_changing_recipient(): void
    {
        Mail::fake();
        [$owner, $payment] = $this->verifiedOwnerPayment();
        $this->artisan('bookings:send-pending-tickets')->assertSuccessful();
        $sent = BookingTicketDelivery::query()->sole();
        $this->assertSame(BookingTicketDelivery::STATUS_SENT, $sent->status);

        $foreignRecipient = 'attacker@example.test';
        $this->actingAs($owner)->post(route('user.bookings.ticket-email.resend', $payment->booking), [
            'email' => $foreignRecipient,
        ])->assertRedirect()->assertSessionHas('success', 'Yêu cầu gửi lại vé đã được ghi nhận.');

        $this->actingAs($owner)->post(route('user.bookings.ticket-email.resend', $payment->booking))
            ->assertRedirect();

        $delivery = BookingTicketDelivery::query()->sole();
        $this->assertSame(BookingTicketDelivery::STATUS_PENDING, $delivery->status);
        $this->assertSame(1, $delivery->attempts);
        $this->assertNull($delivery->sent_at);

        Mail::fake();
        $this->artisan('bookings:send-pending-tickets')->assertSuccessful();
        Mail::assertSent(BookingTicketMail::class, function (BookingTicketMail $mail) use ($foreignRecipient, $payment): bool {
            return $mail->hasTo($payment->booking->recipient_email)
                && ! $mail->hasTo($foreignRecipient);
        });
        $this->assertSame(2, BookingTicketDelivery::query()->sole()->attempts);
    }

    public function test_manual_resend_is_owner_only_and_rate_limited(): void
    {
        [$owner, $payment] = $this->verifiedOwnerPayment();
        $other = $this->userWithRole('user');
        $staff = $this->userWithRole('staff');

        $this->actingAs($other)->post(route('user.bookings.ticket-email.resend', $payment->booking))
            ->assertForbidden();
        $this->actingAs($staff)->post(route('user.bookings.ticket-email.resend', $payment->booking))
            ->assertForbidden();

        for ($attempt = 1; $attempt <= 3; $attempt++) {
            $this->actingAs($owner)->post(route('user.bookings.ticket-email.resend', $payment->booking))
                ->assertRedirect();
        }
        $this->actingAs($owner)->post(route('user.bookings.ticket-email.resend', $payment->booking))
            ->assertTooManyRequests();
        $this->assertDatabaseCount('booking_ticket_deliveries', 1);
    }

    public function test_guest_resend_requires_the_exact_booking_capability(): void
    {
        $scenario = $this->bookingScenario(false);
        $checkoutToken = app(BookingTokenService::class)->issueCheckoutToken();
        $reservation = $this->reserve($scenario, [$scenario['seats'][0]->id], token: $checkoutToken);
        $payment = $this->pendingPayment($reservation->booking);
        $this->postJson(route('payments.zalopay.callback'), $this->callbackBody($payment))
            ->assertJsonPath('return_code', 1);
        $booking = $reservation->booking->fresh();

        $this->withServerVariables(['REMOTE_ADDR' => '192.0.2.10'])
            ->post(route('user.bookings.ticket-email.resend', $booking))->assertNotFound();
        $this->post(route('user.bookings.access.exchange', $booking), [
            'token' => $reservation->guestAccessToken,
            'destination' => 'ticket',
        ])->assertOk();
        $this->withServerVariables(['REMOTE_ADDR' => '192.0.2.11'])
            ->post(route('user.bookings.ticket-email.resend', $booking))
            ->assertRedirect()
            ->assertSessionHas('success', 'Yêu cầu gửi lại vé đã được ghi nhận.');

        $other = $this->payableBooking();
        $this->withServerVariables(['REMOTE_ADDR' => '192.0.2.12'])
            ->post(route('user.bookings.ticket-email.resend', $other))->assertNotFound();
    }

    public function test_unpaid_or_review_booking_cannot_queue_a_resend(): void
    {
        $owner = $this->userWithRole('user');
        $scenario = $this->bookingScenario(false);
        $booking = $this->reserve($scenario, [$scenario['seats'][0]->id], $owner->id)->booking;
        $this->pendingPayment($booking, ['status' => 'review']);

        $this->actingAs($owner)->post(route('user.bookings.ticket-email.resend', $booking))
            ->assertNotFound();
        $this->assertDatabaseCount('booking_ticket_deliveries', 0);
    }

    public function test_an_active_claim_cannot_be_sent_by_a_second_worker(): void
    {
        Mail::fake();
        $this->verifiedPayment();
        BookingTicketDelivery::query()->update([
            'status' => BookingTicketDelivery::STATUS_PROCESSING,
            'attempts' => 1,
            'processing_started_at' => now(),
            'lease_expires_at' => now()->addMinutes(5),
        ]);

        $this->artisan('bookings:send-pending-tickets')->assertSuccessful();

        Mail::assertNothingSent();
        $this->assertSame(1, BookingTicketDelivery::query()->sole()->attempts);
        $this->assertSame(BookingTicketDelivery::STATUS_PROCESSING, BookingTicketDelivery::query()->sole()->status);
    }

    public function test_diagnostics_are_non_mutating_mask_secrets_and_fail_for_log_transport(): void
    {
        $payment = $this->verifiedPayment();
        $booking = $payment->booking->fresh();
        $secret = 'diagnostic-secret-must-not-leak';
        $booking->forceFill([
            'guest_access_token_hash' => hash('sha256', $secret),
            'ticket_email_token_hash' => hash('sha256', 'email-'.$secret),
        ])->save();
        config([
            'mail.default' => 'log',
            'mail.mailers.log.password' => $secret,
        ]);
        $before = BookingTicketDelivery::query()->sole()->getRawOriginal();

        $exitCode = Artisan::call('tickets:mail-diagnostics', ['--booking' => $booking->id]);
        $output = Artisan::output();

        $this->assertSame(1, $exitCode);
        $this->assertStringContainsString('MAILER_IS_LOG_ONLY', $output);
        $this->assertStringNotContainsString($secret, $output);
        $this->assertStringNotContainsString(hash('sha256', $secret), $output);
        $this->assertStringContainsString('no (sync job runs after the HTTP response)', $output);
        $this->assertSame($before, BookingTicketDelivery::query()->sole()->getRawOriginal());
    }

    public function test_mailable_contains_ticket_details_without_tracking_or_secrets(): void
    {
        Mail::fake();
        $payment = $this->verifiedPayment();
        $this->artisan('bookings:send-pending-tickets')->assertSuccessful();

        Mail::assertSent(BookingTicketMail::class, function (BookingTicketMail $mail) use ($payment): bool {
            $html = $mail->render();

            return str_contains($html, $payment->booking->booking_code)
                && str_contains($html, 'Booking Foundation Movie')
                && str_contains($html, 'Test booking room')
                && str_contains($html, '50.000 VNĐ')
                && str_contains($html, 'Xem vé xem phim')
                && str_contains($html, 'Mã QR xác minh vé MovieMate')
                && ! str_contains($html, 'Lưu PDF')
                && ! str_contains($html, 'tracking')
                && ! str_contains($html, 'VNPAY_HASH_SECRET')
                && ! str_contains($html, 'api.qrserver.com');
        });
    }

    private function verifiedPayment()
    {
        $payment = $this->pendingPayment();
        $this->postJson(route('payments.zalopay.callback'), $this->callbackBody($payment))
            ->assertJsonPath('return_code', 1);

        return $payment->fresh();
    }

    private function verifiedOwnerPayment(): array
    {
        $owner = $this->userWithRole('user');
        $scenario = $this->bookingScenario(false);
        $booking = $this->reserve($scenario, [$scenario['seats'][0]->id], $owner->id)->booking;
        $payment = $this->pendingPayment($booking);
        $this->postJson(route('payments.zalopay.callback'), $this->callbackBody($payment))
            ->assertJsonPath('return_code', 1);

        return [$owner, $payment->fresh()];
    }
}
