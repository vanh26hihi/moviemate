<?php

namespace Tests\Feature\Security;

use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class HttpsDiagnosticsCommandTest extends TestCase
{
    public function test_command_reports_safe_https_status_without_printing_secrets(): void
    {
        $secrets = [
            'diagnostic-app-key-must-not-appear',
            'diagnostic-payment-secret-must-not-appear',
            'diagnostic-mail-password-must-not-appear',
        ];
        config([
            'app.url' => 'https://public.example.test',
            'app.key' => $secrets[0],
            'mail.mailers.smtp.password' => $secrets[2],
            'payment.public_hosts' => ['public.example.test'],
            'payment.vnpay.hash_secret' => $secrets[1],
            'session.domain' => null,
            'session.secure' => true,
            'session.same_site' => 'lax',
            'trustedproxy.proxies' => ['127.0.0.1', '::1'],
            'trustedproxy.wildcard_requested' => false,
            'trustedproxy.local_wildcard_enabled' => false,
        ]);
        $this->app['url']->forceRootUrl('https://public.example.test');
        $this->app['url']->forceScheme('https');

        $this->assertSame(0, Artisan::call('app:https-diagnostics'));
        $output = Artisan::output();

        $this->assertStringContainsString('https://public.example.test/login', $output);
        $this->assertStringContainsString('https://public.example.test/payments/vnpay/return', $output);
        $this->assertStringContainsString('https://public.example.test/payments/vnpay/ipn', $output);
        $this->assertStringContainsString('loopback only', $output);
        foreach ($secrets as $secret) {
            $this->assertStringNotContainsString($secret, $output);
        }
    }

    public function test_command_gives_actionable_http_session_host_and_proxy_warnings(): void
    {
        config([
            'app.url' => 'http://localhost',
            'payment.public_hosts' => [],
            'session.secure' => false,
            'trustedproxy.proxies' => [],
            'trustedproxy.wildcard_requested' => false,
        ]);

        $this->assertSame(0, Artisan::call('app:https-diagnostics'));
        $output = Artisan::output();

        $this->assertStringContainsString('APP_URL is HTTP', $output);
        $this->assertStringContainsString('APP_URL host is not included in PAYMENT_PUBLIC_HOSTS', $output);
        $this->assertStringContainsString('No trusted proxies are configured', $output);
    }
}
