<?php

namespace Tests\Feature\Payments;

use App\Models\BookingTicketDelivery;
use App\Models\Payment;
use Illuminate\Support\Facades\Log;

class PayOsWebhookTest extends PayOsPaymentTestCase
{
    public function test_valid_signed_paid_webhook_finalizes_once_and_duplicate_is_idempotent(): void
    {
        $payment = $this->payOsPayment();
        $body = $this->webhookBody($payment);

        $this->postJson(route('payments.payos.webhook'), $body)
            ->assertOk()->assertHeaderMissing('Set-Cookie')->assertExactJson(['message' => 'OK']);
        $paidAt = $payment->fresh()->paid_at?->format('Y-m-d H:i:s.u');
        $this->postJson(route('payments.payos.webhook'), $body)
            ->assertOk()->assertExactJson(['message' => 'OK']);

        $payment->refresh();
        $this->assertSame(Payment::STATUS_SUCCESS, $payment->status);
        $this->assertNotNull($payment->verified_at);
        $this->assertSame('TF230204212323', $payment->transaction_id);
        $this->assertSame($paidAt, $payment->paid_at?->format('Y-m-d H:i:s.u'));
        $this->assertSame('paid', $payment->booking->fresh()->booking_status);
        $this->assertSame(1, BookingTicketDelivery::query()->where('booking_id', $payment->booking_id)->count());
        $this->assertSame(1, Payment::query()->count());
        $this->assertDatabaseCount('activity_logs', 1);
        $this->assertDatabaseHas('activity_logs', [
            'action' => 'payment.verified',
            'subject_id' => (string) $payment->id,
        ]);
    }

    public function test_invalid_signature_is_rejected_before_any_mutation_or_raw_logging(): void
    {
        Log::spy();
        $payment = $this->payOsPayment();
        $body = $this->webhookBody($payment, [], false);

        $this->postJson(route('payments.payos.webhook'), $body)
            ->assertStatus(400)->assertExactJson(['message' => 'Invalid webhook']);

        $this->assertSame(Payment::STATUS_PENDING, $payment->fresh()->status);
        $this->assertNull($payment->fresh()->callback_received_at);
        $this->assertSame('unpaid', $payment->booking->fresh()->payment_status);
        Log::shouldNotHaveReceived('warning');
        Log::shouldNotHaveReceived('error');
    }

    public function test_exact_amount_order_code_payment_link_and_currency_are_required(): void
    {
        foreach ([
            ['amount' => 49999, 'reason' => 'amount_mismatch'],
            ['paymentLinkId' => 'differentPaymentLink123', 'reason' => 'payos_payment_link_mismatch'],
            ['currency' => 'USD', 'reason' => 'payos_currency_mismatch'],
        ] as $case) {
            $payment = $this->payOsPayment();
            $overrides = $case;
            unset($overrides['reason']);

            $this->postJson(route('payments.payos.webhook'), $this->webhookBody($payment, $overrides))
                ->assertOk();

            $this->assertSame(Payment::STATUS_REVIEW, $payment->fresh()->status);
            $this->assertSame($case['reason'], $payment->fresh()->failure_reason);
            $this->assertSame('unpaid', $payment->booking->fresh()->payment_status);
            $payment->forceFill(['status' => Payment::STATUS_FAILED])->save();
            $payment->booking->forceFill(['booking_status' => 'cancelled'])->save();
        }
    }

    public function test_wrong_order_code_does_not_disclose_or_mutate_another_payment(): void
    {
        $payment = $this->payOsPayment();
        $body = $this->webhookBody($payment, ['orderCode' => (int) $payment->order_code + 1]);

        $this->postJson(route('payments.payos.webhook'), $body)
            ->assertOk()->assertExactJson(['message' => 'OK']);

        $this->assertSame(Payment::STATUS_PENDING, $payment->fresh()->status);
        $this->assertNull($payment->fresh()->callback_received_at);
    }

    public function test_pending_and_processing_retain_seats_while_unknown_enters_review(): void
    {
        foreach ([
            'PENDING' => Payment::STATUS_PENDING,
            'PROCESSING' => Payment::STATUS_PROCESSING,
            'SOMETHING_NEW' => Payment::STATUS_REVIEW,
        ] as $providerStatus => $expected) {
            $payment = $this->payOsPayment();
            $body = $this->webhookBody($payment, ['status' => $providerStatus]);

            $this->postJson(route('payments.payos.webhook'), $body)->assertOk();

            $this->assertSame($expected, $payment->fresh()->status);
            $this->assertSame('pending_payment', $payment->booking->fresh()->booking_status);
            $this->assertSame(1, $payment->booking->bookingSeats()->whereNotNull('active_lock_key')->count());
            $payment->forceFill(['status' => Payment::STATUS_FAILED])->save();
            $payment->booking->forceFill(['booking_status' => 'cancelled'])->save();
        }
    }

    public function test_verified_cancelled_releases_eligible_seats_and_duplicate_does_not_release_twice(): void
    {
        $payment = $this->payOsPayment();
        $body = $this->webhookBody($payment, ['status' => 'CANCELLED']);

        $this->postJson(route('payments.payos.webhook'), $body)->assertOk();
        $this->postJson(route('payments.payos.webhook'), $body)->assertOk();

        $this->assertSame(Payment::STATUS_FAILED, $payment->fresh()->status);
        $this->assertSame('payos_cancelled', $payment->fresh()->failure_reason);
        $this->assertSame('cancelled', $payment->booking->fresh()->booking_status);
        $this->assertSame(0, $payment->booking->bookingSeats()->whereNotNull('active_lock_key')->count());
        $this->assertDatabaseCount('booking_ticket_deliveries', 0);
    }

    public function test_stale_cancelled_after_paid_cannot_downgrade_or_release(): void
    {
        $payment = $this->payOsPayment();
        $this->postJson(route('payments.payos.webhook'), $this->webhookBody($payment))->assertOk();

        $this->postJson(
            route('payments.payos.webhook'),
            $this->webhookBody($payment, ['status' => 'CANCELLED']),
        )->assertOk();

        $this->assertSame(Payment::STATUS_SUCCESS, $payment->fresh()->status);
        $this->assertSame('paid', $payment->booking->fresh()->booking_status);
        $this->assertSame(1, $payment->booking->bookingSeats()->whereNotNull('active_lock_key')->count());
        $this->assertDatabaseCount('booking_ticket_deliveries', 1);
    }

    public function test_late_paid_after_verified_cancel_enters_review_without_reissuing_seats_or_ticket(): void
    {
        $payment = $this->payOsPayment();
        $this->postJson(
            route('payments.payos.webhook'),
            $this->webhookBody($payment, ['status' => 'CANCELLED']),
        )->assertOk();

        $this->postJson(route('payments.payos.webhook'), $this->webhookBody($payment))->assertOk();

        $this->assertSame(Payment::STATUS_REVIEW, $payment->fresh()->status);
        $this->assertSame('late_paid_after_payos_cancelled', $payment->fresh()->failure_reason);
        $this->assertSame('cancelled', $payment->booking->fresh()->booking_status);
        $this->assertSame(0, $payment->booking->bookingSeats()->whereNotNull('active_lock_key')->count());
        $this->assertDatabaseCount('booking_ticket_deliveries', 0);
    }

    public function test_webhook_requires_json_has_a_size_limit_and_uses_exact_csrf_exemption(): void
    {
        $this->call('POST', route('payments.payos.webhook'), [], [], [], [
            'CONTENT_TYPE' => 'text/plain',
        ], '{}')->assertStatus(415);
        $this->call('POST', route('payments.payos.webhook'), [], [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], '{broken')->assertStatus(400);
        $this->call('POST', route('payments.payos.webhook'), [], [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], str_repeat('x', 32769))->assertStatus(415);

        $bootstrap = file_get_contents(base_path('bootstrap/app.php'));
        $this->assertSame(1, substr_count($bootstrap, "'payments/payos/webhook'"));
        $this->assertStringNotContainsString("'payments/payos/return'", $bootstrap);
        $this->assertStringNotContainsString("'payments/payos/cancel'", $bootstrap);
    }
}
