<?php

namespace Tests\Feature\Payments;

use App\Models\Payment;
use App\Services\Payments\PaymentReconciliationService;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

class PaymentReconciliationTest extends PaymentTestCase
{
    public function test_query_success_is_verified_through_shared_transition(): void
    {
        $payment = $this->pendingPayment();
        Http::fake(['*' => Http::response($this->querySuccess($payment), 200)]);

        $status = app(PaymentReconciliationService::class)->reconcile($payment);

        $this->assertSame(Payment::STATUS_SUCCESS, $status);
        $this->assertSame(Payment::STATUS_SUCCESS, $payment->fresh()->status);
        $this->assertSame('paid', $payment->booking->fresh()->payment_status);
        $this->assertNotNull($payment->fresh()->query_response_hash);
        Http::assertSent(function (Request $request) use ($payment): bool {
            $this->assertSame('https://sb-openapi.zalopay.vn/v2/query', $request->url());
            $this->assertSame(['app_id', 'app_trans_id', 'mac'], array_keys($request->data()));
            $this->assertSame($payment->app_trans_id, $request['app_trans_id']);

            return true;
        });
    }

    public function test_query_pending_keeps_attempt_pending(): void
    {
        $payment = $this->pendingPayment();
        Http::fake(['*' => Http::response(['return_code' => 3, 'return_message' => 'Pending'], 200)]);

        $status = app(PaymentReconciliationService::class)->reconcile($payment);

        $this->assertSame(Payment::STATUS_PENDING, $status);
        $this->assertSame(Payment::STATUS_PENDING, $payment->fresh()->status);
        $this->assertSame('unpaid', $payment->booking->fresh()->payment_status);
        $this->assertNotNull($payment->fresh()->last_queried_at);
    }

    public function test_query_minus_54_marks_attempt_expired(): void
    {
        $payment = $this->pendingPayment();
        Http::fake(['*' => Http::response([
            'return_code' => 2, 'return_message' => 'Failed',
            'sub_return_code' => -54, 'sub_return_message' => 'Expired',
        ], 200)]);

        $this->assertSame(Payment::STATUS_EXPIRED, app(PaymentReconciliationService::class)->reconcile($payment));
        $this->assertSame(Payment::STATUS_EXPIRED, $payment->fresh()->status);
        $this->assertSame('query_expired', $payment->fresh()->failure_reason);
    }

    public function test_query_minus_101_moves_attempt_to_review(): void
    {
        $payment = $this->pendingPayment();
        Http::fake(['*' => Http::response([
            'return_code' => 2, 'return_message' => 'Unknown',
            'sub_return_code' => -101, 'sub_return_message' => 'Not found',
        ], 200)]);

        $this->assertSame(Payment::STATUS_REVIEW, app(PaymentReconciliationService::class)->reconcile($payment));
        $this->assertSame(Payment::STATUS_REVIEW, $payment->fresh()->status);
        $this->assertSame('query_unresolved', $payment->fresh()->failure_reason);
    }

    public function test_query_amount_mismatch_moves_attempt_to_review(): void
    {
        $payment = $this->pendingPayment();
        Http::fake(['*' => Http::response($this->querySuccess($payment, ['amount' => 50001]), 200)]);

        $status = app(PaymentReconciliationService::class)->reconcile($payment);

        $this->assertSame(Payment::STATUS_REVIEW, $status);
        $this->assertSame('amount_mismatch', $payment->fresh()->failure_reason);
        $this->assertSame('unpaid', $payment->booking->fresh()->payment_status);
    }

    public function test_callback_then_query_success_is_idempotent(): void
    {
        $payment = $this->pendingPayment();
        $zpTransId = 987654321;
        $this->postJson(route('payments.zalopay.callback'), $this->callbackBody($payment, [
            'zp_trans_id' => $zpTransId,
        ]))->assertJsonPath('return_code', 1);
        $paidAt = $payment->fresh()->paid_at?->format('Y-m-d H:i:s.u');
        Http::fake(['*' => Http::response($this->querySuccess($payment, [
            'zp_trans_id' => $zpTransId,
        ]), 200)]);

        $status = app(PaymentReconciliationService::class)->reconcile($payment->fresh());

        $this->assertSame(Payment::STATUS_SUCCESS, $status);
        $this->assertSame($paidAt, $payment->fresh()->paid_at?->format('Y-m-d H:i:s.u'));
        $this->assertSame(1, Payment::query()->where('status', Payment::STATUS_SUCCESS)->count());
    }

    public function test_authentication_error_fails_closed_to_review(): void
    {
        $payment = $this->pendingPayment();
        Http::fake(['*' => Http::response([], 401)]);

        $status = app(PaymentReconciliationService::class)->reconcile($payment);

        $this->assertSame(Payment::STATUS_REVIEW, $status);
        $this->assertSame('query_authentication_error', $payment->fresh()->failure_reason);
        $this->assertSame('unpaid', $payment->booking->fresh()->payment_status);
    }

    public function test_query_command_continues_after_one_attempt_errors(): void
    {
        $first = $this->pendingPayment();
        $second = $this->pendingPayment();
        Http::fake(function (Request $request) use ($first, $second) {
            if ($request['app_trans_id'] === $first->app_trans_id) {
                return Http::response('{bad json', 200);
            }

            return Http::response($this->querySuccess($second), 200);
        });

        $this->artisan('payments:query-pending', ['--batch' => 10])
            ->assertSuccessful()
            ->expectsOutputToContain('errors: 1');

        $this->assertSame(Payment::STATUS_PENDING, $first->fresh()->status);
        $this->assertSame(Payment::STATUS_SUCCESS, $second->fresh()->status);
    }

    private function querySuccess(Payment $payment, array $overrides = []): array
    {
        return array_merge([
            'return_code' => 1,
            'return_message' => 'Success',
            'amount' => $payment->amount,
            'zp_trans_id' => random_int(1000000, 9999999),
            'server_time' => (int) floor(microtime(true) * 1000),
        ], $overrides);
    }
}
