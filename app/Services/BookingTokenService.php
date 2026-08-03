<?php

namespace App\Services;

use App\Exceptions\BookingTokenConfigurationException;

class BookingTokenService
{
    private const CHECKOUT_VERSION = 'v1';

    public function issueCheckoutToken(): string
    {
        $payload = $this->base64UrlEncode(random_bytes(32));
        $signature = $this->signature($payload);

        return self::CHECKOUT_VERSION.'.'.$payload.'.'.$signature;
    }

    public function isValidCheckoutToken(?string $token): bool
    {
        if (! is_string($token)) {
            return false;
        }

        $parts = explode('.', $token);
        if (count($parts) !== 3 || $parts[0] !== self::CHECKOUT_VERSION) {
            return false;
        }

        [, $payload, $signature] = $parts;
        $decoded = $this->base64UrlDecode($payload);

        return $decoded !== false
            && strlen($decoded) === 32
            && hash_equals($this->signature($payload), $signature);
    }

    public function hash(string $token): string
    {
        return hash('sha256', $token);
    }

    public function guestAccessTokenForCheckout(string $checkoutToken): string
    {
        return $this->base64UrlEncode(hash_hmac(
            'sha256',
            'guest-access:'.$checkoutToken,
            $this->signingKey(),
            true
        ));
    }

    public function issueGuestAccessToken(): string
    {
        return $this->base64UrlEncode(random_bytes(32));
    }

    public function verifyHash(?string $storedHash, ?string $rawToken): bool
    {
        if (! is_string($storedHash) || strlen($storedHash) !== 64 || ! is_string($rawToken)) {
            return false;
        }

        return hash_equals($storedHash, $this->hash($rawToken));
    }

    private function signature(string $payload): string
    {
        return $this->base64UrlEncode(hash_hmac(
            'sha256',
            self::CHECKOUT_VERSION.'.'.$payload,
            $this->signingKey(),
            true
        ));
    }

    private function signingKey(): string
    {
        $configuredKey = config('app.key');

        if (! is_string($configuredKey) || $configuredKey === '') {
            throw new BookingTokenConfigurationException(
                'APP_KEY must be configured before booking tokens can be used.'
            );
        }

        $key = $configuredKey;
        if (str_starts_with($configuredKey, 'base64:')) {
            $key = base64_decode(substr($configuredKey, 7), true);

            if ($key === false) {
                throw new BookingTokenConfigurationException(
                    'APP_KEY contains invalid base64 data.'
                );
            }
        }

        if (strlen($key) < 32) {
            throw new BookingTokenConfigurationException(
                'APP_KEY must provide at least 256 bits of key material.'
            );
        }

        return $key;
    }

    private function base64UrlEncode(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }

    private function base64UrlDecode(string $value): string|false
    {
        $padding = (4 - strlen($value) % 4) % 4;

        return base64_decode(strtr($value, '-_', '+/').str_repeat('=', $padding), true);
    }
}
