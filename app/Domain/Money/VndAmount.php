<?php

namespace App\Domain\Money;

use InvalidArgumentException;
use OverflowException;

final readonly class VndAmount
{
    private function __construct(private int $amount) {}

    public static function zero(): self
    {
        return new self(0);
    }

    public static function fromInt(mixed $amount): self
    {
        if (! is_int($amount)) {
            throw new InvalidArgumentException('VND amount must be an integer.');
        }
        if ($amount < 0) {
            throw new InvalidArgumentException('VND amount cannot be negative.');
        }

        return new self($amount);
    }

    public static function fromDatabase(mixed $amount): self
    {
        if (is_int($amount)) {
            return self::fromInt($amount);
        }
        if (! is_string($amount)) {
            throw new InvalidArgumentException('VND database amount must be an integer or decimal string.');
        }

        $amount = trim($amount);
        if (! preg_match('/^\d+(?:\.0+)?$/', $amount)) {
            throw new InvalidArgumentException('VND amount must be a whole number.');
        }

        $integerPart = explode('.', $amount, 2)[0];
        $normalized = ltrim($integerPart, '0');
        $normalized = $normalized === '' ? '0' : $normalized;
        $maximum = (string) PHP_INT_MAX;

        if (strlen($normalized) > strlen($maximum)
            || (strlen($normalized) === strlen($maximum) && strcmp($normalized, $maximum) > 0)) {
            throw new OverflowException('VND amount exceeds the supported integer range.');
        }

        return new self((int) $normalized);
    }

    public function add(self $other): self
    {
        if ($this->amount > PHP_INT_MAX - $other->amount) {
            throw new OverflowException('VND addition exceeds the supported integer range.');
        }

        return new self($this->amount + $other->amount);
    }

    public function multiply(mixed $quantity): self
    {
        if (! is_int($quantity)) {
            throw new InvalidArgumentException('VND multiplier must be an integer.');
        }
        if ($quantity < 0) {
            throw new InvalidArgumentException('VND multiplier cannot be negative.');
        }

        if ($quantity !== 0 && $this->amount > intdiv(PHP_INT_MAX, $quantity)) {
            throw new OverflowException('VND multiplication exceeds the supported integer range.');
        }

        return new self($this->amount * $quantity);
    }

    public function compareTo(self $other): int
    {
        return $this->amount <=> $other->amount;
    }

    public function equals(self $other): bool
    {
        return $this->amount === $other->amount;
    }

    public function value(): int
    {
        return $this->amount;
    }

    public function format(): string
    {
        return number_format($this->amount, 0, ',', '.').' ₫';
    }

    public function __toString(): string
    {
        return (string) $this->amount;
    }
}
