<?php

namespace App\Domain\Payments;

use App\Exceptions\PaymentInitiationException;
use App\Models\Payment;
use Carbon\CarbonImmutable;

final class VnpayPayRequestValidator
{
    private const REQUIRED_KEYS = [
        'vnp_Amount',
        'vnp_Command',
        'vnp_CreateDate',
        'vnp_CurrCode',
        'vnp_ExpireDate',
        'vnp_IpAddr',
        'vnp_Locale',
        'vnp_OrderInfo',
        'vnp_OrderType',
        'vnp_ReturnUrl',
        'vnp_TmnCode',
        'vnp_TxnRef',
        'vnp_Version',
    ];

    private const OPTIONAL_KEYS = ['vnp_BankCode'];

    /** @param array<string, mixed> $parameters @return list<string> */
    public function violations(array $parameters, Payment $payment, bool $mustBeCurrentlyPayable = true): array
    {
        $violations = [];
        $keys = array_keys($parameters);
        foreach (self::REQUIRED_KEYS as $key) {
            if (! array_key_exists($key, $parameters)) {
                $violations[] = 'missing_'.$key;
            }
        }
        foreach ($keys as $key) {
            if (! in_array($key, [...self::REQUIRED_KEYS, ...self::OPTIONAL_KEYS], true)) {
                $violations[] = 'unsupported_parameter';
            }
        }
        if (array_key_exists('vnp_SecureHashType', $parameters)) {
            $violations[] = 'secure_hash_type_must_be_omitted';
        }

        $values = [];
        foreach ($parameters as $key => $value) {
            if (! is_string($value) && ! is_int($value)) {
                $violations[] = 'non_scalar_parameter';

                continue;
            }
            $values[$key] = (string) $value;
        }

        $this->expect($violations, ($values['vnp_Version'] ?? null) === '2.1.0', 'invalid_version');
        $this->expect($violations, ($values['vnp_Command'] ?? null) === 'pay', 'invalid_command');
        $this->expect($violations, preg_match('/^[A-Za-z0-9]{8}$/D', $values['vnp_TmnCode'] ?? '') === 1, 'invalid_tmn_code');
        $this->expect($violations, ($values['vnp_CurrCode'] ?? null) === 'VND', 'invalid_currency');
        $this->expect($violations, in_array($values['vnp_Locale'] ?? null, ['vn', 'en'], true), 'invalid_locale');
        $this->expect($violations, preg_match('/^[A-Za-z0-9]{1,100}$/D', $values['vnp_TxnRef'] ?? '') === 1, 'invalid_txn_ref');
        $this->expect($violations, preg_match('/^[A-Za-z0-9]{1,100}$/D', $values['vnp_OrderType'] ?? '') === 1, 'invalid_order_type');
        $this->expect(
            $violations,
            preg_match('/^[A-Za-z0-9 ._-]{1,255}$/D', $values['vnp_OrderInfo'] ?? '') === 1,
            'invalid_order_info',
        );

        $amount = $values['vnp_Amount'] ?? '';
        $this->expect($violations, preg_match('/^[0-9]{1,12}$/D', $amount) === 1, 'invalid_amount');
        if (preg_match('/^[0-9]{1,12}$/D', $amount) === 1
            && $payment->amount > 0
            && $payment->amount <= intdiv(PHP_INT_MAX, 100)) {
            $this->expect($violations, hash_equals((string) ($payment->amount * 100), $amount), 'amount_mismatch');
        }
        $this->expect($violations, ($values['vnp_TxnRef'] ?? null) === $payment->order_code, 'txn_ref_mismatch');

        $ip = $values['vnp_IpAddr'] ?? '';
        $this->expect(
            $violations,
            strlen($ip) >= 7 && strlen($ip) <= 45 && ! str_contains($ip, ',')
                && filter_var($ip, FILTER_VALIDATE_IP) !== false,
            'invalid_client_ip',
        );

        if (array_key_exists('vnp_BankCode', $values)) {
            $this->expect(
                $violations,
                preg_match('/^[A-Za-z0-9]{3,20}$/D', $values['vnp_BankCode']) === 1,
                'invalid_bank_code',
            );
        }

        $createdAt = $this->date($values['vnp_CreateDate'] ?? '');
        $expiresAt = $this->date($values['vnp_ExpireDate'] ?? '');
        $this->expect($violations, $createdAt !== null, 'invalid_create_date');
        $this->expect($violations, $expiresAt !== null, 'invalid_expire_date');
        if ($createdAt && $expiresAt) {
            $this->expect($violations, $expiresAt->greaterThan($createdAt), 'expire_date_not_after_create_date');
            if ($mustBeCurrentlyPayable) {
                $this->expect($violations, $expiresAt->isFuture(), 'request_already_expired');
            }
            if ($payment->provider_transaction_created_at) {
                $this->expect(
                    $violations,
                    $createdAt->timestamp === $payment->provider_transaction_created_at->timestamp,
                    'create_date_mismatch',
                );
            }
            if ($payment->expires_at) {
                $this->expect($violations, $expiresAt->timestamp === $payment->expires_at->timestamp, 'expire_date_mismatch');
            }
            if ($payment->booking?->expires_at) {
                $this->expect(
                    $violations,
                    $expiresAt->timestamp <= $payment->booking->expires_at->timestamp,
                    'expire_date_exceeds_booking',
                );
            }
        }

        $this->validateReturnUrl($violations, $values['vnp_ReturnUrl'] ?? '');

        return array_values(array_unique($violations));
    }

    /** @param array<string, mixed> $parameters */
    public function assertValid(array $parameters, Payment $payment): void
    {
        $violations = $this->violations($parameters, $payment);
        if ($violations !== []) {
            throw new PaymentInitiationException('VNPAY request contract failed: '.implode(',', $violations));
        }
    }

    /** @param list<string> $violations */
    private function validateReturnUrl(array &$violations, string $url): void
    {
        $parts = parse_url($url);
        $valid = is_array($parts)
            && strlen($url) >= 10
            && strlen($url) <= 255
            && strtolower((string) ($parts['scheme'] ?? '')) === 'https'
            && is_string($parts['host'] ?? null)
            && ! isset($parts['user'], $parts['pass'], $parts['fragment'])
            && ($parts['path'] ?? '') === parse_url(route('payments.vnpay.return'), PHP_URL_PATH);
        $this->expect($violations, $valid, 'invalid_return_url');
        if (! $valid || ! is_array($parts)) {
            return;
        }

        $host = strtolower(rtrim((string) $parts['host'], '.'));
        $allowedHosts = array_values(array_filter(array_map(
            static fn (mixed $value): ?string => is_string($value)
                ? strtolower(rtrim(trim($value), '.'))
                : null,
            (array) config('payment.public_hosts', []),
        )));
        $this->expect(
            $violations,
            ! $this->isLocalHost($host)
                && (app()->environment('testing') || ! str_ends_with($host, '.test'))
                && in_array($host, $allowedHosts, true),
            'unapproved_return_host',
        );

        parse_str((string) ($parts['query'] ?? ''), $query);
        $this->expect(
            $violations,
            array_keys($query) === ['state'] && is_string($query['state']) && $query['state'] !== '',
            'invalid_return_state',
        );
    }

    private function isLocalHost(string $host): bool
    {
        $ip = trim($host, '[]');

        return $host === 'localhost'
            || str_ends_with($host, '.localhost')
            || (filter_var($ip, FILTER_VALIDATE_IP) !== false
                && ($ip === '::1' || str_starts_with($ip, '127.')));
    }

    private function date(string $value): ?CarbonImmutable
    {
        if (preg_match('/^[0-9]{14}$/D', $value) !== 1) {
            return null;
        }
        try {
            $date = CarbonImmutable::createFromFormat('!YmdHis', $value, VnpayConfig::TIMEZONE);

            return $date->format('YmdHis') === $value ? $date : null;
        } catch (\Throwable) {
            return null;
        }
    }

    /** @param list<string> $violations */
    private function expect(array &$violations, bool $condition, string $violation): void
    {
        if (! $condition) {
            $violations[] = $violation;
        }
    }
}
