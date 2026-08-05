<?php

namespace Tests\Feature\Payments;

use App\Models\Payment;
use App\Models\PaymentReviewEvent;
use App\Services\BookingCheckoutService;
use App\Services\BookingExpirationService;
use App\Services\BookingTokenService;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

class PaymentReviewResolutionTest extends PaymentTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRbac();
    }

    public function test_unauthorized_user_is_denied_before_provider_query_or_payment_lookup_disclosure(): void
    {
        $payment = $this->reviewPayment();
        Http::fake();

        $this->actingAs($this->userWithRole('user'))
            ->post(route('admin.payment-reviews.resolve', ['paymentId' => $payment->id]))
            ->assertForbidden();
        $this->actingAs($this->userWithRole('user'))
            ->post(route('admin.payment-reviews.resolve', ['paymentId' => 999999]))
            ->assertForbidden();

        Http::assertNothingSent();
        $this->assertDatabaseCount('payment_review_events', 0);
        $this->assertSame(Payment::STATUS_REVIEW, $payment->fresh()->status);
    }

    public function test_authorized_operator_can_query_only_review_payments(): void
    {
        $payment = $this->pendingPayment();
        Http::fake();

        $this->actingAs($this->userWithRole('manager'))
            ->post(route('admin.payment-reviews.resolve', ['paymentId' => $payment->id]))
            ->assertRedirect(route('admin.payment-reviews.index'))
            ->assertSessionHas('error')
            ->assertSessionMissing('payment_review_error');

        Http::assertNothingSent();
        $this->assertDatabaseCount('payment_review_events', 0);
        $this->assertSame(Payment::STATUS_PENDING, $payment->fresh()->status);
    }

    public function test_review_action_queries_existing_order_and_never_calls_create(): void
    {
        $payment = $this->reviewPayment();
        Http::fake(['*' => Http::response(['return_code' => 3, 'return_message' => 'Pending'], 200)]);

        $this->actingAs($this->userWithRole('manager'))
            ->post(route('admin.payment-reviews.resolve', ['paymentId' => $payment->id]))
            ->assertRedirect(route('admin.payment-reviews.index'))
            ->assertSessionHas('success')
            ->assertSessionMissing('payment_review_result');

        Http::assertSentCount(1);
        Http::assertSent(function (Request $request) use ($payment): bool {
            $this->assertSame('https://sb-openapi.zalopay.vn/v2/query', $request->url());
            $this->assertSame(['app_id', 'app_trans_id', 'mac'], array_keys($request->data()));
            $this->assertSame($payment->app_trans_id, $request['app_trans_id']);

            return ! str_ends_with($request->url(), '/v2/create');
        });
        $this->assertSame(Payment::STATUS_REVIEW, $payment->fresh()->status);
    }

    public function test_authoritative_valid_success_fulfills_only_a_valid_booking(): void
    {
        $payment = $this->reviewPayment();
        Http::fake(['*' => Http::response($this->success($payment, 700000001), 200)]);

        $this->actingAs($actor = $this->userWithRole('manager'))
            ->post(route('admin.payment-reviews.resolve', ['paymentId' => $payment->id]))
            ->assertSessionHas('success')
            ->assertSessionMissing('payment_review_result');

        $this->assertSame(Payment::STATUS_SUCCESS, $payment->fresh()->status);
        $this->assertSame('paid', $payment->booking->fresh()->booking_status);
        $this->assertSame('paid', $payment->booking->fresh()->payment_status);
        $this->assertDatabaseHas('booking_ticket_deliveries', ['booking_id' => $payment->booking_id]);
        $this->assertDatabaseHas('payment_review_events', [
            'payment_id' => $payment->id,
            'actor_user_id' => $actor->id,
            'previous_status' => Payment::STATUS_REVIEW,
            'resulting_status' => Payment::STATUS_SUCCESS,
            'provider_result_category' => 'authoritative_success',
            'provider_result_code' => '1',
        ]);
    }

    public function test_expired_booking_remains_unfulfilled_and_creates_no_ticket(): void
    {
        $booking = $this->payableBooking([
            'booking_status' => 'expired',
            'expires_at' => now()->subMinute(),
        ]);
        $booking->bookingSeats()->update(['active_lock_key' => null]);
        $payment = $this->reviewPayment($booking);
        Http::fake(['*' => Http::response($this->success($payment, 700000002), 200)]);

        $this->actingAs($this->userWithRole('manager'))
            ->post(route('admin.payment-reviews.resolve', ['paymentId' => $payment->id]));

        $this->assertSame(Payment::STATUS_REVIEW, $payment->fresh()->status);
        $this->assertSame('late_payment_after_expiration', $payment->fresh()->failure_reason);
        $this->assertSame('expired', $booking->fresh()->booking_status);
        $this->assertSame('unpaid', $booking->fresh()->payment_status);
        $this->assertDatabaseCount('booking_ticket_deliveries', 0);
    }

    public function test_rebooked_seats_remain_unfulfilled_and_create_no_ticket(): void
    {
        $booking = $this->payableBooking(['expires_at' => now()->subMinute()]);
        $seatId = $booking->bookingSeats()->value('seat_id');
        $payment = $this->reviewPayment($booking);
        $this->assertTrue(app(BookingExpirationService::class)->expire($booking->id));
        $replacement = app(BookingCheckoutService::class)->createPendingBooking(
            $booking->showtime_id,
            [$seatId],
            null,
            'replacement-review@example.test',
            app(BookingTokenService::class)->issueCheckoutToken(),
        )->booking;
        Http::fake(['*' => Http::response($this->success($payment, 700000003), 200)]);

        $this->actingAs($this->userWithRole('manager'))
            ->post(route('admin.payment-reviews.resolve', ['paymentId' => $payment->id]));

        $this->assertSame(Payment::STATUS_REVIEW, $payment->fresh()->status);
        $this->assertSame('late_payment_after_expiration', $payment->fresh()->failure_reason);
        $this->assertSame('pending_payment', $replacement->fresh()->booking_status);
        $this->assertNotNull($replacement->bookingSeats()->value('active_lock_key'));
        $this->assertDatabaseCount('booking_ticket_deliveries', 0);
    }

    public function test_amount_and_transaction_mismatches_remain_review(): void
    {
        $amountMismatch = $this->reviewPayment();
        $identityMismatch = $this->reviewPayment(overrides: ['zp_trans_id' => '700000005']);
        Http::fake(function (Request $request) use ($amountMismatch, $identityMismatch) {
            if ($request['app_trans_id'] === $amountMismatch->app_trans_id) {
                return Http::response($this->success($amountMismatch, 700000004, ['amount' => 1]), 200);
            }

            return Http::response($this->success($identityMismatch, 700000006), 200);
        });
        $actor = $this->userWithRole('manager');

        $this->actingAs($actor)->post(route('admin.payment-reviews.resolve', ['paymentId' => $amountMismatch->id]));
        $this->actingAs($actor)->post(route('admin.payment-reviews.resolve', ['paymentId' => $identityMismatch->id]));

        $this->assertSame(Payment::STATUS_REVIEW, $amountMismatch->fresh()->status);
        $this->assertSame('amount_mismatch', $amountMismatch->fresh()->failure_reason);
        $this->assertSame(Payment::STATUS_REVIEW, $identityMismatch->fresh()->status);
        $this->assertSame('zp_trans_id_mismatch', $identityMismatch->fresh()->failure_reason);
        $this->assertDatabaseCount('booking_ticket_deliveries', 0);
    }

    public function test_audit_event_contains_no_secret_or_raw_provider_material(): void
    {
        $payment = $this->reviewPayment();
        Http::fake(['*' => Http::response([
            'return_code' => 3,
            'sub_return_code' => -101,
            'return_message' => 'Pending',
            'mac' => 'raw-secret-mac',
            'key1' => 'raw-secret-key',
            'guest_token' => 'raw-guest-capability',
            'callback_payload' => ['access_token' => 'raw-access-token'],
        ], 200)]);

        $this->actingAs($actor = $this->userWithRole('manager'))
            ->post(route('admin.payment-reviews.resolve', ['paymentId' => $payment->id]));

        $event = PaymentReviewEvent::query()->sole();
        $serialized = json_encode($event->getAttributes(), JSON_THROW_ON_ERROR);
        $this->assertSame($actor->id, $event->actor_user_id);
        $this->assertSame('3/-101', $event->provider_result_code);
        $this->assertStringNotContainsString('raw-secret', $serialized);
        $this->assertStringNotContainsString('raw-guest', $serialized);
        $this->assertStringNotContainsString('raw-access', $serialized);
        $this->assertSame(
            ['id', 'payment_id', 'actor_user_id', 'action', 'previous_status', 'resulting_status', 'provider_result_category', 'provider_result_code', 'created_at'],
            array_keys($event->getAttributes()),
        );
    }

    public function test_scheduled_query_explicitly_excludes_review(): void
    {
        $payment = $this->reviewPayment();
        Http::fake();

        $this->artisan('payments:query-pending')->assertSuccessful()->expectsOutputToContain('Checked: 0');

        Http::assertNothingSent();
        $this->assertSame(Payment::STATUS_REVIEW, $payment->fresh()->status);
        $this->assertDatabaseCount('payment_review_events', 0);
    }

    private function reviewPayment($booking = null, array $overrides = []): Payment
    {
        return $this->pendingPayment($booking, array_merge([
            'status' => Payment::STATUS_REVIEW,
            'failure_reason' => 'manual_review',
            'failed_at' => now(),
        ], $overrides));
    }

    /** @return array<string, mixed> */
    private function success(Payment $payment, int $zpTransId, array $overrides = []): array
    {
        return array_merge([
            'return_code' => 1,
            'return_message' => 'Success',
            'amount' => $payment->amount,
            'zp_trans_id' => $zpTransId,
            'server_time' => (int) floor(microtime(true) * 1000),
        ], $overrides);
    }
}
