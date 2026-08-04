<?php

namespace Tests\Unit\Services\Mail;

use App\Exceptions\UnsafeProductionMailConfiguration;
use App\Providers\ProductionMailServiceProvider;
use App\Services\Mail\ProductionMailTransportGuard;
use Aws\Ses\SesClient;
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

    public function test_dotted_mailer_collision_is_rejected_before_laravel_resolves_nested_config(): void
    {
        $this->configureProduction('delivery.smtp', [
            'delivery.smtp' => ['transport' => 'smtp'],
            'delivery' => [
                'smtp' => ['transport' => 'log'],
            ],
            'smtp' => ['transport' => 'smtp'],
        ]);

        $this->assertSame(
            ['transport' => 'log'],
            config('mail.mailers.delivery.smtp'),
            'Laravel MailManager resolves the nested dot-notation path, not the literal dotted key.',
        );

        $this->expectException(UnsafeProductionMailConfiguration::class);
        $this->expectExceptionMessage('selected mailer name');
        $this->guard()->assertSafeForProduction();
    }

    public function test_explicit_zero_selection_is_rejected_before_laravel_falls_back(): void
    {
        $this->configureProduction('smtp', ['smtp' => ['transport' => 'smtp']]);
        $this->assertSame('smtp', $this->app->make('mail.manager')->getDefaultDriver());
        $this->assertSame(['transport' => 'smtp'], config('mail.mailers.smtp'));

        $this->expectException(UnsafeProductionMailConfiguration::class);
        $this->guard()->assertSafeForProduction('0');
    }

    public function test_default_zero_selection_is_rejected_before_runtime_lookup(): void
    {
        $this->configureProduction('0', [
            '0' => ['transport' => 'smtp'],
            'smtp' => ['transport' => 'smtp'],
        ]);
        $this->assertSame('0', $this->app->make('mail.manager')->getDefaultDriver());
        $this->assertSame(['transport' => 'smtp'], config('mail.mailers.0'));

        $this->expectException(UnsafeProductionMailConfiguration::class);
        $this->expectExceptionMessage('selected mailer name');
        $this->guard()->assertSafeForProduction();
    }

    #[DataProvider('malformedSelectedMailerProvider')]
    public function test_malformed_default_mailer_selection_fails_closed(mixed $selected): void
    {
        $this->configureProduction($selected, [
            '0' => ['transport' => 'smtp'],
            'smtp' => ['transport' => 'smtp'],
        ]);

        $this->expectException(UnsafeProductionMailConfiguration::class);
        $this->expectExceptionMessage('selected mailer name');
        $this->guard()->assertSafeForProduction();
    }

    public static function malformedSelectedMailerProvider(): array
    {
        return [
            'empty' => [''],
            'whitespace' => ['  '],
        ];
    }

    public function test_malformed_child_identifier_is_rejected_before_dot_notation_resolution(): void
    {
        $this->configureProduction('delivery', [
            'delivery' => ['transport' => 'failover', 'mailers' => ['nested.smtp']],
            'nested' => ['smtp' => ['transport' => 'log']],
            'smtp' => ['transport' => 'smtp'],
        ]);
        $this->assertSame(['transport' => 'log'], config('mail.mailers.nested.smtp'));

        $this->expectException(UnsafeProductionMailConfiguration::class);
        $this->expectExceptionMessage('malformed reference');
        $this->guard()->assertSafeForProduction();
    }

    public function test_shared_safe_child_can_be_reached_from_independent_branches(): void
    {
        $this->configureProduction('delivery', [
            'delivery' => ['transport' => 'failover', 'mailers' => ['east', 'west']],
            'east' => ['transport' => 'failover', 'mailers' => ['shared']],
            'west' => ['transport' => 'roundrobin', 'mailers' => ['shared']],
            'shared' => ['transport' => 'smtp'],
        ]);

        $this->guard()->assertSafeForProduction();
        $this->addToAssertionCount(1);
    }

    public function test_malformed_mailer_registry_is_rejected(): void
    {
        $this->configureProduction('smtp', 'not-an-array');

        $this->expectException(UnsafeProductionMailConfiguration::class);
        $this->expectExceptionMessage('registry');
        $this->guard()->assertSafeForProduction();
    }

    public function test_malformed_individual_mailer_configuration_is_rejected(): void
    {
        $this->configureProduction('delivery', [
            'delivery' => 'not-an-array',
            'smtp' => ['transport' => 'smtp'],
        ]);

        $this->expectException(UnsafeProductionMailConfiguration::class);
        $this->expectExceptionMessage('configuration is malformed');
        $this->guard()->assertSafeForProduction();
    }

    public function test_non_string_transport_is_rejected(): void
    {
        $this->configureProduction('delivery', [
            'delivery' => ['transport' => 123],
            'smtp' => ['transport' => 'smtp'],
        ]);

        $this->expectException(UnsafeProductionMailConfiguration::class);
        $this->expectExceptionMessage('transport is missing or malformed');
        $this->guard()->assertSafeForProduction();
    }

    public function test_legacy_mail_driver_ambiguity_is_rejected(): void
    {
        $this->configureProduction('smtp', ['smtp' => ['transport' => 'smtp']]);
        config(['mail.driver' => 'smtp']);

        $this->expectException(UnsafeProductionMailConfiguration::class);
        $this->expectExceptionMessage('Legacy mail.driver');
        $this->guard()->assertSafeForProduction();
    }

    #[DataProvider('unsafeUrlProvider')]
    public function test_unsafe_or_malformed_url_configuration_is_rejected(mixed $url): void
    {
        $this->configureProduction('delivery', [
            'delivery' => ['transport' => 'smtp', 'url' => $url],
            'smtp' => ['transport' => 'smtp'],
        ]);

        $this->expectException(UnsafeProductionMailConfiguration::class);
        $this->guard()->assertSafeForProduction();
    }

    public static function unsafeUrlProvider(): array
    {
        return [
            'malformed URL' => ['http://['],
            'array URL' => [['smtp://localhost']],
            'unsupported custom URL' => ['custom://localhost'],
        ];
    }

    public function test_safe_smtp_url_and_uppercase_driver_are_normalized(): void
    {
        foreach (['smtp://localhost:2525', 'SMTP://localhost:2525'] as $url) {
            $this->configureProduction('delivery', [
                'delivery' => ['transport' => 'log', 'url' => $url],
                'smtp' => ['transport' => 'smtp'],
            ]);

            $this->guard()->assertSafeForProduction();
        }

        $this->addToAssertionCount(2);
    }

    public function test_url_credentials_do_not_leak_from_diagnostics(): void
    {
        $secret = 'smtp-url-password-must-not-leak';
        $this->configureProduction('delivery', [
            'delivery' => [
                'transport' => 'smtp',
                'url' => 'custom://user:'.$secret.'@localhost',
            ],
            'smtp' => ['transport' => 'smtp'],
        ]);

        try {
            $this->guard()->assertSafeForProduction();
            $this->fail('Expected unsupported URL transport to be rejected.');
        } catch (UnsafeProductionMailConfiguration $exception) {
            $this->assertStringNotContainsString($secret, $exception->getMessage());
            $this->assertStringContainsString('delivery -> custom', $exception->getMessage());
        }
    }

    public function test_malformed_allow_list_identifier_is_rejected(): void
    {
        $this->configureProduction(
            'smtp',
            ['smtp' => ['transport' => 'smtp']],
            'smtp/bypass',
        );

        $this->expectException(UnsafeProductionMailConfiguration::class);
        $this->expectExceptionMessage('allow-list');
        $this->guard()->assertSafeForProduction();
    }

    public function test_unavailable_optional_transport_dependency_is_rejected(): void
    {
        $this->assertFalse(class_exists(SesClient::class));
        $this->configureProduction('ses', ['ses' => ['transport' => 'ses']], 'ses');

        $this->expectException(UnsafeProductionMailConfiguration::class);
        $this->expectExceptionMessage('unknown value');
        $this->guard()->assertSafeForProduction();
    }

    public function test_testing_array_boot_remains_valid(): void
    {
        config([
            'mail.default' => 'array',
            'mail.mailers' => ['array' => ['transport' => 'array']],
        ]);

        $this->provider()->boot($this->guard());
        $this->assertTrue(app()->environment('testing'));
    }

    public function test_loaded_config_is_shared_by_guard_and_mail_manager_and_env_cannot_bypass_it(): void
    {
        $original = getenv('MAIL_MAILER');
        putenv('MAIL_MAILER=array');
        $_ENV['MAIL_MAILER'] = 'array';
        $this->configureProduction('smtp', ['smtp' => ['transport' => 'smtp']]);

        try {
            $this->guard()->assertSafeForProduction();
            $this->assertSame('smtp', $this->app->make('mail.manager')->getDefaultDriver());
            $this->assertSame(['transport' => 'smtp'], config('mail.mailers.smtp'));
        } finally {
            if ($original === false) {
                putenv('MAIL_MAILER');
                unset($_ENV['MAIL_MAILER']);
            } else {
                putenv('MAIL_MAILER='.$original);
                $_ENV['MAIL_MAILER'] = $original;
            }
        }
    }

    private function configureProduction(
        mixed $default,
        mixed $mailers,
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
