<?php

namespace Tests\Feature\Payments;

use App\Domain\Payments\VnpayConfig;
use App\Exceptions\PaymentConfigurationException;
use Tests\TestCase;

class VnpayProductionConfigTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->app->detectEnvironment(static fn (): string => 'production');
        $this->app['url']->forceRootUrl('https://movies.example.com');
        $this->app['url']->forceScheme('https');
        config([
            'payment.public_hosts' => ['movies.example.com'],
            'payment.vnpay.environment' => 'production',
            'payment.vnpay.tmn_code' => 'MOVIE123',
            'payment.vnpay.hash_secret' => str_repeat('production-secret-', 3),
            'payment.vnpay.payment_url' => 'https://pay.vnpay.vn/vpcpay.html',
            'payment.vnpay.query_url' => 'https://merchant.vnpay.vn/api/transaction',
            'payment.vnpay.bank_code' => '',
            'payment.vnpay.locale' => 'vn',
            'payment.vnpay.order_type' => 'other',
            'payment.vnpay.payment_ttl_minutes' => 15,
            'payment.vnpay.http_timeout_seconds' => 10,
            'payment.vnpay.query_interval_seconds' => 60,
            'payment.vnpay.query_ip' => '203.0.113.10',
        ]);
    }

    public function test_valid_production_configuration_is_accepted(): void
    {
        $this->assertSame('production', (new VnpayConfig)->environment);
    }

    public function test_production_rejects_sandbox_environment(): void
    {
        config(['payment.vnpay.environment' => 'sandbox']);
        $this->expectException(PaymentConfigurationException::class);
        new VnpayConfig;
    }

    public function test_production_rejects_http_provider_urls(): void
    {
        config(['payment.vnpay.query_url' => 'http://merchant.vnpay.vn/api/transaction']);
        $this->expectException(PaymentConfigurationException::class);
        new VnpayConfig;
    }

    public function test_production_rejects_unapproved_or_loopback_merchant_hosts(): void
    {
        $this->app['url']->forceRootUrl('https://localhost');
        $this->expectException(PaymentConfigurationException::class);
        new VnpayConfig;
    }

    public function test_invalid_secret_error_never_contains_secret_value(): void
    {
        config(['payment.vnpay.hash_secret' => 'leaked-value']);
        try {
            new VnpayConfig;
            $this->fail('Weak secret should fail.');
        } catch (PaymentConfigurationException $exception) {
            $this->assertStringNotContainsString('leaked-value', $exception->getMessage());
        }
    }
}
