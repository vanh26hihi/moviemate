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

        if ($driver === 'mysql' && $database !== 'moviemate_phase4_rehearsal') {
            throw new RuntimeException(
                'Unsafe PHPUnit database configuration: MySQL tests require [moviemate_phase4_rehearsal]; '
                ."refusing database [{$database}]."
            );
        }
    }
}
