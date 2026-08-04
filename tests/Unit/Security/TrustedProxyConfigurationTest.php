<?php

namespace Tests\Unit\Security;

use App\Support\TrustedProxyConfiguration;
use PHPUnit\Framework\TestCase;

class TrustedProxyConfigurationTest extends TestCase
{
    public function test_explicit_ip_addresses_and_cidr_ranges_are_preserved(): void
    {
        $this->assertSame(
            ['127.0.0.1', '::1', '10.20.0.0/16'],
            TrustedProxyConfiguration::proxies('127.0.0.1, ::1, 10.20.0.0/16', 'production', false),
        );
    }

    public function test_wildcard_is_denied_outside_an_explicit_local_opt_in(): void
    {
        $this->assertSame([], TrustedProxyConfiguration::proxies('*', 'production', true));
        $this->assertSame([], TrustedProxyConfiguration::proxies('*', 'local', false));
        $this->assertSame([], TrustedProxyConfiguration::proxies('REMOTE_ADDR', 'production', false));
        $this->assertSame([], TrustedProxyConfiguration::proxies('0.0.0.0/0,::/0', 'production', false));
    }

    public function test_wildcard_is_available_only_for_explicit_local_development(): void
    {
        $this->assertSame('*', TrustedProxyConfiguration::proxies('*', 'local', true));
        $this->assertTrue(TrustedProxyConfiguration::wildcardRequested('127.0.0.1,*'));
    }
}
