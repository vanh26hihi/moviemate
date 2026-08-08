<?php

use App\Support\TrustedProxyConfiguration;

$environment = (string) env('APP_ENV', 'production');
$allowLocalWildcard = filter_var(
    env('TRUSTED_PROXIES_ALLOW_LOCAL_WILDCARD', false),
    FILTER_VALIDATE_BOOL,
);
$configuredProxies = (string) env('TRUSTED_PROXIES', '127.0.0.1,::1');
$wildcardRequested = TrustedProxyConfiguration::wildcardRequested($configuredProxies);

// Trusting the current caller is intentionally limited to an explicit local-only
// switch. Production should list the reverse proxy IP addresses or CIDR ranges.
$trustedProxies = TrustedProxyConfiguration::proxies(
    $configuredProxies,
    $environment,
    $allowLocalWildcard,
);

$configuredHosts = array_values(array_unique(array_filter(array_map(
    static fn (string $host): string => strtolower(rtrim(trim($host), '.')),
    explode(',', (string) env('TRUSTED_HOSTS', '')),
))));
$appHost = parse_url((string) env('APP_URL', ''), PHP_URL_HOST);
$paymentHosts = array_values(array_filter(array_map(
    static fn (string $host): string => strtolower(rtrim(trim($host), '.')),
    explode(',', (string) env('PAYMENT_PUBLIC_HOSTS', '')),
)));

if (is_string($appHost) && $appHost !== '') {
    $configuredHosts[] = strtolower(rtrim($appHost, '.'));
}

if (in_array($environment, ['local', 'testing'], true)) {
    $configuredHosts = [...$configuredHosts, 'localhost', '127.0.0.1', '::1'];
}

return [
    // Laravel's built-in TrustProxies middleware reads this key directly.
    'proxies' => $trustedProxies,
    'wildcard_requested' => $wildcardRequested,
    'local_wildcard_enabled' => $environment === 'local' && $allowLocalWildcard,

    // Exact hosts only: no implicit subdomains and no arbitrary Host values.
    'hosts' => array_values(array_unique([...$configuredHosts, ...$paymentHosts])),
];
