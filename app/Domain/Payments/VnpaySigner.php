<?php

namespace App\Domain\Payments;

use InvalidArgumentException;

final class VnpaySigner
{
    private const QUERY_REQUEST_ORDER = [
        'vnp_RequestId',
        'vnp_Version',
        'vnp_Command',
        'vnp_TmnCode',
        'vnp_TxnRef',
        'vnp_TransactionDate',
        'vnp_CreateDate',
        'vnp_IpAddr',
        'vnp_OrderInfo',
    ];

    private const QUERY_RESPONSE_ORDER = [
        'vnp_ResponseId',
        'vnp_Command',
        'vnp_ResponseCode',
        'vnp_Message',
        'vnp_TmnCode',
        'vnp_TxnRef',
        'vnp_Amount',
        'vnp_BankCode',
        'vnp_PayDate',
        'vnp_TransactionNo',
        'vnp_TransactionType',
        'vnp_TransactionStatus',
        'vnp_OrderInfo',
        'vnp_PromotionCode',
        'vnp_PromotionAmount',
    ];

    /** @param array<string, mixed> $parameters */
    public function paymentCanonical(array $parameters): string
    {
        $filtered = [];
        foreach ($parameters as $key => $value) {
            if (! is_string($key)
                || ! str_starts_with($key, 'vnp_')
                || in_array($key, ['vnp_SecureHash', 'vnp_SecureHashType'], true)
                || $value === null
                || $value === '') {
                continue;
            }
            $filtered[$this->scalar($key)] = $this->scalar($value);
        }
        ksort($filtered, SORT_STRING);

        return collect($filtered)
            ->map(fn (string $value, string $key): string => urlencode($key).'='.urlencode($value))
            ->implode('&');
    }

    /** @param array<string, mixed> $parameters */
    public function signPayment(array $parameters, string $secret): string
    {
        return hash_hmac('sha512', $this->paymentCanonical($parameters), $secret);
    }

    /** @param array<string, mixed> $parameters */
    public function verifyPayment(array $parameters, string $provided, string $secret): bool
    {
        return $this->constantTimeHexEquals($this->signPayment($parameters, $secret), $provided);
    }

    /** @param array<string, mixed> $fields */
    public function queryRequestCanonical(array $fields): string
    {
        return $this->orderedCanonical($fields, self::QUERY_REQUEST_ORDER);
    }

    /** @param array<string, mixed> $fields */
    public function signQueryRequest(array $fields, string $secret): string
    {
        return hash_hmac('sha512', $this->queryRequestCanonical($fields), $secret);
    }

    /** @param array<string, mixed> $fields */
    public function queryResponseCanonical(array $fields): string
    {
        return $this->orderedCanonical($fields, self::QUERY_RESPONSE_ORDER);
    }

    /** @param array<string, mixed> $fields */
    public function verifyQueryResponse(array $fields, string $provided, string $secret): bool
    {
        $expected = hash_hmac('sha512', $this->queryResponseCanonical($fields), $secret);

        return $this->constantTimeHexEquals($expected, $provided);
    }

    /** @return array<string, string> */
    public function parseQueryString(string $query): array
    {
        if (! mb_check_encoding($query, 'UTF-8')) {
            throw new InvalidArgumentException('VNPAY query string is not valid UTF-8.');
        }

        $parameters = [];
        foreach ($query === '' ? [] : explode('&', $query) as $pair) {
            [$rawKey, $rawValue] = array_pad(explode('=', $pair, 2), 2, '');
            $key = urldecode($rawKey);
            $value = urldecode($rawValue);
            if ($key === '' || ! mb_check_encoding($key, 'UTF-8') || ! mb_check_encoding($value, 'UTF-8')) {
                throw new InvalidArgumentException('VNPAY query parameter encoding is invalid.');
            }
            if (str_contains($key, '[') || str_contains($key, ']') || array_key_exists($key, $parameters)) {
                throw new InvalidArgumentException('Duplicate or array VNPAY query parameters are not accepted.');
            }
            $parameters[$key] = $value;
        }

        return $parameters;
    }

    public function constantTimeHexEquals(string $expected, string $provided): bool
    {
        return preg_match('/^[a-f0-9]{128}$/Di', $provided) === 1
            && hash_equals(strtolower($expected), strtolower($provided));
    }

    /** @param array<string, mixed> $fields @param list<string> $order */
    private function orderedCanonical(array $fields, array $order): string
    {
        return implode('|', array_map(
            fn (string $key): string => $this->scalar($fields[$key] ?? ''),
            $order,
        ));
    }

    private function scalar(mixed $value): string
    {
        if (! is_string($value) && ! is_int($value)) {
            throw new InvalidArgumentException('VNPAY parameters must be scalar strings or integers.');
        }

        $value = (string) $value;
        if (! mb_check_encoding($value, 'UTF-8')) {
            throw new InvalidArgumentException('VNPAY parameter encoding is invalid.');
        }

        return $value;
    }
}
