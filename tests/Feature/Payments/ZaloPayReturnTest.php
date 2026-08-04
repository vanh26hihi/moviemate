<?php

namespace Tests\Feature\Payments;

use App\Domain\Payments\ZaloPaySigner;
use App\Models\Payment;
use App\Services\Payments\PaymentReturnTokenService;
use Illuminate\Support\Facades\Http;
use Illuminate\Testing\TestResponse;

class ZaloPayReturnTest extends PaymentTestCase
{
    public function test_fake_browser_status_does_not_pay(): void
    {
        $payment = $this->pendingPayment();
        $params = $this->returnParams($payment, ['status' => '1'], false);

        $this->visitReturn($payment, $params)->assertOk();

        $this->assertSame(Payment::STATUS_PENDING, $payment->fresh()->status);
        $this->assertSame('unpaid', $payment->booking->fresh()->payment_status);
    }

    public function test_valid_checksum_alone_does_not_pay_or_call_provider(): void
    {
        Http::fake();
        $payment = $this->pendingPayment();
        $params = $this->returnParams($payment, ['status' => '1', 'amount' => '999999'], true);

        $response = $this->visitReturn($payment, $params);

        $response->assertOk()->assertSee('có checksum hợp lệ');
        $this->assertSame(Payment::STATUS_PENDING, $payment->fresh()->status);
        $this->assertSame('unpaid', $payment->booking->fresh()->payment_status);
        Http::assertNothingSent();
    }

    public function test_invalid_checksum_does_not_pay(): void
    {
        $payment = $this->pendingPayment();
        $params = $this->returnParams($payment, [], false);

        $this->visitReturn($payment, $params)
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

        $this->visitReturn($payment, $params)
            ->assertOk()
            ->assertSee('data-payment-state="review"', false)
            ->assertSee('Giao dịch đang được đối soát');

        $this->assertSame(Payment::STATUS_REVIEW, $payment->fresh()->status);
    }

    public function test_guest_return_requires_attempt_scoped_access_token(): void
    {
        $payment = $this->pendingPayment();
        $params = $this->returnParams($payment);
        unset($params['return_token']);

        $this->get(route('payments.zalopay.return', $params))->assertNotFound();
    }

    public function test_guest_return_exchanges_internal_token_for_clean_session_url(): void
    {
        $payment = $this->pendingPayment();
        $guestToken = 'guest-bearer-must-never-enter-a-url';
        $payment->booking->forceFill([
            'guest_access_token_hash' => hash('sha256', $guestToken),
            'guest_access_expires_at' => now()->addHour(),
        ])->save();
        $params = $this->returnParams($payment);

        $response = $this->get(route('payments.zalopay.return', $params));
        $cleanUrl = route('payments.zalopay.return', ['apptransid' => $payment->app_trans_id]);

        $response->assertRedirect($cleanUrl)
            ->assertHeader('Referrer-Policy', 'no-referrer');
        $this->assertStringContainsString('no-store', (string) $response->headers->get('Cache-Control'));
        $this->assertStringNotContainsString($guestToken, route('payments.zalopay.return', $params));
        $this->assertStringNotContainsString('return_token', $cleanUrl);
        $this->assertStringNotContainsString($guestToken, $cleanUrl);

        $this->get($cleanUrl)->assertOk();
    }

    public function test_pending_return_has_no_ticket_qr_or_download(): void
    {
        $payment = $this->pendingPayment();

        $this->visitReturn($payment, $this->returnParams($payment))
            ->assertOk()
            ->assertDontSee('data-paid-ticket-link', false)
            ->assertDontSee('data-qr-value', false)
            ->assertDontSee('data-ticket-download', false);
    }

    public function test_paid_callback_return_links_to_clean_guest_ticket_url(): void
    {
        $payment = $this->pendingPayment();
        $this->postJson(route('payments.zalopay.callback'), $this->callbackBody($payment))
            ->assertJsonPath('return_code', 1);

        $this->visitReturn($payment, $this->returnParams($payment))
            ->assertOk()
            ->assertSee('data-paid-ticket-link', false)
            ->assertSee(route('user.bookings.ticket', $payment->booking), false)
            ->assertDontSee('guest_token=', false)
            ->assertDontSee('return_token=', false);
    }

    private function visitReturn(Payment $payment, array $params): TestResponse
    {
        $cleanUrl = route('payments.zalopay.return', ['apptransid' => $payment->app_trans_id]);
        $response = $this->get(route('payments.zalopay.return', $params));
        $response->assertRedirect($cleanUrl)
            ->assertHeader('Referrer-Policy', 'no-referrer');
        $this->assertStringContainsString('no-store', (string) $response->headers->get('Cache-Control'));

        return $this->get($cleanUrl);
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
