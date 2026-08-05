<?php

namespace App\Rules;

use App\Domain\Money\VndAmount;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use InvalidArgumentException;
use OverflowException;

class WholeVndAmount implements ValidationRule
{
    public function __construct(
        private readonly string $label,
        private readonly int $maximum,
    ) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        try {
            VndAmount::fromInput($value, $this->maximum);
        } catch (InvalidArgumentException|OverflowException) {
            $fail(sprintf(
                '%s phải là số nguyên VND không âm từ 0 đến %s.',
                $this->label,
                number_format($this->maximum, 0, ',', '.'),
            ));
        }
    }
}
