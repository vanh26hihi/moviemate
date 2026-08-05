<?php

namespace App\Domain\Payments;

use App\Exceptions\PaymentConfigurationException;

final readonly class VnpayConfig
{
    public const TIMEZONE = 'Asia/Ho_Chi_Minh';

    public string $environment;

    public string $tmnCode;

    public string $hashSecret;

    public string $paymentUrl;

    public string $queryUrl;

    public string $bankCode;

    public string $locale;

    public string $orderType;

    public int $paymentTtlMinutes;

    public int $httpTimeoutSeconds;

    public int $queryIntervalSeconds;

    public string $queryIp;

    public function __construct()
    {
        $environment = config('payment.vnpay.environment');
        $tmnCode = config('payment.vnpay.tmn_code');
        $hashSecret = config('payment.vnpay.hash_secret');
        $paymentUrl = config('payment.vnpay.payment_url');
        $queryUrl = config('payment.vnpay.query_url');
        $bankCode = config('payment.vnpay.bank_code');
        $locale = config('payment.vnpay.locale');
        $orderType = config('payment.vnpay.order_type');
        $ttl = config('payment.vnpay.payment_ttl_minutes');
        $timeout = config('payment.vnpay.http_timeout_seconds');
        $queryInterval = config('payment.vnpay.query_interval_seconds');
        $queryIp = config('payment.vnpay.query_ip');

        if (! in_array($environment, ['sandbox', 'production'], true)) {
            throw new PaymentConfigurationException('VNPAY environment must be sandbox or production.');
        }
        if (! is_string($tmnCode) || preg_match('/^[A-Za-z0-9]{8}$/D', $tmnCode) !== 1) {
            throw new PaymentConfigurationException('A valid 8-character VNPAY TmnCode is required.');
        }
        if (! is_string($hashSecret) || strlen($hashSecret) < 32) {
            throw new PaymentConfigurationException('A strong VNPAY HashSecret is required.');
        }
        foreach (['payment' => $paymentUrl, 'query' => $queryUrl] as $name => $url) {
            if (! is_string($url) || filter_var($url, FILTER_VALIDATE_URL) === false) {
                throw new PaymentConfigurationException("A valid VNPAY {$name} URL is required.");
            }
            if (app()->environment('production') && strtolower((string) parse_url($url, PHP_URL_SCHEME)) !== 'https') {
                throw new PaymentConfigurationException("Production VNPAY {$name} URL must use HTTPS.");
            }
        }
        if (! is_string($bankCode) || ($bankCode !== '' && preg_match('/^[A-Za-z0-9]{2,20}$/D', $bankCode) !== 1)) {
            throw new PaymentConfigurationException('VNPAY bank code is invalid.');
        }
        if (! in_array($locale, ['vn', 'en'], true)) {
            throw new PaymentConfigurationException('VNPAY locale must be vn or en.');
        }
        if (! is_string($orderType) || preg_match('/^[A-Za-z0-9_-]{1,100}$/D', $orderType) !== 1) {
            throw new PaymentConfigurationException('VNPAY order type is invalid.');
        }
        foreach (['payment TTL' => $ttl, 'HTTP timeout' => $timeout, 'query interval' => $queryInterval] as $name => $value) {
            if (! is_int($value) || $value <= 0) {
                throw new PaymentConfigurationException("VNPAY {$name} must be a positive integer.");
            }
        }
        if (! is_string($queryIp) || filter_var($queryIp, FILTER_VALIDATE_IP) === false) {
            throw new PaymentConfigurationException('VNPAY query IP address is invalid.');
        }

        if (app()->environment('production')) {
            if ($environment !== 'production') {
                throw new PaymentConfigurationException('Production must use the VNPAY production environment.');
            }
            $this->validateMerchantUrl(route('payments.vnpay.return'));
            $this->validateMerchantUrl(route('payments.vnpay.ipn'));
        }

        $this->environment = $environment;
        $this->tmnCode = $tmnCode;
        $this->hashSecret = $hashSecret;
        $this->paymentUrl = $paymentUrl;
        $this->queryUrl = $queryUrl;
        $this->bankCode = $bankCode;
        $this->locale = $locale;
        $this->orderType = $orderType;
        $this->paymentTtlMinutes = $ttl;
        $this->httpTimeoutSeconds = $timeout;
        $this->queryIntervalSeconds = $queryInterval;
        $this->queryIp = $queryIp;
    }

    public static function isConfigured(): bool
    {
        return is_string(config('payment.vnpay.tmn_code'))
            && trim((string) config('payment.vnpay.tmn_code')) !== ''
            && is_string(config('payment.vnpay.hash_secret'))
            && trim((string) config('payment.vnpay.hash_secret')) !== '';
    }

    public function returnUrl(string $state): string
    {
        return route('payments.vnpay.return', ['state' => $state]);
    }

    public function ipnUrl(): string
    {
        return route('payments.vnpay.ipn');
    }

    private function validateMerchantUrl(string $url): void
    {
        $parts = parse_url($url);
        $configuredHosts = config('payment.public_hosts');
        if (! is_array($parts)
            || strtolower((string) ($parts['scheme'] ?? '')) !== 'https'
            || ! is_string($parts['host'] ?? null)
            || isset($parts['user'])
            || isset($parts['pass'])
            || ! is_array($configuredHosts)
            || $configuredHosts === []) {
            throw new PaymentConfigurationException('Production VNPAY merchant URLs are not securely configured.');
        }

        $host = strtolower(rtrim($parts['host'], '.'));
        $allowed = array_values(array_filter(array_map(
            static fn (mixed $value): ?string => is_string($value)
                ? strtolower(rtrim(trim($value), '.'))
                : null,
            $configuredHosts,
        )));
        if ($host === 'localhost'
            || str_ends_with($host, '.localhost')
            || $this->isLoopback($host)
            || ! in_array($host, $allowed, true)) {
            throw new PaymentConfigurationException('Production VNPAY merchant host is not allowed.');
        }
    }

    private function isLoopback(string $host): bool
    {
        $ip = trim($host, '[]');

        return filter_var($ip, FILTER_VALIDATE_IP) !== false
            && ($ip === '::1' || str_starts_with($ip, '127.'));
    }
}
