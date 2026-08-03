<?php

namespace Tests\Feature\Payments;

use App\Domain\Payments\ZaloPaySigner;
use App\Exceptions\PaymentInitiationException;
use App\Exceptions\ZaloPayTransportException;
use App\Models\Payment;
use App\Services\BookingTokenService;
use App\Services\Payments\PaymentInitiationService;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

class PaymentInitiationTest extends PaymentTestCase
{
    public function test_create_order_sends_the_exact_verified_request(): void
    {
        Http::fake(['*' => Http::response($this->successfulCreate(), 200)]);
        $booking = $this->payableBooking();

        $result = app(PaymentInitiationService::class)->initiate($booking);

        Http::assertSent(function (Request $request) use ($result, $booking): bool {
            $data = $request->data();
            $expectedKeys = [
                'app_id', 'app_user', 'app_trans_id', 'app_time', 'amount',
                'description', 'item', 'embed_data', 'mac',
            ];
            sort($expectedKeys);
            $actualKeys = array_keys($data);
            sort($actualKeys);

            $this->assertSame('https://sb-openapi.zalopay.vn/v2/create', $request->url());
            $this->assertSame($expectedKeys, $actualKeys);
            $this->assertSame(2553, $data['app_id']);
            $this->assertSame($result->payment->app_trans_id, $data['app_trans_id']);
            $this->assertSame(50000, $data['amount']);
            $this->assertSame('[{"booking_code":"'.$booking->booking_code.'","amount":50000}]', $data['item']);
            $this->assertStringContainsString('"redirecturl":', $data['embed_data']);
            $this->assertSame(
                app(ZaloPaySigner::class)->createMac($data, 'test-key1'),
                $data['mac'],
            );

            return true;
        });
    }

    public function test_create_success_stores_order_url_but_keeps_booking_unpaid(): void
    {
        Http::fake(['*' => Http::response($this->successfulCreate(), 200)]);
        $booking = $this->payableBooking();

        $result = app(PaymentInitiationService::class)->initiate($booking);

        $this->assertSame('https://zalopay.example.test/pay', $result->orderUrl);
        $this->assertSame(Payment::STATUS_PENDING, $result->payment->status);
        $this->assertSame('unpaid', $booking->fresh()->payment_status);
        $this->assertNotNull($result->payment->create_response_hash);
        $this->assertSame('token', $result->payment->zp_trans_token);
    }

    public function test_create_rejection_records_failed_attempt_and_never_pays_booking(): void
    {
        Http::fake(['*' => Http::response([
            'return_code' => 2, 'return_message' => 'Rejected',
            'sub_return_code' => -10, 'sub_return_message' => 'Invalid order',
        ], 200)]);
        $booking = $this->payableBooking();

        try {
            app(PaymentInitiationService::class)->initiate($booking);
            $this->fail('Create rejection should throw.');
        } catch (PaymentInitiationException) {
            $this->addToAssertionCount(1);
        }

        $payment = $booking->payments()->firstOrFail();
        $this->assertSame(Payment::STATUS_FAILED, $payment->status);
        $this->assertSame('unpaid', $booking->fresh()->payment_status);
        $this->assertSame('pending_payment', $booking->fresh()->booking_status);
    }

    public function test_network_timeout_keeps_attempt_pending_for_safe_query_and_never_pays_booking(): void
    {
        Http::fake(['*' => Http::failedConnection('timed out')]);
        $booking = $this->payableBooking();

        $this->expectException(ZaloPayTransportException::class);

        try {
            app(PaymentInitiationService::class)->initiate($booking);
        } finally {
            $this->assertSame(Payment::STATUS_PENDING, $booking->payments()->firstOrFail()->status);
            $this->assertSame('create_transport_unknown', $booking->payments()->firstOrFail()->failure_reason);
            $this->assertSame('unpaid', $booking->fresh()->payment_status);
        }
    }

    public function test_duplicate_create_minus_68_queries_existing_attempt_without_issuing_ticket(): void
    {
        Http::fake([
            'https://sb-openapi.zalopay.vn/v2/create' => Http::response([
                'return_code' => 2, 'return_message' => 'Duplicate',
                'sub_return_code' => -68, 'sub_return_message' => 'Duplicate app_trans_id',
            ], 200),
            'https://sb-openapi.zalopay.vn/v2/query' => Http::response([
                'return_code' => 3, 'return_message' => 'Pending',
            ], 200),
        ]);
        $booking = $this->payableBooking();

        $result = app(PaymentInitiationService::class)->initiate($booking);

        $this->assertSame(Payment::STATUS_PENDING, $result->payment->status);
        $this->assertSame(1, $booking->payments()->count());
        $this->assertSame('unpaid', $booking->fresh()->payment_status);
        Http::assertSentCount(2);
    }

    public function test_secrets_and_mac_are_never_written_to_logs(): void
    {
        $records = [];
        Log::listen(function ($record) use (&$records): void {
            $records[] = json_encode([$record->message, $record->context]);
        });
        Http::fake(['*' => Http::failedConnection('network unavailable')]);

        try {
            app(PaymentInitiationService::class)->initiate($this->payableBooking());
        } catch (Throwable $exception) {
            $records[] = $exception->getMessage();
        }

        $output = implode('\n', $records);
        $this->assertStringNotContainsString('test-key1', $output);
        $this->assertStringNotContainsString('test-key2', $output);
        $this->assertDoesNotMatchRegularExpression('/[a-f0-9]{64}/i', $output);
    }

    public function test_active_pending_attempt_is_reused_instead_of_creating_another_attempt(): void
    {
        $booking = $this->payableBooking();
        $existing = $this->pendingPayment($booking, ['order_url' => 'https://zalopay.example.test/existing']);
        Http::fake();

        $result = app(PaymentInitiationService::class)->initiate($booking);

        $this->assertTrue($result->replayed);
        $this->assertSame($existing->id, $result->payment->id);
        $this->assertSame(1, $booking->payments()->count());
        Http::assertNothingSent();
    }

    public function test_guest_payment_initiation_requires_scoped_session_capability(): void
    {
        Http::fake(['*' => Http::response($this->successfulCreate(), 200)]);
        $booking = $this->payableBooking();
        $rawToken = app(BookingTokenService::class)->issueGuestAccessToken();
        $booking->forceFill([
            'guest_access_token_hash' => hash('sha256', $rawToken),
            'guest_access_expires_at' => now()->addHour(),
        ])->save();

        $this->post(route('user.bookings.access.exchange', $booking), [
            'token' => $rawToken,
            'destination' => 'success',
        ])->assertOk();

        $this->post(route('payments.zalopay.initiate', $booking))
            ->assertRedirect('https://zalopay.example.test/pay');
        $this->assertSame(1, $booking->payments()->count());
    }

    public function test_guest_bearer_in_request_body_cannot_bypass_session_capability(): void
    {
        Http::fake();
        $booking = $this->payableBooking();
        $rawToken = app(BookingTokenService::class)->issueGuestAccessToken();
        $booking->forceFill([
            'guest_access_token_hash' => hash('sha256', $rawToken),
            'guest_access_expires_at' => now()->addHour(),
        ])->save();

        $this->post(route('payments.zalopay.initiate', $booking), [
            'guest_token' => $rawToken,
        ])->assertNotFound();

        $this->assertSame(0, $booking->payments()->count());
        Http::assertNothingSent();
    }

    public function test_checkout_replay_by_a_different_actor_cannot_start_payment(): void
    {
        $this->seedRbac();
        Http::fake();
        $scenario = $this->bookingScenario(false);
        $checkoutToken = app(BookingTokenService::class)->issueCheckoutToken();
        $booking = $this->reserve(
            $scenario,
            [$scenario['seats'][0]->id],
            null,
            $checkoutToken,
        )->booking;
        $attacker = $this->userWithRole('user');

        $this->actingAs($attacker)->post(route('user.bookings.store'), [
            'showtime_id' => $scenario['showtime']->id,
            'seat_ids' => [$scenario['seats'][0]->id],
            'customer_email' => 'guest@example.test',
            'checkout_token' => $checkoutToken,
        ])->assertConflict();

        $this->post(route('payments.zalopay.initiate', $booking))->assertForbidden();
        $this->assertSame(0, $booking->payments()->count());
        Http::assertNothingSent();
    }

    private function successfulCreate(): array
    {
        return [
            'return_code' => 1,
            'return_message' => 'Success',
            'sub_return_code' => 1,
            'sub_return_message' => 'Success',
            'zp_trans_token' => 'token',
            'order_url' => 'https://zalopay.example.test/pay',
            'order_token' => 'order-token',
            'qr_code' => 'qr-data',
        ];
    }
}
