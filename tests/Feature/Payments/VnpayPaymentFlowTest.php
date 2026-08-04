<?php

namespace Tests\Feature\Payments;

use App\Domain\Payments\VnpaySigner;
use App\Models\BookingTicketDelivery;
use App\Models\Payment;
use App\Services\Payments\PaymentInitiationService;
use App\Services\Payments\PaymentReturnTokenService;
use Illuminate\Support\Facades\Log;

class VnpayPaymentFlowTest extends VnpayPaymentTestCase
{
    public function test_initiation_persists_attempt_before_building_a_valid_signed_url(): void
    {
        $booking = $this->payableBooking();
        $result = app(PaymentInitiationService::class)->initiate($booking, 'vnpay', '203.0.113.7');
        parse_str((string) parse_url($result->orderUrl, PHP_URL_QUERY), $parameters);

        $this->assertSame('vnpay', $result->payment->provider);
        $this->assertSame(50000, $result->payment->amount);
        $this->assertSame('5000000', $parameters['vnp_Amount']);
        $this->assertSame('203.0.113.7', $parameters['vnp_IpAddr']);
        $this->assertSame('2.1.0', $parameters['vnp_Version']);
        $this->assertSame('pay', $parameters['vnp_Command']);
        $this->assertSame('VND', $parameters['vnp_CurrCode']);
        $this->assertSame($result->payment->order_code, $parameters['vnp_TxnRef']);
        $this->assertMatchesRegularExpression('/^\d{14}$/', $parameters['vnp_CreateDate']);
        $this->assertMatchesRegularExpression('/^\d{14}$/', $parameters['vnp_ExpireDate']);
        $this->assertSame('VNPAYQR', $parameters['vnp_BankCode']);
        $this->assertTrue(app(VnpaySigner::class)->verifyPayment(
            $parameters,
            $parameters['vnp_SecureHash'],
            (string) config('payment.vnpay.hash_secret'),
        ));
        $this->assertNull($result->payment->payment_url);
        $this->assertNull($result->payment->order_url);
        $this->assertStringNotContainsString((string) config('payment.vnpay.hash_secret'), $result->orderUrl);
        $this->assertSame('unpaid', $booking->fresh()->payment_status);
    }

    public function test_blank_bank_code_is_omitted_and_pending_attempt_is_idempotently_reused(): void
    {
        config(['payment.vnpay.bank_code' => '']);
        $booking = $this->payableBooking();
        $first = app(PaymentInitiationService::class)->initiate($booking, 'vnpay');
        $second = app(PaymentInitiationService::class)->initiate($booking, 'vnpay');
        parse_str((string) parse_url($second->orderUrl, PHP_URL_QUERY), $parameters);

        $this->assertTrue($second->replayed);
        $this->assertSame($first->payment->id, $second->payment->id);
        $this->assertArrayNotHasKey('vnp_BankCode', $parameters);
        $this->assertSame(1, $booking->payments()->count());
    }

    public function test_valid_ipn_is_the_only_path_that_fulfils_and_duplicate_is_idempotent(): void
    {
        $payment = $this->vnpayPayment();
        $parameters = $this->signedParameters($payment);

        $this->getJson(route('payments.vnpay.ipn', $parameters))
            ->assertOk()
            ->assertHeaderMissing('Set-Cookie')
            ->assertExactJson(['RspCode' => '00', 'Message' => 'Confirm success']);
        $this->assertSame(Payment::STATUS_SUCCESS, $payment->fresh()->status);
        $this->assertSame('paid', $payment->booking->fresh()->payment_status);
        $this->assertSame(1, BookingTicketDelivery::query()->where('booking_id', $payment->booking_id)->count());

        $this->getJson(route('payments.vnpay.ipn', $parameters))
            ->assertOk()->assertExactJson(['RspCode' => '02', 'Message' => 'Order already confirmed']);
        $this->assertSame(1, BookingTicketDelivery::query()->where('booking_id', $payment->booking_id)->count());
    }

    public function test_invalid_signature_and_amount_mismatch_never_pay_or_issue_ticket(): void
    {
        Log::spy();
        $payment = $this->vnpayPayment();
        $invalid = $this->signedParameters($payment);
        $invalid['vnp_SecureHash'] = str_repeat('0', 128);
        $this->getJson(route('payments.vnpay.ipn', $invalid))->assertJsonPath('RspCode', '97');
        $this->assertSame(Payment::STATUS_PENDING, $payment->fresh()->status);

        $mismatch = $this->signedParameters($payment, ['vnp_Amount' => '999900']);
        $this->getJson(route('payments.vnpay.ipn', $mismatch))->assertJsonPath('RspCode', '04');
        $this->assertSame(Payment::STATUS_REVIEW, $payment->fresh()->status);
        $this->assertSame('unpaid', $payment->booking->fresh()->payment_status);
        $this->assertDatabaseMissing('booking_ticket_deliveries', ['booking_id' => $payment->booking_id]);
        $failedRetry = $this->signedParameters($payment, ['vnp_TransactionStatus' => '02']);
        $this->getJson(route('payments.vnpay.ipn', $failedRetry))->assertJsonPath('RspCode', '00');
        $this->assertSame(Payment::STATUS_REVIEW, $payment->fresh()->status);
        Log::shouldNotHaveReceived('info');
        Log::shouldNotHaveReceived('debug');
    }

    public function test_unknown_reference_and_merchant_mismatch_are_rejected_without_mutation(): void
    {
        $payment = $this->vnpayPayment();
        $unknown = $this->signedParameters($payment, ['vnp_TxnRef' => 'MMUNKNOWNREFERENCE']);
        $this->getJson(route('payments.vnpay.ipn', $unknown))->assertJsonPath('RspCode', '01');

        $merchant = $this->signedParameters($payment, ['vnp_TmnCode' => 'OTHER123']);
        $this->getJson(route('payments.vnpay.ipn', $merchant))->assertJsonPath('RspCode', '99');
        $this->assertSame(Payment::STATUS_PENDING, $payment->fresh()->status);
    }

    public function test_partial_success_codes_never_fulfil(): void
    {
        $responseOnly = $this->vnpayPayment();
        $this->getJson(route('payments.vnpay.ipn', $this->signedParameters(
            $responseOnly,
            ['vnp_TransactionStatus' => '02'],
        )))->assertJsonPath('RspCode', '00');
        $this->assertSame(Payment::STATUS_FAILED, $responseOnly->fresh()->status);

        $statusOnly = $this->vnpayPayment();
        $this->getJson(route('payments.vnpay.ipn', $this->signedParameters(
            $statusOnly,
            ['vnp_ResponseCode' => '24'],
        )))->assertJsonPath('RspCode', '00');
        $this->assertSame(Payment::STATUS_REVIEW, $statusOnly->fresh()->status);
        $this->assertSame(0, BookingTicketDelivery::query()->count());
    }

    public function test_late_or_seat_lost_success_moves_to_review_without_ticket(): void
    {
        $late = $this->vnpayPayment();
        $late->booking->forceFill(['expires_at' => now()->subMinute()])->save();
        $this->getJson(route('payments.vnpay.ipn', $this->signedParameters($late)))
            ->assertJsonPath('RspCode', '99');
        $this->assertSame(Payment::STATUS_REVIEW, $late->fresh()->status);

        $seatLost = $this->vnpayPayment();
        $seatLost->booking->bookingSeats()->update(['active_lock_key' => null]);
        $this->getJson(route('payments.vnpay.ipn', $this->signedParameters($seatLost)))
            ->assertJsonPath('RspCode', '99');
        $this->assertSame(Payment::STATUS_REVIEW, $seatLost->fresh()->status);
        $this->assertSame(0, BookingTicketDelivery::query()->count());
    }

    public function test_return_signature_only_grants_read_access_and_cleans_provider_parameters(): void
    {
        $payment = $this->vnpayPayment();
        $state = app(PaymentReturnTokenService::class)->issue($payment);
        $parameters = $this->signedParameters($payment) + ['state' => $state];

        $response = $this->get(route('payments.vnpay.return', $parameters));
        $response->assertRedirect(route('payments.vnpay.return', ['ref' => $payment->order_code]));
        $this->followRedirects($response)->assertOk()->assertSee('VNPAY');
        $this->assertSame(Payment::STATUS_PENDING, $payment->fresh()->status);
        $this->assertSame('unpaid', $payment->booking->fresh()->payment_status);
    }

    public function test_forged_return_is_rejected_without_database_mutation(): void
    {
        $payment = $this->vnpayPayment();
        $parameters = $this->signedParameters($payment);
        $parameters['vnp_Amount'] = '100';

        $this->get(route('payments.vnpay.return', $parameters))->assertNotFound();
        $this->assertSame(Payment::STATUS_PENDING, $payment->fresh()->status);
    }

    public function test_guest_return_capability_is_scoped_to_one_payment(): void
    {
        $allowed = $this->vnpayPayment();
        $other = $this->vnpayPayment();
        $state = app(PaymentReturnTokenService::class)->issue($allowed);
        $this->get(route('payments.vnpay.return', $this->signedParameters($allowed) + ['state' => $state]))
            ->assertRedirect();

        $this->get(route('payments.vnpay.return', ['ref' => $other->order_code]))->assertNotFound();
    }
}
