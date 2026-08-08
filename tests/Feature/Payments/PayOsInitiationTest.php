<?php

namespace Tests\Feature\Payments;

use App\Domain\Payments\PayOsSigner;
use App\Exceptions\PaymentInitiationException;
use App\Exceptions\PayOsResponseException;
use App\Exceptions\PayOsTransportException;
use App\Models\Payment;
use App\Services\Payments\PaymentInitiationService;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

class PayOsInitiationTest extends PayOsPaymentTestCase
{
    public function test_configured_availability_and_unconfigured_state_are_safe(): void
    {
        $this->assertTrue(app(PaymentInitiationService::class)->availability()['payos']);

        config(['services.payos.checksum_key' => null]);
        $this->assertFalse(app(PaymentInitiationService::class)->availability()['payos']);
    }

    public function test_create_link_uses_authoritative_attempt_identity_amount_urls_and_expiration(): void
    {
        Http::preventStrayRequests();
        Http::fake(function (Request $request) {
            $data = $request->data();
            $responseData = [
                'orderCode' => $data['orderCode'],
                'amount' => $data['amount'],
                'currency' => 'VND',
                'paymentLinkId' => '124c33293c43417ab7879e14c8d9eb18',
                'status' => 'PENDING',
                'checkoutUrl' => 'https://pay.payos.vn/web/124c33293c43417ab7879e14c8d9eb18',
            ];

            return Http::response($this->providerEnvelope($responseData));
        });
        $booking = $this->payableBooking();

        $result = app(PaymentInitiationService::class)->initiate($booking, 'payos');

        $this->assertSame('https://pay.payos.vn/web/124c33293c43417ab7879e14c8d9eb18', $result->orderUrl);
        $this->assertSame($booking->id, $result->payment->booking_id);
        $this->assertSame(50000, $result->payment->amount);
        $this->assertSame('unpaid', $booking->fresh()->payment_status);
        $this->assertMatchesRegularExpression('/^[1-9][0-9]{0,15}$/', $result->payment->order_code);
        $this->assertMatchesRegularExpression('/^MM[A-Z0-9]{1,7}$/', $result->payment->description);
        $this->assertLessThanOrEqual(9, strlen($result->payment->description));

        Http::assertSent(function (Request $request) use ($booking): bool {
            $data = $request->data();
            $signed = array_intersect_key($data, array_flip([
                'amount', 'cancelUrl', 'description', 'orderCode', 'returnUrl',
            ]));
            $this->assertSame('https://api-merchant.payos.vn/v2/payment-requests', $request->url());
            $this->assertSame(50000, $data['amount']);
            $this->assertSame($booking->expires_at->getTimestamp(), $data['expiredAt']);
            $this->assertStringContainsString('/payments/payos/return', $data['returnUrl']);
            $this->assertStringContainsString('/payments/payos/cancel', $data['cancelUrl']);
            $this->assertSame(
                app(PayOsSigner::class)->createPaymentRequestSignature($signed, self::CHECKSUM_KEY),
                $data['signature'],
            );

            return true;
        });
    }

    public function test_browser_amount_and_order_code_cannot_override_server_values(): void
    {
        Http::fake(function (Request $request) {
            $data = $request->data();
            $responseData = [
                'orderCode' => $data['orderCode'], 'amount' => $data['amount'], 'currency' => 'VND',
                'paymentLinkId' => '124c33293c43417ab7879e14c8d9eb18', 'status' => 'PENDING',
                'checkoutUrl' => 'https://pay.payos.vn/web/124c33293c43417ab7879e14c8d9eb18',
            ];

            return Http::response($this->providerEnvelope($responseData));
        });
        $this->seedRbac();
        $user = $this->userWithRole('user');
        $booking = $this->payableBooking(['user_id' => $user->id]);

        $this->actingAs($user)->post(route('payments.payos.initiate', $booking), [
            'amount' => 1,
            'orderCode' => 999,
            'paymentLinkId' => 'forged',
            'cinema_id' => 999,
        ])->assertRedirect('https://pay.payos.vn/web/124c33293c43417ab7879e14c8d9eb18');

        $payment = $booking->payments()->sole();
        $this->assertSame(50000, $payment->amount);
        $this->assertNotSame('999', $payment->order_code);
        $this->assertSame($booking->cinema_id, $payment->booking->cinema_id);
    }

    public function test_duplicate_initiation_reuses_confirmed_link_without_second_provider_request(): void
    {
        Http::fake(function (Request $request) {
            $data = $request->data();
            $responseData = [
                'orderCode' => $data['orderCode'], 'amount' => $data['amount'], 'currency' => 'VND',
                'paymentLinkId' => '124c33293c43417ab7879e14c8d9eb18', 'status' => 'PENDING',
                'checkoutUrl' => 'https://pay.payos.vn/web/124c33293c43417ab7879e14c8d9eb18',
            ];

            return Http::response($this->providerEnvelope($responseData));
        });
        $booking = $this->payableBooking();

        $first = app(PaymentInitiationService::class)->initiate($booking, 'payos');
        $second = app(PaymentInitiationService::class)->initiate($booking, 'payos');

        $this->assertSame($first->payment->id, $second->payment->id);
        $this->assertSame($first->orderUrl, $second->orderUrl);
        $this->assertTrue($second->replayed);
        $this->assertSame(1, $booking->payments()->count());
        Http::assertSentCount(1);
    }

    public function test_tampered_response_signature_and_open_redirect_are_never_accepted(): void
    {
        foreach (['signature', 'checkout'] as $case) {
            Http::fake(function (Request $request) use ($case) {
                $data = $request->data();
                $responseData = [
                    'orderCode' => $data['orderCode'], 'amount' => $data['amount'], 'currency' => 'VND',
                    'paymentLinkId' => '124c33293c43417ab7879e14c8d9eb18', 'status' => 'PENDING',
                    'checkoutUrl' => $case === 'checkout'
                        ? 'https://attacker.example.test/steal'
                        : 'https://pay.payos.vn/web/124c33293c43417ab7879e14c8d9eb18',
                ];
                $envelope = $this->providerEnvelope($responseData);
                if ($case === 'signature') {
                    $envelope['signature'] = str_repeat('0', 64);
                }

                return Http::response($envelope);
            });
            $booking = $this->payableBooking();

            try {
                app(PaymentInitiationService::class)->initiate($booking, 'payos');
                $this->fail('Untrusted response must be rejected.');
            } catch (PayOsResponseException) {
                $this->addToAssertionCount(1);
            }

            $payment = $booking->payments()->sole();
            $this->assertNotSame(Payment::STATUS_SUCCESS, $payment->status);
            $this->assertSame('unpaid', $booking->fresh()->payment_status);
            $this->assertNull($payment->payment_url);
            $payment->forceFill(['status' => Payment::STATUS_FAILED])->save();
            $booking->forceFill(['booking_status' => 'cancelled'])->save();
        }
    }

    public function test_create_timeout_and_rate_limit_keep_one_unresolved_attempt_and_reserved_seats(): void
    {
        foreach ([
            fn () => Http::failedConnection('timeout'),
            fn () => Http::response([], 429),
        ] as $fakeResponse) {
            Http::fake(['*' => $fakeResponse]);
            $booking = $this->payableBooking();

            try {
                app(PaymentInitiationService::class)->initiate($booking, 'payos');
                $this->fail('The uncertain provider result must throw.');
            } catch (PayOsTransportException) {
                $this->addToAssertionCount(1);
            }

            $payment = $booking->payments()->sole();
            $this->assertSame(Payment::STATUS_UNRESOLVED, $payment->status);
            $this->assertSame('pending_payment', $booking->fresh()->booking_status);
            $this->assertSame(1, $booking->bookingSeats()->whereNotNull('active_lock_key')->count());
            $payment->forceFill(['status' => Payment::STATUS_FAILED])->save();
            $booking->forceFill(['booking_status' => 'cancelled'])->save();
        }
    }

    public function test_cross_provider_attempt_cannot_be_replaced_by_payos(): void
    {
        Http::fake();
        $booking = $this->payableBooking();
        $this->pendingPayment($booking);

        $this->expectException(PaymentInitiationException::class);
        try {
            app(PaymentInitiationService::class)->initiate($booking, 'payos');
        } finally {
            $this->assertSame(1, $booking->payments()->count());
            Http::assertNothingSent();
        }
    }

    public function test_expired_and_nearly_expired_bookings_are_rejected_before_http(): void
    {
        Http::fake();
        $booking = $this->payableBooking(['expires_at' => now()->addSeconds(30)]);

        $this->expectException(PaymentInitiationException::class);
        try {
            app(PaymentInitiationService::class)->initiate($booking, 'payos');
        } finally {
            $this->assertSame(0, $booking->payments()->count());
            Http::assertNothingSent();
        }
    }
}
