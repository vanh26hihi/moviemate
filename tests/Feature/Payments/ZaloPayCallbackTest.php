<?php

namespace Tests\Feature\Payments;

use App\Models\BookingTicketDelivery;
use App\Models\Payment;
use App\Services\BookingCheckoutService;
use App\Services\BookingExpirationService;
use App\Services\BookingTokenService;

class ZaloPayCallbackTest extends PaymentTestCase
{
    public function test_valid_callback_marks_payment_success(): void
    {
        $payment = $this->pendingPayment();

        $this->postJson(route('payments.zalopay.callback'), $this->callbackBody($payment))
            ->assertOk()
            ->assertExactJson(['return_code' => 1, 'return_message' => 'Success']);

        $payment->refresh();
        $this->assertSame(Payment::STATUS_SUCCESS, $payment->status);
        $this->assertNotNull($payment->verified_at);
        $this->assertNotNull($payment->callback_payload_hash);
    }

    public function test_valid_callback_marks_booking_paid_without_changing_total(): void
    {
        $payment = $this->pendingPayment();
        $originalTotal = $payment->booking->getRawOriginal('total_amount');

        $this->postJson(route('payments.zalopay.callback'), $this->callbackBody($payment))->assertOk();

        $booking = $payment->booking->fresh();
        $this->assertSame('paid', $booking->payment_status);
        $this->assertSame('paid', $booking->booking_status);
        $this->assertNotNull($booking->paid_at);
        $this->assertSame($originalTotal, $booking->getRawOriginal('total_amount'));
    }

    public function test_invalid_mac_makes_no_database_change(): void
    {
        $payment = $this->pendingPayment();

        $this->postJson(route('payments.zalopay.callback'), $this->callbackBody($payment, [], false))
            ->assertOk()
            ->assertExactJson(['return_code' => 2, 'return_message' => 'Invalid MAC']);

        $this->assertSame(Payment::STATUS_PENDING, $payment->fresh()->status);
        $this->assertNull($payment->fresh()->callback_received_at);
        $this->assertSame('unpaid', $payment->booking->fresh()->payment_status);
    }

    public function test_wrong_app_id_makes_no_database_change(): void
    {
        $payment = $this->pendingPayment();

        $this->postJson(route('payments.zalopay.callback'), $this->callbackBody($payment, ['app_id' => 9999]))
            ->assertOk()
            ->assertJsonPath('return_code', 2);

        $this->assertSame(Payment::STATUS_PENDING, $payment->fresh()->status);
        $this->assertNull($payment->fresh()->callback_payload_hash);
    }

    public function test_wrong_app_trans_id_makes_no_database_change(): void
    {
        $payment = $this->pendingPayment();

        $this->postJson(route('payments.zalopay.callback'), $this->callbackBody($payment, [
            'app_trans_id' => '260804_unknown',
        ]))->assertOk()->assertJsonPath('return_code', 2);

        $this->assertSame(Payment::STATUS_PENDING, $payment->fresh()->status);
        $this->assertSame(1, Payment::query()->count());
    }

    public function test_amount_mismatch_moves_attempt_to_review_and_keeps_booking_pending(): void
    {
        $payment = $this->pendingPayment();

        $this->postJson(route('payments.zalopay.callback'), $this->callbackBody($payment, ['amount' => 49999]))
            ->assertOk()
            ->assertJsonPath('return_code', 2);

        $this->assertSame(Payment::STATUS_REVIEW, $payment->fresh()->status);
        $this->assertSame('amount_mismatch', $payment->fresh()->failure_reason);
        $this->assertSame('unpaid', $payment->booking->fresh()->payment_status);
        $this->assertSame('pending_payment', $payment->booking->fresh()->booking_status);
    }

    public function test_duplicate_callback_is_idempotent(): void
    {
        $payment = $this->pendingPayment();
        $body = $this->callbackBody($payment, ['zp_trans_id' => 123456789]);

        $this->postJson(route('payments.zalopay.callback'), $body)->assertJsonPath('return_code', 1);
        $paidAt = $payment->fresh()->paid_at?->format('Y-m-d H:i:s.u');
        $this->postJson(route('payments.zalopay.callback'), $body)->assertJsonPath('return_code', 1);

        $this->assertSame(1, Payment::query()->count());
        $this->assertSame($paidAt, $payment->fresh()->paid_at?->format('Y-m-d H:i:s.u'));
        $this->assertSame('paid', $payment->booking->fresh()->payment_status);
        $this->assertDatabaseCount('booking_ticket_deliveries', 1);
        $this->assertEmpty(session('guest_booking_capabilities', []));
    }

    public function test_verified_success_creates_one_pending_outbox_row(): void
    {
        $payment = $this->pendingPayment();
        $body = $this->callbackBody($payment, ['zp_trans_id' => 918273645]);

        $this->postJson(route('payments.zalopay.callback'), $body)->assertOk();
        $this->postJson(route('payments.zalopay.callback'), $body)->assertOk();

        $delivery = BookingTicketDelivery::query()->sole();
        $this->assertSame($payment->booking_id, $delivery->booking_id);
        $this->assertSame(BookingTicketDelivery::STATUS_PENDING, $delivery->status);
        $this->assertSame(0, $delivery->attempts);
    }

    public function test_unknown_payment_creates_no_row(): void
    {
        $payment = $this->pendingPayment();
        $count = Payment::query()->count();

        $this->postJson(route('payments.zalopay.callback'), $this->callbackBody($payment, [
            'app_trans_id' => '260804_not_found',
        ]))->assertJsonPath('return_code', 2);

        $this->assertSame($count, Payment::query()->count());
    }

    public function test_duplicate_zp_trans_id_is_rejected(): void
    {
        $existing = $this->pendingPayment(overrides: [
            'status' => Payment::STATUS_SUCCESS,
            'zp_trans_id' => '888888',
            'verified_at' => now(),
            'paid_at' => now(),
        ]);
        $payment = $this->pendingPayment();

        $this->postJson(route('payments.zalopay.callback'), $this->callbackBody($payment, [
            'zp_trans_id' => 888888,
        ]))->assertJsonPath('return_code', 2);

        $this->assertSame(Payment::STATUS_REVIEW, $payment->fresh()->status);
        $this->assertSame('duplicate_zp_trans_id', $payment->fresh()->failure_reason);
        $this->assertSame(Payment::STATUS_SUCCESS, $existing->fresh()->status);
    }

    public function test_callback_requires_no_authentication_or_session(): void
    {
        $payment = $this->pendingPayment();

        $response = $this->withUnencryptedCookies([])
            ->postJson(route('payments.zalopay.callback'), $this->callbackBody($payment));

        $response->assertOk()->assertJsonPath('return_code', 1);
        $this->assertGuest();
    }

    public function test_missing_callback_configuration_returns_formal_permanent_failure(): void
    {
        config(['payment.zalopay.app_id' => null]);

        $this->postJson(route('payments.zalopay.callback'), [
            'type' => 1,
            'data' => '{}',
            'mac' => str_repeat('0', 64),
        ])->assertOk()->assertExactJson([
            'return_code' => 2,
            'return_message' => 'Merchant configuration rejected',
        ]);
    }

    public function test_csrf_exemption_is_limited_to_the_exact_callback_path(): void
    {
        $bootstrap = file_get_contents(base_path('bootstrap/app.php'));

        $this->assertStringContainsString("'payments/zalopay/callback'", $bootstrap);
        $this->assertStringNotContainsString("'payments/*'", $bootstrap);
        $this->assertStringNotContainsString("'payments/zalopay/*'", $bootstrap);
    }

    public function test_late_callback_moves_payment_to_review(): void
    {
        $booking = $this->payableBooking(['expires_at' => now()->subMinute(), 'booking_status' => 'expired']);
        $booking->bookingSeats()->update(['active_lock_key' => null]);
        $payment = $this->pendingPayment($booking);

        $this->postJson(route('payments.zalopay.callback'), $this->callbackBody($payment))
            ->assertJsonPath('return_code', 2);

        $this->assertSame(Payment::STATUS_REVIEW, $payment->fresh()->status);
        $this->assertSame('late_payment_after_expiration', $payment->fresh()->failure_reason);
    }

    public function test_expiration_wins_before_callback_and_never_fulfils_booking(): void
    {
        $booking = $this->payableBooking(['expires_at' => now()->subMinute()]);
        $payment = $this->pendingPayment($booking);

        $this->assertTrue(app(BookingExpirationService::class)->expire($booking->id));
        $this->postJson(route('payments.zalopay.callback'), $this->callbackBody($payment))
            ->assertJsonPath('return_code', 2);

        $this->assertSame('expired', $booking->fresh()->booking_status);
        $this->assertSame('unpaid', $booking->fresh()->payment_status);
        $this->assertSame(Payment::STATUS_REVIEW, $payment->fresh()->status);
        $this->assertSame('late_payment_after_expiration', $payment->fresh()->failure_reason);
        $this->assertDatabaseCount('booking_ticket_deliveries', 0);
    }

    public function test_callback_wins_before_expiration_and_expiration_skips_paid_booking(): void
    {
        $payment = $this->pendingPayment();
        $booking = $payment->booking;

        $this->postJson(route('payments.zalopay.callback'), $this->callbackBody($payment))
            ->assertJsonPath('return_code', 1);
        $booking->forceFill(['expires_at' => now()->subMinute()])->save();

        $this->assertFalse(app(BookingExpirationService::class)->expire($booking->id));
        $this->assertSame('paid', $booking->fresh()->booking_status);
        $this->assertSame('paid', $booking->fresh()->payment_status);
        $this->assertSame(Payment::STATUS_SUCCESS, $payment->fresh()->status);
        $this->assertDatabaseCount('booking_ticket_deliveries', 1);
    }

    public function test_late_callback_after_same_seat_is_rebooked_moves_old_payment_to_review(): void
    {
        $booking = $this->payableBooking(['expires_at' => now()->subMinute()]);
        $seatId = $booking->bookingSeats()->value('seat_id');
        $payment = $this->pendingPayment($booking);
        $this->assertTrue(app(BookingExpirationService::class)->expire($booking->id));

        $replacement = app(BookingCheckoutService::class)->createPendingBooking(
            $booking->showtime_id,
            [$seatId],
            null,
            'replacement@example.test',
            app(BookingTokenService::class)->issueCheckoutToken(),
        )->booking;

        $this->postJson(route('payments.zalopay.callback'), $this->callbackBody($payment))
            ->assertJsonPath('return_code', 2);

        $this->assertSame(Payment::STATUS_REVIEW, $payment->fresh()->status);
        $this->assertSame('late_payment_after_expiration', $payment->fresh()->failure_reason);
        $this->assertSame('expired', $booking->fresh()->booking_status);
        $this->assertSame('pending_payment', $replacement->fresh()->booking_status);
        $this->assertNotNull($replacement->bookingSeats()->value('active_lock_key'));
        $this->assertDatabaseCount('booking_ticket_deliveries', 0);
    }

    public function test_late_callback_does_not_fulfill_expired_booking(): void
    {
        $booking = $this->payableBooking(['expires_at' => now()->subMinute(), 'booking_status' => 'expired']);
        $payment = $this->pendingPayment($booking);

        $this->postJson(route('payments.zalopay.callback'), $this->callbackBody($payment))->assertOk();

        $this->assertSame('expired', $booking->fresh()->booking_status);
        $this->assertSame('unpaid', $booking->fresh()->payment_status);
        $this->assertNull($booking->fresh()->paid_at);
    }

    public function test_late_callback_never_sends_ticket_email(): void
    {
        $booking = $this->payableBooking(['expires_at' => now()->subMinute(), 'booking_status' => 'expired']);
        $payment = $this->pendingPayment($booking);

        $this->postJson(route('payments.zalopay.callback'), $this->callbackBody($payment))->assertOk();

        $this->assertDatabaseCount('booking_ticket_deliveries', 0);
        $this->assertNull($booking->fresh()->ticket_emailed_at);
    }
}
