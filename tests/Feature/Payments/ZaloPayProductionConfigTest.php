<?php

namespace Tests\Feature\Payments;

use App\Domain\Payments\ZaloPayConfig;
use App\Exceptions\PaymentConfigurationException;
use Tests\TestCase;

class ZaloPayProductionConfigTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->app->detectEnvironment(static fn (): string => 'production');
        config([
            'payment.driver' => 'zalopay',
            'payment.public_hosts' => ['movies.example.com'],
            'payment.zalopay.environment' => 'production',
            'payment.zalopay.app_id' => 2553,
            'payment.zalopay.key1' => 'production-key-one',
            'payment.zalopay.key2' => 'production-key-two',
            'payment.zalopay.callback_url' => 'https://movies.example.com/payments/zalopay/callback',
            'payment.zalopay.redirect_url' => 'https://movies.example.com/payments/zalopay/return',
            'payment.zalopay.callback_path' => '/payments/zalopay/callback',
            'payment.zalopay.redirect_path' => '/payments/zalopay/return',
            'payment.zalopay.expire_duration_seconds' => 600,
            'payment.zalopay.http_timeout_seconds' => 10,
            'payment.zalopay.query_interval_seconds' => 60,
        ]);
    }

    public function test_valid_production_https_urls_on_configured_public_host_are_accepted(): void
    {
        $config = new ZaloPayConfig;

        $this->assertSame('https://movies.example.com/payments/zalopay/callback', $config->callbackUrl);
        $this->assertSame('https://movies.example.com/payments/zalopay/return', $config->redirectUrl);
    }

    public function test_production_rejects_non_https_callback_or_redirect(): void
    {
        config(['payment.zalopay.callback_url' => 'http://movies.example.com/payments/zalopay/callback']);

        $this->expectException(PaymentConfigurationException::class);
        new ZaloPayConfig;
    }

    public function test_production_rejects_wrong_callback_path(): void
    {
        config(['payment.zalopay.callback_url' => 'https://movies.example.com/payments/callback']);

        $this->expectException(PaymentConfigurationException::class);
        new ZaloPayConfig;
    }

    public function test_production_rejects_unconfigured_host_and_localhost(): void
    {
        config([
            'payment.public_hosts' => ['movies.example.com'],
            'payment.zalopay.callback_url' => 'https://localhost/payments/zalopay/callback',
        ]);

        $this->expectException(PaymentConfigurationException::class);
        new ZaloPayConfig;
    }

    public function test_production_fails_closed_when_public_hosts_are_missing(): void
    {
        config(['payment.public_hosts' => []]);

        $this->expectException(PaymentConfigurationException::class);
        new ZaloPayConfig;
    }

    public function test_production_cannot_fall_back_to_sandbox(): void
    {
        config(['payment.zalopay.environment' => 'sandbox']);

        $this->expectException(PaymentConfigurationException::class);
        new ZaloPayConfig;
    }
}
