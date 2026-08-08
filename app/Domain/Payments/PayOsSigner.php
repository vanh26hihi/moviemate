<?php

namespace App\Domain\Payments;

use JsonException;

final class PayOsSigner
{
    /** @param array{amount:int,cancelUrl:string,description:string,orderCode:int,returnUrl:string} $fields */
    public function createPaymentRequestSignature(array $fields, string $checksumKey): string
    {
        return hash_hmac('sha256', $this->paymentRequestCanonical($fields), $checksumKey);
    }

    /** @param array{amount:int,cancelUrl:string,description:string,orderCode:int,returnUrl:string} $fields */
    public function paymentRequestCanonical(array $fields): string
    {
        return implode('&', [
            'amount='.$fields['amount'],
            'cancelUrl='.$fields['cancelUrl'],
            'description='.$fields['description'],
            'orderCode='.$fields['orderCode'],
            'returnUrl='.$fields['returnUrl'],
        ]);
    }

    /** @param array<string, mixed> $data */
    public function signData(array $data, string $checksumKey): string
    {
        return hash_hmac('sha256', $this->dataCanonical($data), $checksumKey);
    }

    /** @param array<string, mixed> $data */
    public function verifyData(array $data, mixed $signature, string $checksumKey): bool
    {
        if (! is_string($signature) || preg_match('/^[a-fA-F0-9]{64}$/D', $signature) !== 1) {
            return false;
        }

        return hash_equals($this->signData($data, $checksumKey), strtolower($signature));
    }

    /** @param array<string, mixed> $data */
    public function dataCanonical(array $data): string
    {
        ksort($data, SORT_STRING);
        $parts = [];
        foreach ($data as $key => $value) {
            $parts[] = $key.'='.$this->stringValue($value);
        }

        return implode('&', $parts);
    }

    private function stringValue(mixed $value): string
    {
        if ($value === null || $value === 'null' || $value === 'undefined') {
            return '';
        }
        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }
        if (is_int($value) || is_float($value) || is_string($value)) {
            return (string) $value;
        }
        if (is_array($value)) {
            $normalized = array_map(function (mixed $item): mixed {
                if (is_array($item)) {
                    ksort($item, SORT_STRING);
                }

                return $item;
            }, $value);
            try {
                return json_encode($normalized, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
            } catch (JsonException) {
                throw new \InvalidArgumentException('payOS signature data cannot be encoded.');
            }
        }

        throw new \InvalidArgumentException('payOS signature data contains an unsupported value.');
    }
}
