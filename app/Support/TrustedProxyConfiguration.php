<?php

namespace App\Support;

final class TrustedProxyConfiguration
{
    private const CALLER_WILDCARDS = ['*', '**', 'REMOTE_ADDR', '0.0.0.0/0', '::/0'];

    /** @return array<int, string>|string */
    public static function proxies(string $configured, string $environment, bool $allowLocalWildcard): array|string
    {
        $proxies = array_values(array_unique(array_filter(array_map(
            'trim',
            explode(',', $configured),
        ))));
        $wildcardRequested = array_intersect($proxies, self::CALLER_WILDCARDS) !== [];

        if ($wildcardRequested && $environment === 'local' && $allowLocalWildcard) {
            return '*';
        }

        return array_values(array_diff($proxies, self::CALLER_WILDCARDS));
    }

    public static function wildcardRequested(string $configured): bool
    {
        $proxies = array_map('trim', explode(',', $configured));

        return array_intersect($proxies, self::CALLER_WILDCARDS) !== [];
    }
}
