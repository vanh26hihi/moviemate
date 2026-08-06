<?php

namespace Tests\Feature\Payments;

use App\Models\Payment;
use App\Services\Payments\PaymentReturnTokenService;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

class PayOsReturnAndCancellationTest extends PayOsPaymentTestCase
{
    public function test_forged_browser_paid_status_queries_provider_and_keeps_pending_state(): void
    {
        $payment = $this->payOsPayment();
        Http::preventStrayRequests();
        Http::fake(fn () => Http::response($this->providerEnvelope($this->queryData($payment))));

        $response = $this->get(route('payments.payos.return', [
            'orderCode' => $payment->order_code,
            'status' => 'PAID',
            'state' => app(PaymentReturnTokenService::class)->issue($payment),
        ]));

        $response->assertRedirect(route('payments.payos.return', ['attempt' => $payment->id]));
        $this->get($response->headers->get('Location'))
            ->assertOk()
            ->assertSee('Đang xác minh kết quả thanh toán');
        $this->assertSame(Payment::STATUS_PENDING, $payment->fresh()->status);
        $this->assertSame('unpaid', $payment->booking->fresh()->payment_status);
        Http::assertSent(fn (Request $request): bool => $request->method() === 'GET'
            && str_ends_with($request->url(), '/v2/payment-requests/'.$payment->order_code));
    }

    public function test_valid_return_query_paid_finalizes_immediately(): void
    {
        $payment = $this->payOsPayment();
        Http::fake(fn () => Http::response($this->providerEnvelope(
            $this->queryData($payment, 'PAID'),
        )));

        $response = $this->get(route('payments.payos.return', [
            'orderCode' => $payment->order_code,
            'state' => app(PaymentReturnTokenService::class)->issue($payment),
        ]));

        $response->assertRedirect();
        $this->get($response->headers->get('Location'))
            ->assertOk()
            ->assertSee('Đặt vé thành công');
        $this->assertSame(Payment::STATUS_SUCCESS, $payment->fresh()->status);
        $this->assertSame('paid', $payment->booking->fresh()->booking_status);
        $this->assertDatabaseCount('booking_ticket_deliveries', 1);
    }

    public function test_query_amount_mismatch_enters_review_and_never_pays(): void
    {
        $payment = $this->payOsPayment();
        Http::fake(fn () => Http::response($this->providerEnvelope(
            $this->queryData($payment, 'PAID', ['amount' => $payment->amount + 1]),
        )));

        $this->get(route('payments.payos.return', [
            'orderCode' => $payment->order_code,
            'state' => app(PaymentReturnTokenService::class)->issue($payment),
        ]))->assertRedirect();

        $this->assertSame(Payment::STATUS_REVIEW, $payment->fresh()->status);
        $this->assertSame('amount_mismatch', $payment->fresh()->failure_reason);
        $this->assertSame('unpaid', $payment->booking->fresh()->payment_status);
        $this->assertDatabaseCount('booking_ticket_deliveries', 0);
    }

    public function test_query_paid_then_webhook_paid_is_idempotent(): void
    {
        $payment = $this->payOsPayment();
        Http::fake(fn () => Http::response($this->providerEnvelope(
            $this->queryData($payment, 'PAID'),
        )));
        $this->get(route('payments.payos.return', [
            'orderCode' => $payment->order_code,
            'state' => app(PaymentReturnTokenService::class)->issue($payment),
        ]))->assertRedirect();

        $this->postJson(route('payments.payos.webhook'), $this->webhookBody($payment))->assertOk();

        $this->assertSame(Payment::STATUS_SUCCESS, $payment->fresh()->status);
        $this->assertSame('paid', $payment->booking->fresh()->booking_status);
        $this->assertDatabaseCount('booking_ticket_deliveries', 1);
    }

    public function test_return_query_timeout_retains_pending_state_and_seats(): void
    {
        $payment = $this->payOsPayment();
        Http::fake(['*' => fn () => Http::failedConnection('timeout')]);

        $response = $this->get(route('payments.payos.return', [
            'orderCode' => $payment->order_code,
            'status' => 'PAID',
            'state' => app(PaymentReturnTokenService::class)->issue($payment),
        ]));

        $response->assertRedirect(route('payments.payos.return', ['attempt' => $payment->id]));
        $this->assertSame(Payment::STATUS_PENDING, $payment->fresh()->status);
        $this->assertSame('pending_payment', $payment->booking->fresh()->booking_status);
        $this->assertSame(1, $payment->booking->bookingSeats()->whereNotNull('active_lock_key')->count());
    }

    public function test_forged_cancel_return_cannot_release_without_verified_cancelled_query(): void
    {
        $payment = $this->payOsPayment();
        Http::fake(fn () => Http::response($this->providerEnvelope($this->queryData($payment))));

        $response = $this->get(route('payments.payos.cancel', [
            'orderCode' => $payment->order_code,
            'cancel' => 'true',
            'status' => 'CANCELLED',
            'state' => app(PaymentReturnTokenService::class)->issue($payment),
        ]));

        $response->assertRedirect(route('payments.payos.cancel', ['attempt' => $payment->id]));
        $this->assertSame(Payment::STATUS_PENDING, $payment->fresh()->status);
        $this->assertSame('pending_payment', $payment->booking->fresh()->booking_status);
        $this->assertSame(1, $payment->booking->bookingSeats()->whereNotNull('active_lock_key')->count());
    }

    public function test_explicit_post_cancellation_verifies_provider_before_release(): void
    {
        $this->seedRbac();
        $user = $this->userWithRole('user');
        $booking = $this->payableBooking(['user_id' => $user->id]);
        $payment = $this->payOsPayment($booking);
        Http::fake(fn () => Http::response($this->providerEnvelope(
            $this->queryData($payment, 'CANCELLED'),
        )));

        $this->actingAs($user)
            ->post(route('payments.payos.cancel-attempt', $booking))
            ->assertRedirect(route('user.bookings.history'));

        $this->assertSame(Payment::STATUS_FAILED, $payment->fresh()->status);
        $this->assertSame('cancelled', $booking->fresh()->booking_status);
        $this->assertSame(0, $booking->bookingSeats()->whereNotNull('active_lock_key')->count());
        Http::assertSent(fn (Request $request): bool => $request->method() === 'POST'
            && str_ends_with($request->url(), '/v2/payment-requests/'.$payment->order_code.'/cancel'));
    }

    public function test_return_requires_attempt_scoped_state_and_wrong_customer_cannot_query(): void
    {
        Http::fake();
        $this->seedRbac();
        $owner = $this->userWithRole('user');
        $other = $this->userWithRole('user');
        $payment = $this->payOsPayment($this->payableBooking(['user_id' => $owner->id]));

        $this->get(route('payments.payos.return', [
            'orderCode' => $payment->order_code,
            'status' => 'PAID',
        ]))->assertNotFound();
        $this->actingAs($other)->get(route('payments.payos.return', [
            'orderCode' => $payment->order_code,
            'status' => 'PAID',
        ]))->assertForbidden();

        $this->assertSame(Payment::STATUS_PENDING, $payment->fresh()->status);
        Http::assertNothingSent();
    }
}
