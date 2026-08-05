<?php

namespace App\Services\Payments;

use App\Exceptions\PaymentConfigurationException;
use App\Models\Payment;
use Illuminate\Http\Request;
use JsonException;

class PaymentReturnTokenService
{
    private const CURRENT_VERSION = 'v2';

    private const LEGACY_VERSION = 'v1';

    private const AUDIENCE = 'payment-return';

    private const SESSION_KEY = 'payment_return_attempts';

    public function issue(Payment $payment): string
    {
        $paymentId = $payment->getKey();
        if (! is_int($paymentId) || $paymentId <= 0) {
            throw new PaymentConfigurationException('Payment return state requires a persisted payment.');
        }

        $payload = implode('.', [
            self::CURRENT_VERSION,
            base_convert((string) $paymentId, 10, 36),
            base_convert((string) (now()->getTimestamp() + ($this->ttlMinutes() * 60)), 10, 36),
            $this->base64UrlEncode(random_bytes(12)),
        ]);

        return $payload.'.'.$this->compactSignature($payload, $payment);
    }

    public function verify(Payment $payment, mixed $token): bool
    {
        return $this->verifiedClaims($payment, $token) !== null;
    }

    public function exchange(Request $request, Payment $payment, mixed $token): bool
    {
        $claims = $this->verifiedClaims($payment, $token);
        if ($claims === null) {
            return false;
        }

        $request->session()->migrate(true);
        $attempts = $request->session()->get(self::SESSION_KEY, []);
        if (! is_array($attempts)) {
            $attempts = [];
        }
        $attempts[(string) $payment->getKey()] = [
            'attempt_hash' => hash('sha256', $this->attemptReference($payment)),
            'expires_at' => $claims['exp'],
        ];
        $request->session()->put(self::SESSION_KEY, $attempts);

        return true;
    }

    public function allows(Request $request, Payment $payment): bool
    {
        $attempts = $request->session()->get(self::SESSION_KEY, []);
        $grant = is_array($attempts) ? ($attempts[(string) $payment->getKey()] ?? null) : null;

        if (! is_array($grant)
            || ! is_int($grant['expires_at'] ?? null)
            || $grant['expires_at'] <= now()->getTimestamp()
            || ! is_string($grant['attempt_hash'] ?? null)
            || ! hash_equals(hash('sha256', $this->attemptReference($payment)), $grant['attempt_hash'])) {
            if (is_array($attempts)) {
                unset($attempts[(string) $payment->getKey()]);
                $request->session()->put(self::SESSION_KEY, $attempts);
            }

            return false;
        }

        return true;
    }

    private function verifiedClaims(Payment $payment, mixed $token): ?array
    {
        if (! is_string($token)) {
            return null;
        }

        if (str_starts_with($token, self::CURRENT_VERSION.'.')) {
            return $this->verifiedCompactClaims($payment, $token);
        }

        return $this->verifiedLegacyClaims($payment, $token);
    }

    private function verifiedCompactClaims(Payment $payment, string $token): ?array
    {
        $parts = explode('.', $token);
        if (count($parts) !== 5
            || $parts[0] !== self::CURRENT_VERSION
            || preg_match('/^[0-9a-z]+$/D', $parts[1]) !== 1
            || preg_match('/^[0-9a-z]+$/D', $parts[2]) !== 1
            || preg_match('/^[A-Za-z0-9_-]{16}$/D', $parts[3]) !== 1
            || preg_match('/^[A-Za-z0-9_-]{43}$/D', $parts[4]) !== 1) {
            return null;
        }

        [$version, $encodedPaymentId, $encodedExpiry, $nonce, $signature] = $parts;
        $payload = implode('.', [$version, $encodedPaymentId, $encodedExpiry, $nonce]);
        if (! hash_equals($this->compactSignature($payload, $payment), $signature)) {
            return null;
        }

        $paymentId = base_convert($encodedPaymentId, 36, 10);
        $expiresAt = base_convert($encodedExpiry, 36, 10);
        if (! ctype_digit($paymentId) || ! ctype_digit($expiresAt)) {
            return null;
        }

        $paymentId = (int) $paymentId;
        $expiresAt = (int) $expiresAt;
        $now = now()->getTimestamp();
        if ($paymentId !== $payment->getKey()
            || $expiresAt <= $now
            || $expiresAt > $now + ($this->ttlMinutes() * 60) + 60) {
            return null;
        }

        return ['exp' => $expiresAt];
    }

    private function verifiedLegacyClaims(Payment $payment, string $token): ?array
    {
        $parts = explode('.', $token);
        if (count($parts) !== 3 || $parts[0] !== self::LEGACY_VERSION) {
            return null;
        }
        [, $payload, $signature] = $parts;
        if (! hash_equals($this->legacySignature($payload), $signature)) {
            return null;
        }

        $decoded = $this->base64UrlDecode($payload);
        if ($decoded === false) {
            return null;
        }

        try {
            $claims = json_decode($decoded, true, 16, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return null;
        }

        $now = now()->getTimestamp();
        if (! is_array($claims)
            || ($claims['v'] ?? null) !== 1
            || ($claims['aud'] ?? null) !== self::AUDIENCE
            || ($claims['pid'] ?? null) !== $payment->getKey()
            || ($claims['attempt'] ?? null) !== $this->attemptReference($payment)
            || ! is_int($claims['iat'] ?? null)
            || ! is_int($claims['exp'] ?? null)
            || ! is_string($claims['nonce'] ?? null)
            || $claims['iat'] > $now + 60
            || $claims['exp'] <= $now
            || $claims['exp'] <= $claims['iat']
            || $this->ttlMinutes() * 60 < $claims['exp'] - $claims['iat']) {
            return null;
        }

        return $claims;
    }

    private function compactSignature(string $payload, Payment $payment): string
    {
        return $this->base64UrlEncode(hash_hmac(
            'sha256',
            $payload.'|attempt:'.$this->attemptReference($payment),
            $this->derivedKey(self::CURRENT_VERSION),
            true,
        ));
    }

    private function legacySignature(string $payload): string
    {
        return $this->base64UrlEncode(hash_hmac(
            'sha256',
            self::LEGACY_VERSION.'.'.$payload,
            $this->derivedKey(self::LEGACY_VERSION),
            true,
        ));
    }

    private function attemptReference(Payment $payment): string
    {
        $reference = $payment->provider === 'vnpay' ? $payment->order_code : $payment->app_trans_id;
        if (! is_string($reference) || $reference === '') {
            throw new PaymentConfigurationException('Payment attempt reference is missing.');
        }

        return $reference;
    }

    private function derivedKey(string $version): string
    {
        $configuredKey = config('app.key');
        if (! is_string($configuredKey) || $configuredKey === '') {
            throw new PaymentConfigurationException('APP_KEY is required for payment return state.');
        }

        $key = $configuredKey;
        if (str_starts_with($key, 'base64:')) {
            $key = base64_decode(substr($key, 7), true);
            if ($key === false) {
                throw new PaymentConfigurationException('APP_KEY contains invalid base64 data.');
            }
        }
        if (strlen($key) < 32) {
            throw new PaymentConfigurationException('APP_KEY must provide at least 256 bits of key material.');
        }

        return hash_hmac('sha256', 'moviemate/payment-return-state/'.$version, $key, true);
    }

    private function ttlMinutes(): int
    {
        return max(1, (int) config('payment.return_state_ttl_minutes', 30));
    }

    private function base64UrlEncode(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }

    private function base64UrlDecode(string $value): string|false
    {
        if (preg_match('/^[A-Za-z0-9_-]+$/D', $value) !== 1) {
            return false;
        }
        $padding = (4 - strlen($value) % 4) % 4;

        return base64_decode(strtr($value, '-_', '+/').str_repeat('=', $padding), true);
    }
}
