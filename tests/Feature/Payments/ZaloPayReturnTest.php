<?php

namespace Tests\Feature\Payments;

use App\Domain\Payments\ZaloPaySigner;
use App\Exceptions\PaymentConfigurationException;
use App\Models\Payment;
use App\Services\Payments\PaymentReturnTokenService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Testing\TestResponse;

class ZaloPayReturnTest extends PaymentTestCase
{
    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

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
        unset($params['state']);

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
        $this->assertStringNotContainsString('state=', $cleanUrl);
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

    public function test_return_state_expires_after_short_ttl(): void
    {
        Carbon::setTestNow('2026-08-04 10:00:00');
        config(['payment.return_state_ttl_minutes' => 30]);
        $payment = $this->pendingPayment();
        $state = app(PaymentReturnTokenService::class)->issue($payment);

        Carbon::setTestNow('2026-08-04 10:31:00');

        $this->assertFalse(app(PaymentReturnTokenService::class)->verify($payment, $state));
    }

    public function test_return_state_rejects_wrong_audience_even_with_valid_signature(): void
    {
        $payment = $this->pendingPayment();
        $state = $this->stateWithChangedClaim(
            app(PaymentReturnTokenService::class)->issue($payment),
            'aud',
            'guest-ticket',
        );

        $this->assertFalse(app(PaymentReturnTokenService::class)->verify($payment, $state));
    }

    public function test_return_state_rejects_tampering(): void
    {
        $payment = $this->pendingPayment();
        $state = app(PaymentReturnTokenService::class)->issue($payment);
        $last = substr($state, -1);
        $tampered = substr($state, 0, -1).($last === 'A' ? 'B' : 'A');

        $this->assertFalse(app(PaymentReturnTokenService::class)->verify($payment, $tampered));
    }

    public function test_return_state_is_scoped_to_one_payment_attempt(): void
    {
        $first = $this->pendingPayment();
        $second = $this->pendingPayment($this->payableBooking());
        $state = app(PaymentReturnTokenService::class)->issue($first);

        $this->assertFalse(app(PaymentReturnTokenService::class)->verify($second, $state));
        $this->get(route('payments.zalopay.return', [
            'apptransid' => $second->app_trans_id,
            'state' => $state,
        ]))->assertNotFound();
    }

    public function test_return_state_requires_a_256_bit_application_key(): void
    {
        $payment = $this->pendingPayment();
        config(['app.key' => 'too-short']);

        $this->expectException(PaymentConfigurationException::class);
        app(PaymentReturnTokenService::class)->issue($payment);
    }

    public function test_paid_guest_return_state_does_not_grant_ticket_capability(): void
    {
        $payment = $this->pendingPayment();
        $this->postJson(route('payments.zalopay.callback'), $this->callbackBody($payment))
            ->assertJsonPath('return_code', 1);

        $this->visitReturn($payment, $this->returnParams($payment))
            ->assertOk()
            ->assertDontSee('data-paid-ticket-link', false)
            ->assertSee('Liên kết mở vé an toàn được gửi riêng qua email')
            ->assertDontSee($payment->booking->booking_code)
            ->assertDontSee('guest_token=', false)
            ->assertDontSee('?state=', false)
            ->assertDontSee('&state=', false);

        $this->get(route('user.bookings.ticket', $payment->booking))->assertNotFound();
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
            'state' => app(PaymentReturnTokenService::class)->issue($payment),
        ], $overrides);
        $params['checksum'] = $validChecksum
            ? app(ZaloPaySigner::class)->returnChecksum($params, 'test-key2')
            : str_repeat('0', 64);

        return $params;
    }

    private function stateWithChangedClaim(string $state, string $name, mixed $value): string
    {
        [, $payload] = explode('.', $state);
        $claims = json_decode($this->base64UrlDecode($payload), true, 16, JSON_THROW_ON_ERROR);
        $claims[$name] = $value;
        $newPayload = $this->base64UrlEncode(json_encode($claims, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));

        $configuredKey = (string) config('app.key');
        $key = str_starts_with($configuredKey, 'base64:')
            ? base64_decode(substr($configuredKey, 7), true)
            : $configuredKey;
        $derived = hash_hmac('sha256', 'moviemate/payment-return-state/v1', $key, true);
        $signature = $this->base64UrlEncode(hash_hmac('sha256', 'v1.'.$newPayload, $derived, true));

        return 'v1.'.$newPayload.'.'.$signature;
    }

    private function base64UrlEncode(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }

    private function base64UrlDecode(string $value): string
    {
        return base64_decode(
            strtr($value, '-_', '+/').str_repeat('=', (4 - strlen($value) % 4) % 4),
            true,
        );
    }
}
