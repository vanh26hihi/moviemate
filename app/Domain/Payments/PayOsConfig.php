<?php

namespace App\Domain\Payments;

use App\Exceptions\PaymentConfigurationException;

final readonly class PayOsConfig
{
    public string $clientId;

    public string $apiKey;

    public string $checksumKey;

    public string $baseUrl;

    public int $connectTimeoutSeconds;

    public int $requestTimeoutSeconds;

    public function __construct()
    {
        $clientId = config('services.payos.client_id');
        $apiKey = config('services.payos.api_key');
        $checksumKey = config('services.payos.checksum_key');
        $baseUrl = config('services.payos.base_url');
        $connectTimeout = config('services.payos.connect_timeout_seconds');
        $requestTimeout = config('services.payos.request_timeout_seconds');

        foreach (['Client ID' => $clientId, 'API Key' => $apiKey, 'Checksum Key' => $checksumKey] as $name => $value) {
            if (! is_string($value)
                || strlen($value) < 8
                || $value !== trim($value)
                || preg_match('/^[\x21-\x7E]+$/D', $value) !== 1
                || preg_match('/placeholder|changeme|your[-_ ]?(?:key|id)/i', $value) === 1) {
                throw new PaymentConfigurationException("A valid payOS {$name} is required.");
            }
        }

        $parts = is_string($baseUrl) ? parse_url($baseUrl) : false;
        if (! is_array($parts)
            || strtolower((string) ($parts['scheme'] ?? '')) !== 'https'
            || ! is_string($parts['host'] ?? null)
            || ($parts['path'] ?? '') !== ''
            || isset($parts['user'], $parts['pass'], $parts['query'], $parts['fragment'])) {
            throw new PaymentConfigurationException('The payOS base URL must be a secure origin URL.');
        }
        if (app()->environment('production')
            && strtolower(rtrim($parts['host'], '.')) !== 'api-merchant.payos.vn') {
            throw new PaymentConfigurationException('Production payOS requests must use api-merchant.payos.vn.');
        }
        if (! is_int($connectTimeout) || ! is_int($requestTimeout)
            || $connectTimeout <= 0 || $requestTimeout <= 0 || $connectTimeout > $requestTimeout) {
            throw new PaymentConfigurationException('payOS HTTP timeouts are invalid.');
        }

        $this->clientId = $clientId;
        $this->apiKey = $apiKey;
        $this->checksumKey = $checksumKey;
        $this->baseUrl = rtrim($baseUrl, '/');
        $this->connectTimeoutSeconds = $connectTimeout;
        $this->requestTimeoutSeconds = $requestTimeout;
    }

    public static function isConfigured(): bool
    {
        foreach (['client_id', 'api_key', 'checksum_key'] as $key) {
            $value = config("services.payos.{$key}");
            if (! is_string($value) || trim($value) === '') {
                return false;
            }
        }

        return true;
    }

    public function assertMerchantUrl(string $url): void
    {
        $parts = parse_url($url);
        if (! is_array($parts) || ! is_string($parts['host'] ?? null) || isset($parts['user'], $parts['pass'])) {
            throw new PaymentConfigurationException('A valid payOS merchant URL is required.');
        }

        $host = strtolower(rtrim($parts['host'], '.'));
        $loopback = $host === 'localhost'
            || str_ends_with($host, '.localhost')
            || in_array($host, ['127.0.0.1', '::1'], true);
        if (! app()->environment('testing')
            && (strtolower((string) ($parts['scheme'] ?? '')) !== 'https' || $loopback)) {
            throw new PaymentConfigurationException('payOS return and cancel URLs require a public HTTPS host.');
        }
    }

    public function validCheckoutUrl(string $url): bool
    {
        $parts = parse_url($url);

        return is_array($parts)
            && strtolower((string) ($parts['scheme'] ?? '')) === 'https'
            && strtolower(rtrim((string) ($parts['host'] ?? ''), '.')) === 'pay.payos.vn'
            && ! isset($parts['user'], $parts['pass'])
            && preg_match('~^/web/[A-Za-z0-9_-]{8,100}$~D', (string) ($parts['path'] ?? '')) === 1;
    }
}
