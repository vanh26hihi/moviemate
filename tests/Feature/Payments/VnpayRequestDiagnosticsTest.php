<?php

namespace Tests\Feature\Payments;

use Illuminate\Support\Facades\Artisan;

class VnpayRequestDiagnosticsTest extends VnpayPaymentTestCase
{
    public function test_valid_existing_attempt_passes_without_printing_secret_signature_or_signed_url(): void
    {
        config(['payment.vnpay.bank_code' => '']);
        $payment = $this->vnpayPayment();
        $secret = (string) config('payment.vnpay.hash_secret');

        $exit = Artisan::call('payments:vnpay-request-diagnostics', [
            '--payment' => (string) $payment->id,
            '--client-ip' => '203.0.113.7',
        ]);
        $output = Artisan::output();

        $this->assertSame(0, $exit);
        $this->assertStringContainsString('VNPAY PAY request contract passes', $output);
        $this->assertStringContainsString('BankCode omitted', $output);
        $this->assertStringContainsString('SecureHashType omitted', $output);
        $this->assertStringContainsString('Canonical SHA-256', $output);
        $this->assertStringContainsString('?state=[redacted]', $output);
        $this->assertStringNotContainsString($secret, $output);
        $this->assertStringNotContainsString('vnp_SecureHash=', $output);
        $this->assertStringNotContainsString('APP_KEY', $output);
    }

    public function test_invalid_attempt_returns_non_zero_and_still_does_not_disclose_credentials(): void
    {
        $payment = $this->vnpayPayment(overrides: ['order_code' => 'INVALID-TXN-REF']);
        $secret = (string) config('payment.vnpay.hash_secret');

        $exit = Artisan::call('payments:vnpay-request-diagnostics', [
            '--payment' => (string) $payment->id,
        ]);
        $output = Artisan::output();

        $this->assertSame(1, $exit);
        $this->assertStringContainsString('Request reconstruction failed safely', $output);
        $this->assertStringNotContainsString($secret, $output);
        $this->assertStringNotContainsString('vnp_SecureHash=', $output);
    }

    public function test_invalid_secret_configuration_never_appears_in_diagnostic_output(): void
    {
        $payment = $this->vnpayPayment();
        $unsafeSecret = str_repeat('s', 32).' ';
        config(['payment.vnpay.hash_secret' => $unsafeSecret]);

        $exit = Artisan::call('payments:vnpay-request-diagnostics', [
            '--payment' => (string) $payment->id,
        ]);
        $output = Artisan::output();

        $this->assertSame(1, $exit);
        $this->assertStringNotContainsString($unsafeSecret, $output);
        $this->assertStringNotContainsString('vnp_SecureHash=', $output);
    }
}
