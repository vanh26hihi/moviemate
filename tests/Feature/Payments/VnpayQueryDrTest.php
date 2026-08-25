<?php

namespace Tests\Feature\Payments;

use App\Domain\Payments\VnpaySigner;
use App\Exceptions\VnpayResponseException;
use App\Exceptions\VnpayTransportException;
use App\Models\ActivityLog;
use App\Models\BookingTicketDelivery;
use App\Models\Payment;
use App\Services\Payments\PaymentReconciliationService;
use App\Services\Payments\VnpayQueryService;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;

class VnpayQueryDrTest extends VnpayPaymentTestCase
{
    public function test_verified_query_dr_success_fulfils_exactly_once(): void
    {
        $payment = $this->vnpayPayment();
        Http::fake(fn () => Http::response($this->response($payment), 200));

        $status = app(PaymentReconciliationService::class)->reconcile($payment);

        $this->assertSame(Payment::STATUS_SUCCESS, $status);
        $this->assertSame('paid', $payment->booking->fresh()->payment_status);
        $this->assertSame(1, BookingTicketDelivery::query()->where('booking_id', $payment->booking_id)->count());
        $this->assertEvidence('provider_query_success', 200, 'match', true, '00', '00');
        Http::assertSent(function (Request $request) use ($payment): bool {
            $fields = $request->data();
            $this->assertSame('querydr', $fields['vnp_Command']);
            $this->assertSame($payment->order_code, $fields['vnp_TxnRef']);
            $this->assertSame(
                app(VnpaySigner::class)->signQueryRequest($fields, (string) config('payment.vnpay.hash_secret')),
                $fields['vnp_SecureHash'],
            );

            return true;
        });
    }

    public function test_query_dr_checksum_failure_moves_attempt_to_review_without_fulfilment(): void
    {
        $payment = $this->vnpayPayment();
        $response = $this->response($payment);
        $response['vnp_SecureHash'] = str_repeat('0', 128);
        Http::fake(fn () => Http::response($response, 200));

        $this->assertSame(Payment::STATUS_REVIEW, app(PaymentReconciliationService::class)->reconcile($payment));
        $this->assertSame('unpaid', $payment->booking->fresh()->payment_status);
        $this->assertDatabaseMissing('booking_ticket_deliveries', ['booking_id' => $payment->booking_id]);
        $this->assertEvidence('response_checksum_mismatch', 200, 'mismatch', true, '00', '00');
    }

    #[DataProvider('authenticationHttpStatusProvider')]
    public function test_http_authentication_statuses_are_classified_without_exposing_payloads(
        int $httpStatus,
        string $classification,
    ): void {
        $payment = $this->vnpayPayment();
        Http::fake(fn () => Http::response('', $httpStatus));

        $this->assertSame(Payment::STATUS_REVIEW, app(PaymentReconciliationService::class)->reconcile($payment));
        $this->assertSame('query_authentication_error', $payment->fresh()->failure_reason);
        $this->assertEvidence($classification, $httpStatus, 'unavailable', false);
    }

    public static function authenticationHttpStatusProvider(): array
    {
        return [
            'unauthorized' => [401, 'transport_http_401'],
            'forbidden' => [403, 'transport_http_403'],
        ];
    }

    public function test_generic_non_success_http_status_is_classified(): void
    {
        $payment = $this->vnpayPayment();
        Http::fake(fn () => Http::response('provider unavailable', 503));

        try {
            app(PaymentReconciliationService::class)->reconcile($payment);
            $this->fail('A non-success HTTP response must not be treated as provider evidence.');
        } catch (VnpayResponseException) {
            $this->addToAssertionCount(1);
        }

        $this->assertEvidence('transport_http_other', 503, 'unavailable', false);
    }

    public function test_transport_timeout_is_classified(): void
    {
        $payment = $this->vnpayPayment();
        Http::fake(fn () => throw new ConnectionException('synthetic timeout'));

        try {
            app(PaymentReconciliationService::class)->reconcile($payment);
            $this->fail('A transport timeout must escape for operational retry handling.');
        } catch (VnpayTransportException) {
            $this->addToAssertionCount(1);
        }

        $this->assertEvidence('transport_timeout', null, 'unavailable', false);
    }

    public function test_invalid_json_is_classified(): void
    {
        $payment = $this->vnpayPayment();
        Http::fake(fn () => Http::response('{not-json', 200));

        try {
            app(PaymentReconciliationService::class)->reconcile($payment);
            $this->fail('Malformed JSON must not be trusted.');
        } catch (VnpayResponseException) {
            $this->addToAssertionCount(1);
        }

        $this->assertEvidence('response_invalid_json', 200, 'unavailable', false);
    }

    public function test_missing_checksum_is_classified(): void
    {
        $payment = $this->vnpayPayment();
        $response = $this->response($payment);
        unset($response['vnp_SecureHash']);
        Http::fake(fn () => Http::response($response, 200));

        $this->assertSame(Payment::STATUS_REVIEW, app(PaymentReconciliationService::class)->reconcile($payment));
        $this->assertEvidence('response_missing_checksum', 200, 'unavailable', false, '00', '00');
    }

    public function test_unknown_transaction_status_is_review_never_success(): void
    {
        $payment = $this->vnpayPayment();
        Http::fake(fn () => Http::response($this->response($payment, ['vnp_TransactionStatus' => '99']), 200));

        $this->assertSame(Payment::STATUS_REVIEW, app(PaymentReconciliationService::class)->reconcile($payment));
        $this->assertSame('unpaid', $payment->booking->fresh()->payment_status);
    }

    public function test_pending_query_status_preserves_pending_attempt(): void
    {
        $payment = $this->vnpayPayment();
        Http::fake(fn () => Http::response($this->response($payment, ['vnp_TransactionStatus' => '01']), 200));

        $this->assertSame(Payment::STATUS_PENDING, app(PaymentReconciliationService::class)->reconcile($payment));
        $this->assertSame('unpaid', $payment->booking->fresh()->payment_status);
    }

    public function test_pending_query_status_preserves_review_attempt(): void
    {
        $payment = $this->vnpayPayment(overrides: ['status' => Payment::STATUS_REVIEW]);
        Http::fake(fn () => Http::response($this->response($payment, ['vnp_TransactionStatus' => '01']), 200));

        $this->assertSame(Payment::STATUS_REVIEW, app(VnpayQueryService::class)->reconcileReview($payment));
        $this->assertSame('query_pending', $payment->fresh()->failure_reason);
        $this->assertSame('unpaid', $payment->booking->fresh()->payment_status);
        $this->assertEvidence('provider_query_success', 200, 'match', true, '00', '01');
    }

    public function test_provider_duplicate_request_code_is_distinct_from_checksum_failure(): void
    {
        $payment = $this->vnpayPayment(overrides: ['status' => Payment::STATUS_REVIEW]);
        Http::fake(fn () => Http::response($this->response($payment, [
            'vnp_ResponseCode' => '94',
            'vnp_Message' => 'Duplicate request',
            'vnp_TransactionStatus' => '',
        ]), 200));

        $this->assertSame(Payment::STATUS_REVIEW, app(VnpayQueryService::class)->reconcileReview($payment));
        $this->assertSame('vnpay_query_response_94', $payment->fresh()->failure_reason);
        $this->assertEvidence('provider_response_error', 200, 'match', true, '94');
    }

    public function test_each_query_uses_a_new_request_id(): void
    {
        $payment = $this->vnpayPayment();
        $requestIds = [];
        Str::createRandomStringsUsingSequence([str_repeat('A', 32), str_repeat('B', 32)]);
        Http::fake(function (Request $request) use ($payment, &$requestIds) {
            $requestIds[] = $request->data()['vnp_RequestId'];

            return Http::response($this->response($payment, ['vnp_TransactionStatus' => '01']), 200);
        });

        try {
            $this->assertSame(Payment::STATUS_PENDING, app(PaymentReconciliationService::class)->reconcile($payment));
            $this->assertSame(Payment::STATUS_PENDING, app(PaymentReconciliationService::class)->reconcile($payment->fresh()));
        } finally {
            Str::createRandomStringsNormally();
        }

        $this->assertSame([str_repeat('A', 32), str_repeat('B', 32)], $requestIds);
        $this->assertSame(2, ActivityLog::query()->where('action', 'payment.vnpay_query_attempted')->count());
    }

    public function test_duplicate_reconciliation_does_not_duplicate_settlement(): void
    {
        $payment = $this->vnpayPayment();
        Http::fake(fn () => Http::response($this->response($payment), 200));

        $this->assertSame(Payment::STATUS_SUCCESS, app(PaymentReconciliationService::class)->reconcile($payment));
        $this->assertSame(Payment::STATUS_SUCCESS, app(PaymentReconciliationService::class)->reconcile($payment->fresh()));
        $this->assertSame(1, BookingTicketDelivery::query()->where('booking_id', $payment->booking_id)->count());
        $this->assertSame(1, ActivityLog::query()->where('action', 'payment.vnpay_query_attempted')->count());
        Http::assertSentCount(1);
    }

    public function test_terminal_failed_query_cancels_booking_and_releases_seats(): void
    {
        $payment = $this->vnpayPayment();
        Http::fake(fn () => Http::response($this->response(
            $payment,
            ['vnp_TransactionStatus' => '02'],
        ), 200));

        $this->assertSame(Payment::STATUS_FAILED, app(PaymentReconciliationService::class)->reconcile($payment));
        $this->assertSame('cancelled', $payment->booking->fresh()->booking_status);
        $this->assertSame(0, $payment->booking->bookingSeats()->whereNotNull('active_lock_key')->count());
    }

    public function test_expired_transaction_status_uses_existing_terminal_failure_policy(): void
    {
        $payment = $this->vnpayPayment();
        Http::fake(fn () => Http::response($this->response(
            $payment,
            ['vnp_TransactionStatus' => '08'],
        ), 200));

        $this->assertSame(Payment::STATUS_FAILED, app(PaymentReconciliationService::class)->reconcile($payment));
        $this->assertSame('vnpay_terminal_expired', $payment->fresh()->failure_reason);
        $this->assertSame('cancelled', $payment->booking->fresh()->booking_status);
        $this->assertSame(0, $payment->booking->bookingSeats()->whereNotNull('active_lock_key')->count());
        $this->assertEvidence('provider_query_success', 200, 'match', true, '00', '08');
    }

    public function test_malformed_query_response_fails_closed(): void
    {
        $payment = $this->vnpayPayment();
        Http::fake(fn () => Http::response('{not-json', 200));

        try {
            app(PaymentReconciliationService::class)->reconcile($payment);
            $this->fail('Malformed response should escape for operational retry handling.');
        } catch (VnpayResponseException) {
            $this->addToAssertionCount(1);
        }
        $this->assertSame(Payment::STATUS_UNRESOLVED, $payment->fresh()->status);
        $this->assertSame('unpaid', $payment->booking->fresh()->payment_status);
    }

    public function test_ipn_success_racing_with_stale_query_response_cannot_be_downgraded(): void
    {
        $payment = $this->vnpayPayment();
        Http::fake(function () use ($payment) {
            $this->getJson(route('payments.vnpay.ipn', $this->signedParameters($payment)))
                ->assertJsonPath('RspCode', '00');

            return Http::response($this->response($payment, ['vnp_TransactionStatus' => '02']), 200);
        });

        $this->assertSame(Payment::STATUS_SUCCESS, app(PaymentReconciliationService::class)->reconcile($payment));
        $this->assertSame('paid', $payment->booking->fresh()->payment_status);
    }

    /** @param array<string,string> $overrides @return array<string,string> */
    private function response(Payment $payment, array $overrides = []): array
    {
        $fields = array_merge([
            'vnp_ResponseId' => 'RESP123',
            'vnp_Command' => 'querydr',
            'vnp_ResponseCode' => '00',
            'vnp_Message' => 'Success',
            'vnp_TmnCode' => 'MOVIE123',
            'vnp_TxnRef' => $payment->order_code,
            'vnp_Amount' => (string) ($payment->amount * 100),
            'vnp_BankCode' => 'NCB',
            'vnp_PayDate' => now('Asia/Ho_Chi_Minh')->format('YmdHis'),
            'vnp_TransactionNo' => '987654321',
            'vnp_TransactionType' => '01',
            'vnp_TransactionStatus' => '00',
            'vnp_OrderInfo' => 'MovieMate test booking',
            'vnp_PromotionCode' => '',
            'vnp_PromotionAmount' => '0',
        ], $overrides);
        $fields['vnp_SecureHash'] = hash_hmac(
            'sha512',
            app(VnpaySigner::class)->queryResponseCanonical($fields),
            (string) config('payment.vnpay.hash_secret'),
        );

        return $fields;
    }

    private function assertEvidence(
        string $classification,
        ?int $httpStatus,
        string $checksum,
        bool $hasChecksum,
        ?string $responseCode = null,
        ?string $transactionStatus = null,
    ): void {
        $event = ActivityLog::query()->where('action', 'payment.vnpay_query_attempted')->sole();
        $context = $event->context;

        $this->assertSame($classification, $context['error_category']);
        $this->assertSame($httpStatus, $context['http_status'] ?? null);
        $this->assertSame($checksum, $context['checksum_verification']);
        $this->assertSame($hasChecksum, $context['response_has_checksum']);
        $this->assertSame($responseCode, $context['provider_response_code'] ?? null);
        $this->assertSame($transactionStatus, $context['provider_transaction_status'] ?? null);
        $this->assertMatchesRegularExpression('/^[A-Z0-9]{32}$/D', $context['query_request_id']);
        $this->assertMatchesRegularExpression('/^[0-9]{14}$/D', $context['query_requested_at']);
        $this->assertMatchesRegularExpression('/^[0-9]{14}$/D', $context['transaction_date_sent']);

        $serialized = json_encode($event->getAttributes(), JSON_THROW_ON_ERROR);
        $this->assertStringNotContainsString((string) config('payment.vnpay.hash_secret'), $serialized);
        $this->assertStringNotContainsString('vnp_SecureHash', $serialized);
    }
}
