<?php

namespace Tests\Feature\Payments;

use App\Domain\Payments\ZaloPaySigner;
use App\Models\Payment;
use App\Services\Payments\PaymentReturnTokenService;
use Illuminate\Support\Facades\Http;

class ZaloPayReturnTest extends PaymentTestCase
{
    public function test_fake_browser_status_does_not_pay(): void
    {
        $payment = $this->pendingPayment();
        $params = $this->returnParams($payment, ['status' => '1'], false);

        $this->get(route('payments.zalopay.return', $params))->assertOk();

        $this->assertSame(Payment::STATUS_PENDING, $payment->fresh()->status);
        $this->assertSame('unpaid', $payment->booking->fresh()->payment_status);
    }

    public function test_valid_checksum_alone_does_not_pay_or_call_provider(): void
    {
        Http::fake();
        $payment = $this->pendingPayment();
        $params = $this->returnParams($payment, ['status' => '1', 'amount' => '999999'], true);

        $response = $this->get(route('payments.zalopay.return', $params));

        $response->assertOk()->assertSee('có checksum hợp lệ');
        $this->assertSame(Payment::STATUS_PENDING, $payment->fresh()->status);
        $this->assertSame('unpaid', $payment->booking->fresh()->payment_status);
        Http::assertNothingSent();
    }

    public function test_invalid_checksum_does_not_pay(): void
    {
        $payment = $this->pendingPayment();
        $params = $this->returnParams($payment, [], false);

        $this->get(route('payments.zalopay.return', $params))
            ->assertOk()
            ->assertSee('không được xác thực');

        $this->assertSame(Payment::STATUS_PENDING, $payment->fresh()->status);
        $this->assertNull($payment->fresh()->verified_at);
    }

    public function test_return_displays_only_current_database_state(): void
    {
        $payment = $this->pendingPayment(overrides: [
            'status' => Payment::STATUS_REVIEW,
            'failure_reason' => 'manual_review',
        ]);
        $params = $this->returnParams($payment, ['status' => '1'], true);

        $this->get(route('payments.zalopay.return', $params))
            ->assertOk()
            ->assertSee('data-payment-state="review"', false)
            ->assertSee('Cần đối soát');

        $this->assertSame(Payment::STATUS_REVIEW, $payment->fresh()->status);
    }

    public function test_guest_return_requires_attempt_scoped_access_token(): void
    {
        $payment = $this->pendingPayment();
        $params = $this->returnParams($payment);
        unset($params['return_token']);

        $this->get(route('payments.zalopay.return', $params))->assertNotFound();
    }

    private function returnParams(Payment $payment, array $overrides = [], bool $validChecksum = false): array
    {
        $params = array_merge([
            'appid' => (string) $payment->app_id,
            'apptransid' => $payment->app_trans_id,
            'pmcid' => '38',
            'bankcode' => 'test-bank',
            'amount' => (string) $payment->amount,
            'discountamount' => '0',
            'status' => '1',
            'return_token' => app(PaymentReturnTokenService::class)->issue($payment),
        ], $overrides);
        $params['checksum'] = $validChecksum
            ? app(ZaloPaySigner::class)->returnChecksum($params, 'test-key2')
            : str_repeat('0', 64);

        return $params;
    }
}
