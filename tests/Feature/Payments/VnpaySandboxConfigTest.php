<?php

namespace Tests\Feature\Payments;

use App\Domain\Payments\VnpayConfig;
use App\Exceptions\PaymentConfigurationException;

class VnpaySandboxConfigTest extends VnpayPaymentTestCase
{
    public function test_valid_sandbox_configuration_is_accepted(): void
    {
        $this->assertSame('sandbox', (new VnpayConfig)->environment);
    }

    public function test_missing_or_malformed_tmn_code_is_rejected(): void
    {
        foreach ([null, '', 'short', 'bad code!', ' MOVIE12', 'YOURCODE'] as $value) {
            config(['payment.vnpay.tmn_code' => $value]);
            try {
                new VnpayConfig;
                $this->fail('Invalid TmnCode should fail.');
            } catch (PaymentConfigurationException) {
                $this->addToAssertionCount(1);
            }
        }
    }

    public function test_secret_whitespace_placeholder_and_wrappers_are_rejected_without_disclosure(): void
    {
        foreach ([
            str_repeat('x', 32).' ',
            "\n".str_repeat('x', 32),
            '<'.str_repeat('x', 32).'>',
            'your-secret-'.str_repeat('x', 32),
        ] as $secret) {
            config(['payment.vnpay.hash_secret' => $secret]);
            try {
                new VnpayConfig;
                $this->fail('Unsafe HashSecret should fail.');
            } catch (PaymentConfigurationException $exception) {
                $this->assertStringNotContainsString($secret, $exception->getMessage());
            }
        }
    }

    public function test_sandbox_environment_rejects_production_or_malformed_provider_endpoints(): void
    {
        foreach ([
            ['payment.vnpay.payment_url' => 'https://pay.vnpay.vn/vpcpay.html'],
            ['payment.vnpay.query_url' => 'https://merchant.vnpay.vn/api/transaction'],
            ['payment.vnpay.payment_url' => 'https://sandbox.vnpayment.vn/wrong'],
        ] as $override) {
            config($override);
            try {
                new VnpayConfig;
                $this->fail('Environment endpoint mismatch should fail.');
            } catch (PaymentConfigurationException) {
                $this->addToAssertionCount(1);
            } finally {
                config([
                    'payment.vnpay.payment_url' => 'https://sandbox.vnpayment.vn/paymentv2/vpcpay.html',
                    'payment.vnpay.query_url' => 'https://sandbox.vnpayment.vn/merchant_webapi/api/transaction',
                ]);
            }
        }
    }

    public function test_missing_secret_is_rejected_without_echoing_secret(): void
    {
        config(['payment.vnpay.hash_secret' => 'too-short-secret']);
        try {
            new VnpayConfig;
            $this->fail('Weak secret should fail.');
        } catch (PaymentConfigurationException $exception) {
            $this->assertStringNotContainsString('too-short-secret', $exception->getMessage());
        }
    }
}
