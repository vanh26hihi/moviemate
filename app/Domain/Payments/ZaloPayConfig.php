<?php

namespace App\Domain\Payments;

use App\Exceptions\PaymentConfigurationException;

final readonly class ZaloPayConfig
{
    public int $appId;

    public string $environment;

    public string $key1;

    public string $key2;

    public string $callbackUrl;

    public string $redirectUrl;

    public int $expireDurationSeconds;

    public int $httpTimeoutSeconds;

    public int $queryIntervalSeconds;

    public string $createEndpoint;

    public string $queryEndpoint;

    public function __construct()
    {
        if (config('payment.driver') !== 'zalopay') {
            throw new PaymentConfigurationException('The configured payment driver is not supported.');
        }

        $environment = config('payment.zalopay.environment');
        $appId = config('payment.zalopay.app_id');
        $key1 = config('payment.zalopay.key1');
        $key2 = config('payment.zalopay.key2');
        $callbackUrl = config('payment.zalopay.callback_url');
        $redirectUrl = config('payment.zalopay.redirect_url');
        $expireDuration = config('payment.zalopay.expire_duration_seconds');
        $timeout = config('payment.zalopay.http_timeout_seconds');
        $queryInterval = config('payment.zalopay.query_interval_seconds');

        if (! in_array($environment, ['sandbox', 'production'], true)) {
            throw new PaymentConfigurationException('ZaloPay environment must be sandbox or production.');
        }

        if (! is_numeric($appId) || (string) (int) $appId !== ltrim((string) $appId, '+') || (int) $appId <= 0) {
            throw new PaymentConfigurationException('A valid ZaloPay app ID is required.');
        }

        foreach (['key1' => $key1, 'key2' => $key2] as $name => $key) {
            if (! is_string($key) || trim($key) === '') {
                throw new PaymentConfigurationException("ZaloPay {$name} is required.");
            }
        }

        foreach (['callback URL' => $callbackUrl, 'redirect URL' => $redirectUrl] as $name => $url) {
            if (! is_string($url) || filter_var($url, FILTER_VALIDATE_URL) === false) {
                throw new PaymentConfigurationException("A valid ZaloPay {$name} is required.");
            }
        }

        if (app()->environment('production') && $environment !== 'production') {
            throw new PaymentConfigurationException('Production must use the ZaloPay production environment.');
        }

        if (app()->environment('production') || $environment === 'production') {
            $this->validatePublicUrl(
                $callbackUrl,
                (string) config('payment.zalopay.callback_path'),
                'callback',
            );
            $this->validatePublicUrl(
                $redirectUrl,
                (string) config('payment.zalopay.redirect_path'),
                'redirect',
            );
        }

        foreach (['expiration' => $expireDuration, 'HTTP timeout' => $timeout, 'query interval' => $queryInterval] as $name => $seconds) {
            if (! is_int($seconds) || $seconds <= 0) {
                throw new PaymentConfigurationException("ZaloPay {$name} must be a positive integer.");
            }
        }

        $createEndpoint = config("payment.zalopay.endpoints.{$environment}.create");
        $queryEndpoint = config("payment.zalopay.endpoints.{$environment}.query");

        if (! is_string($createEndpoint) || ! is_string($queryEndpoint)) {
            throw new PaymentConfigurationException('ZaloPay endpoints are not configured.');
        }

        $this->environment = $environment;
        $this->appId = (int) $appId;
        $this->key1 = $key1;
        $this->key2 = $key2;
        $this->callbackUrl = $callbackUrl;
        $this->redirectUrl = $redirectUrl;
        $this->expireDurationSeconds = $expireDuration;
        $this->httpTimeoutSeconds = $timeout;
        $this->queryIntervalSeconds = $queryInterval;
        $this->createEndpoint = $createEndpoint;
        $this->queryEndpoint = $queryEndpoint;
    }

    private function validatePublicUrl(string $url, string $expectedPath, string $name): void
    {
        $parts = parse_url($url);
        $hosts = config('payment.public_hosts');
        if (! is_array($parts)
            || strtolower((string) ($parts['scheme'] ?? '')) !== 'https'
            || ! is_string($parts['host'] ?? null)
            || ($parts['path'] ?? '/') !== $expectedPath
            || isset($parts['user'])
            || isset($parts['pass'])
            || isset($parts['query'])
            || isset($parts['fragment'])
            || ! is_array($hosts)
            || $hosts === []) {
            throw new PaymentConfigurationException("Production ZaloPay {$name} URL is not securely configured.");
        }

        $host = strtolower(rtrim($parts['host'], '.'));
        $allowedHosts = array_values(array_filter(array_map(
            static fn (mixed $configured): ?string => is_string($configured)
                ? strtolower(rtrim(trim($configured), '.'))
                : null,
            $hosts,
        )));
        if ($host === 'localhost'
            || str_ends_with($host, '.localhost')
            || $this->isLoopbackIp($host)
            || ! in_array($host, $allowedHosts, true)) {
            throw new PaymentConfigurationException("Production ZaloPay {$name} host is not allowed.");
        }
    }

    private function isLoopbackIp(string $host): bool
    {
        $ip = trim($host, '[]');
        if (filter_var($ip, FILTER_VALIDATE_IP) === false) {
            return false;
        }

        return $ip === '::1' || str_starts_with($ip, '127.');
    }
}
