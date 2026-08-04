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
        foreach ([null, '', 'short', 'bad code!'] as $value) {
            config(['payment.vnpay.tmn_code' => $value]);
            try {
                new VnpayConfig;
                $this->fail('Invalid TmnCode should fail.');
            } catch (PaymentConfigurationException) {
                $this->addToAssertionCount(1);
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
