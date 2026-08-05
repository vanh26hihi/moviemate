<?php

namespace Tests\Feature\Payments;

use App\Domain\Payments\VnpaySigner;
use App\Exceptions\VnpayResponseException;
use App\Models\BookingTicketDelivery;
use App\Models\Payment;
use App\Services\Payments\PaymentReconciliationService;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

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
}
