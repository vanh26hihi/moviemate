<?php

namespace Tests\Unit;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Tests\Support\DatabaseSafetyGuard;

class DatabaseSafetyGuardTest extends TestCase
{
    public function test_it_rejects_the_primary_mysql_database(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('refusing to use MySQL database [moviemate]');

        DatabaseSafetyGuard::assertSafe([
            'driver' => 'mysql',
            'database' => 'moviemate',
        ]);
    }

    public function test_it_checks_values_resolved_from_a_database_url(): void
    {
        $this->expectException(RuntimeException::class);

        DatabaseSafetyGuard::assertSafe([
            'driver' => 'sqlite',
            'database' => ':memory:',
            'url' => 'mysql://root@127.0.0.1/moviemate',
        ]);
    }

    /**
     * @param  array<string, mixed>  $configuration
     */
    #[DataProvider('safeDatabaseConfigurations')]
    public function test_it_allows_safe_database_configurations(array $configuration): void
    {
        DatabaseSafetyGuard::assertSafe($configuration);

        $this->addToAssertionCount(1);
    }

    /**
     * @return array<string, array{array<string, string>}>
     */
    public static function safeDatabaseConfigurations(): array
    {
        return [
            'SQLite in-memory' => [[
                'driver' => 'sqlite',
                'database' => ':memory:',
            ]],
            'dedicated moviemate testing database' => [[
                'driver' => 'mysql',
                'database' => 'moviemate_testing',
            ]],
            '_test suffix' => [[
                'driver' => 'mysql',
                'database' => 'moviemate_test',
            ]],
            '_testing suffix' => [[
                'driver' => 'mysql',
                'database' => 'integration_testing',
            ]],
        ];
    }
}
