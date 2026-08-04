<?php

namespace Tests\Unit\Services\Mail;

use App\Exceptions\UnsafeProductionMailConfiguration;
use App\Providers\ProductionMailServiceProvider;
use App\Services\Mail\ProductionMailTransportGuard;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ProductionMailTransportGuardTest extends TestCase
{
    #[DataProvider('unsafeGraphProvider')]
    public function test_production_rejects_every_graph_with_an_unsafe_leaf(
        string $default,
        array $mailers,
        string $path,
    ): void {
        $this->configureProduction($default, $mailers);

        try {
            $this->guard()->assertSafeForProduction();
            $this->fail('Expected unsafe production mail configuration to be rejected.');
        } catch (UnsafeProductionMailConfiguration $exception) {
            $this->assertStringContainsString($path, $exception->getMessage());
        }
    }

    public static function unsafeGraphProvider(): array
    {
        return [
            'direct log' => [
                'log',
                ['log' => ['transport' => 'log'], 'smtp' => ['transport' => 'smtp']],
                'log',
            ],
            'direct array' => [
                'array',
                ['array' => ['transport' => 'array'], 'smtp' => ['transport' => 'smtp']],
                'array',
            ],
            'failover smtp then log' => [
                'delivery',
                [
                    'delivery' => ['transport' => 'failover', 'mailers' => ['primary', 'audit']],
                    'primary' => ['transport' => 'smtp'],
                    'audit' => ['transport' => 'log'],
                ],
                'delivery -> failover -> audit -> log',
            ],
            'failover log then smtp' => [
                'delivery',
                [
                    'delivery' => ['transport' => 'failover', 'mailers' => ['audit', 'primary']],
                    'primary' => ['transport' => 'smtp'],
                    'audit' => ['transport' => 'log'],
                ],
                'delivery -> failover -> audit -> log',
            ],
            'nested failover array' => [
                'delivery',
                [
                    'delivery' => ['transport' => 'failover', 'mailers' => ['primary', 'backup-group']],
                    'primary' => ['transport' => 'smtp'],
                    'backup-group' => ['transport' => 'failover', 'mailers' => ['memory']],
                    'memory' => ['transport' => 'array'],
                ],
                'delivery -> failover -> backup-group -> failover -> memory -> array',
            ],
            'round robin log' => [
                'delivery',
                [
                    'delivery' => ['transport' => 'roundrobin', 'mailers' => ['primary', 'audit']],
                    'primary' => ['transport' => 'smtp'],
                    'audit' => ['transport' => 'log'],
                ],
                'delivery -> roundrobin -> audit -> log',
            ],
            'log alias' => [
                'production',
                ['production' => ['transport' => 'log'], 'smtp' => ['transport' => 'smtp']],
                'production -> log',
            ],
            'array alias' => [
                'corp',
                ['corp' => ['transport' => 'array'], 'smtp' => ['transport' => 'smtp']],
                'corp -> array',
            ],
            'missing child' => [
                'delivery',
                [
                    'delivery' => ['transport' => 'failover', 'mailers' => ['primary', 'missing']],
                    'primary' => ['transport' => 'smtp'],
                ],
                'delivery -> failover -> missing',
            ],
            'circular graph' => [
                'delivery',
                [
                    'delivery' => ['transport' => 'failover', 'mailers' => ['backup']],
                    'backup' => ['transport' => 'roundrobin', 'mailers' => ['delivery']],
                    'smtp' => ['transport' => 'smtp'],
                ],
                'delivery -> failover -> backup -> roundrobin -> delivery',
            ],
            'empty composite' => [
                'delivery',
                [
                    'delivery' => ['transport' => 'failover', 'mailers' => []],
                    'smtp' => ['transport' => 'smtp'],
                ],
                'delivery -> failover',
            ],
            'malformed composite list' => [
                'delivery',
                [
                    'delivery' => ['transport' => 'failover', 'mailers' => ['primary' => 'smtp']],
                    'smtp' => ['transport' => 'smtp'],
                ],
                'delivery -> failover',
            ],
            'duplicate composite child' => [
                'delivery',
                [
                    'delivery' => ['transport' => 'failover', 'mailers' => ['smtp', 'smtp']],
                    'smtp' => ['transport' => 'smtp'],
                ],
                'delivery -> failover',
            ],
            'unknown custom leaf' => [
                'delivery',
                ['delivery' => ['transport' => 'custom-api'], 'smtp' => ['transport' => 'smtp']],
                'delivery -> custom-api',
            ],
            'safe sounding mailer with disallowed leaf' => [
                'production-smtp',
                ['production-smtp' => ['transport' => 'log'], 'smtp' => ['transport' => 'smtp']],
                'production-smtp -> log',
            ],
            'missing transport' => [
                'delivery',
                ['delivery' => [], 'smtp' => ['transport' => 'smtp']],
                'delivery',
            ],
            'URL overrides safe-looking transport' => [
                'delivery',
                [
                    'delivery' => ['transport' => 'smtp', 'url' => 'log://localhost'],
                    'smtp' => ['transport' => 'smtp'],
                ],
                'delivery -> log',
            ],
        ];
    }

    public function test_safe_nested_composite_with_only_approved_leaves_is_accepted(): void
    {
        $this->configureProduction('delivery', [
            'delivery' => ['transport' => 'failover', 'mailers' => ['primary', 'regional']],
            'primary' => ['transport' => 'smtp'],
            'regional' => ['transport' => 'roundrobin', 'mailers' => ['backup-a', 'backup-b']],
            'backup-a' => ['transport' => 'smtp'],
            'backup-b' => ['transport' => 'smtp'],
        ]);

        $this->guard()->assertSafeForProduction();

        $this->addToAssertionCount(1);
    }

    #[DataProvider('invalidAllowListProvider')]
    public function test_invalid_production_allow_list_is_rejected(mixed $allowed): void
    {
        $this->configureProduction('smtp', ['smtp' => ['transport' => 'smtp']], $allowed);

        $this->expectException(UnsafeProductionMailConfiguration::class);
        $this->expectExceptionMessage('allowed-transports');

        $this->guard()->assertSafeForProduction();
    }

    public static function invalidAllowListProvider(): array
    {
        return [
            'null' => [null],
            'array' => [['smtp']],
            'empty' => [''],
            'empty item' => ['smtp,'],
            'duplicate' => ['smtp, SMTP'],
            'log' => ['log'],
            'array transport' => ['array'],
            'null transport' => ['null'],
            'failover' => ['failover'],
            'round robin' => ['roundrobin'],
            'unknown' => ['not-configured'],
        ];
    }

    public function test_custom_leaf_cannot_be_approved_by_the_allow_list_alone(): void
    {
        $this->configureProduction(
            'provider',
            [
                'provider' => ['transport' => 'corp-api'],
                'smtp' => ['transport' => 'smtp'],
            ],
            'smtp,corp-api',
        );

        $this->expectException(UnsafeProductionMailConfiguration::class);
        $this->expectExceptionMessage('unknown value');

        $this->guard()->assertSafeForProduction();
    }

    public function test_additional_inspectable_leaf_requires_explicit_approval(): void
    {
        $this->configureProduction(
            'local-mta',
            [
                'local-mta' => ['transport' => 'sendmail'],
                'smtp' => ['transport' => 'smtp'],
            ],
            ' SMTP , SENDMAIL ',
        );

        $this->guard()->assertSafeForProduction();

        $this->addToAssertionCount(1);
    }

    public function test_excessive_graph_depth_is_rejected(): void
    {
        $mailers = ['smtp' => ['transport' => 'smtp']];
        for ($index = 0; $index <= 32; $index++) {
            $mailers['node-'.$index] = [
                'transport' => 'failover',
                'mailers' => [$index === 32 ? 'smtp' : 'node-'.($index + 1)],
            ];
        }
        $this->configureProduction('node-0', $mailers);

        $this->expectException(UnsafeProductionMailConfiguration::class);
        $this->expectExceptionMessage('maximum supported depth');

        $this->guard()->assertSafeForProduction();
    }

    public function test_non_production_array_mailer_remains_allowed(): void
    {
        config([
            'mail.default' => 'array',
            'mail.mailers' => ['array' => ['transport' => 'array']],
            'mail.production_allowed_transports' => null,
        ]);

        $this->guard()->assertSafeForProduction();

        $this->assertTrue(app()->environment('testing'));
    }

    public function test_diagnostics_show_only_sanitized_graph_path_and_no_secrets(): void
    {
        $password = 'smtp-password-must-not-leak';
        $providerKey = 'provider-key-must-not-leak';
        $guestFragment = '#token=guest-capability-must-not-leak';
        $this->configureProduction('delivery', [
            'delivery' => ['transport' => 'failover', 'mailers' => ['primary', 'audit']],
            'primary' => [
                'transport' => 'smtp',
                'password' => $password,
                'key' => $providerKey,
            ],
            'audit' => [
                'transport' => 'log',
                'message' => $guestFragment,
            ],
        ]);

        try {
            $this->guard()->assertSafeForProduction();
            $this->fail('Expected unsafe production mail configuration to be rejected.');
        } catch (UnsafeProductionMailConfiguration $exception) {
            $message = $exception->getMessage();
            $this->assertStringContainsString('delivery -> failover -> audit -> log', $message);
            $this->assertStringNotContainsString($password, $message);
            $this->assertStringNotContainsString($providerKey, $message);
            $this->assertStringNotContainsString($guestFragment, $message);
        }
    }

    public function test_production_boot_provider_rejects_an_unsafe_graph(): void
    {
        $this->configureProduction('delivery', [
            'delivery' => ['transport' => 'failover', 'mailers' => ['smtp', 'log']],
            'smtp' => ['transport' => 'smtp'],
            'log' => ['transport' => 'log'],
        ]);

        $this->expectException(UnsafeProductionMailConfiguration::class);

        $this->provider()->boot($this->guard());
    }

    public function test_production_boot_provider_accepts_a_safe_graph(): void
    {
        $this->configureProduction('delivery', [
            'delivery' => ['transport' => 'failover', 'mailers' => ['primary', 'backup']],
            'primary' => ['transport' => 'smtp'],
            'backup' => ['transport' => 'smtp'],
        ]);

        $this->provider()->boot($this->guard());

        $this->addToAssertionCount(1);
    }

    private function configureProduction(
        string $default,
        array $mailers,
        mixed $allowed = 'smtp',
    ): void {
        $this->app->detectEnvironment(static fn (): string => 'production');
        config([
            'mail.default' => $default,
            'mail.driver' => null,
            'mail.mailers' => $mailers,
            'mail.production_allowed_transports' => $allowed,
        ]);
    }

    private function guard(): ProductionMailTransportGuard
    {
        return $this->app->make(ProductionMailTransportGuard::class);
    }

    private function provider(): ProductionMailServiceProvider
    {
        return new ProductionMailServiceProvider($this->app);
    }
}
