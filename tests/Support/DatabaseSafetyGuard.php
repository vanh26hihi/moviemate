<?php

namespace Tests\Support;

use Illuminate\Database\ConfigurationUrlParser;
use RuntimeException;

final class DatabaseSafetyGuard
{
    /**
     * @param  array<string, mixed>|string  $configuration
     */
    public static function assertSafe(array|string $configuration): void
    {
        $resolved = (new ConfigurationUrlParser)->parseConfiguration($configuration);
        $driver = strtolower((string) ($resolved['driver'] ?? ''));
        $database = (string) ($resolved['database'] ?? '');

        if ($driver === 'mysql' && $database === 'moviemate') {
            throw new RuntimeException(
                'Unsafe PHPUnit database configuration: refusing to use MySQL database [moviemate].'
            );
        }
    }
}
