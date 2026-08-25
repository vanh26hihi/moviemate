<?php

namespace Tests\Unit\Services\Mail;

use App\Services\Mail\TicketMailConfigurationInspector;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class TicketMailConfigurationInspectorTest extends TestCase
{
    public function test_testing_array_transport_remains_usable_for_mail_fakes(): void
    {
        config([
            'mail.default' => 'array',
            'mail.mailers' => ['array' => ['transport' => 'array']],
            'mail.from.address' => 'tickets@example.test',
        ]);

        $report = $this->inspector()->inspect();

        $this->assertTrue($report['ready']);
        $this->assertSame('array', $report['transport']);
    }

    public function test_valid_smtp_configuration_is_delivery_ready_without_exposing_credentials(): void
    {
        $secret = 'smtp-secret-must-not-appear';
        config([
            'mail.default' => 'smtp',
            'mail.mailers' => [
                'smtp' => [
                    'transport' => 'smtp',
                    'host' => 'smtp.example.test',
                    'port' => 587,
                    'scheme' => 'tls',
                    'username' => 'mailer',
                    'password' => $secret,
                ],
            ],
            'mail.from.address' => 'tickets@example.test',
        ]);

        $report = $this->inspector()->inspect();

        $this->assertTrue($report['ready']);
        $this->assertSame('smtp', $report['transport']);
        $this->assertSame(587, $report['smtp_port']);
        $this->assertSame('tls', $report['encryption']);
        $this->assertStringNotContainsString($secret, json_encode($report));
    }

    #[DataProvider('blockedConfigurationProvider')]
    public function test_non_delivery_or_malformed_configuration_is_blocked(
        mixed $default,
        mixed $mailers,
        string $category,
    ): void {
        config([
            'mail.default' => $default,
            'mail.mailers' => $mailers,
            'mail.from.address' => 'tickets@example.test',
        ]);

        $report = $this->inspector()->inspect();

        $this->assertFalse($report['ready']);
        $this->assertSame($category, $report['category']);
    }

    public static function blockedConfigurationProvider(): array
    {
        return [
            'log' => ['log', ['log' => ['transport' => 'log']], 'MAILER_IS_LOG_ONLY'],
            'missing selected mailer' => ['missing', ['smtp' => ['transport' => 'smtp']], 'MAILER_NOT_CONFIGURED'],
            'malformed registry' => ['smtp', 'invalid', 'MAILER_NOT_CONFIGURED'],
            'smtp missing host' => ['smtp', ['smtp' => ['transport' => 'smtp', 'port' => 587]], 'MAILER_NOT_CONFIGURED'],
            'unavailable optional provider' => ['resend', ['resend' => ['transport' => 'resend']], 'MAILER_NOT_CONFIGURED'],
            'composite reaches log' => [
                'delivery',
                [
                    'delivery' => ['transport' => 'failover', 'mailers' => ['smtp', 'audit']],
                    'smtp' => ['transport' => 'smtp', 'host' => 'smtp.example.test', 'port' => 587],
                    'audit' => ['transport' => 'log'],
                ],
                'MAILER_IS_LOG_ONLY',
            ],
            'composite contains malformed smtp leaf' => [
                'delivery',
                [
                    'delivery' => ['transport' => 'failover', 'mailers' => ['primary', 'backup']],
                    'primary' => ['transport' => 'smtp', 'host' => 'smtp.example.test', 'port' => 587],
                    'backup' => ['transport' => 'smtp', 'port' => 587],
                ],
                'MAILER_NOT_CONFIGURED',
            ],
        ];
    }

    private function inspector(): TicketMailConfigurationInspector
    {
        return app(TicketMailConfigurationInspector::class);
    }
}
