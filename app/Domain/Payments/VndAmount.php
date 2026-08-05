<?php

namespace App\Domain\Payments;

use InvalidArgumentException;

final class VndAmount
{
    public static function fromDecimal(mixed $value): int
    {
        if (! is_int($value) && ! is_string($value)) {
            throw new InvalidArgumentException('Payment amount must not be converted through floating point.');
        }

        $raw = is_int($value) ? (string) $value : trim($value);

        if (! preg_match('/^([0-9]+)(?:\.([0-9]+))?$/D', $raw, $matches)) {
            throw new InvalidArgumentException('Payment amount must be an exact decimal VND value.');
        }

        if (isset($matches[2]) && trim($matches[2], '0') !== '') {
            throw new InvalidArgumentException('Fractional VND is not supported.');
        }

        $integer = ltrim($matches[1], '0');
        $integer = $integer === '' ? '0' : $integer;
        $maximum = (string) PHP_INT_MAX;

        if (strlen($integer) > strlen($maximum)
            || (strlen($integer) === strlen($maximum) && strcmp($integer, $maximum) > 0)) {
            throw new InvalidArgumentException('Payment amount exceeds the supported integer range.');
        }

        $amount = (int) $integer;

        if ($amount <= 0) {
            throw new InvalidArgumentException('Payment amount must be greater than zero.');
        }

        return $amount;
    }
}
