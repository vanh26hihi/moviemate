<?php

namespace App\Services;

use Illuminate\Support\Collection;
use InvalidArgumentException;

class FoodSelectionCanonicalizer
{
    /**
     * @return list<array{food_id: int, quantity: int}>
     */
    public function canonicalize(array|Collection|null $payload): array
    {
        $payload = $payload instanceof Collection ? $payload->all() : ($payload ?? []);
        if ($payload === []) {
            return [];
        }

        $quantities = [];
        if (array_is_list($payload)) {
            foreach ($payload as $line) {
                if (! is_array($line) || ! array_key_exists('food_id', $line) || ! array_key_exists('quantity', $line)) {
                    throw new InvalidArgumentException('Each food line requires food_id and quantity.');
                }

                $foodId = $this->positiveInteger($line['food_id'], 'food_id');
                if (array_key_exists($foodId, $quantities)) {
                    throw new InvalidArgumentException("Duplicate food item {$foodId}.");
                }

                $quantities[$foodId] = $this->quantity($line['quantity']);
            }
        } else {
            foreach ($payload as $foodId => $quantity) {
                $quantities[$this->positiveInteger($foodId, 'food_id')] = $this->quantity($quantity);
            }
        }

        $quantities = array_filter($quantities, fn (int $quantity): bool => $quantity !== 0);
        ksort($quantities, SORT_NUMERIC);

        $canonical = [];
        foreach ($quantities as $foodId => $quantity) {
            $canonical[] = ['food_id' => $foodId, 'quantity' => $quantity];
        }

        return $canonical;
    }

    private function positiveInteger(mixed $value, string $field): int
    {
        if (is_int($value)) {
            $integer = $value;
        } elseif (is_string($value) && preg_match('/^\d+$/', $value)) {
            $integer = (int) $value;
        } else {
            throw new InvalidArgumentException("{$field} must be an integer.");
        }

        if ($integer < 1) {
            throw new InvalidArgumentException("{$field} must be positive.");
        }

        return $integer;
    }

    private function quantity(mixed $value): int
    {
        if (is_int($value)) {
            $quantity = $value;
        } elseif (is_string($value) && preg_match('/^-?\d+$/', $value)) {
            $quantity = (int) $value;
        } else {
            throw new InvalidArgumentException('Food quantity must be an integer.');
        }

        if ($quantity < 0) {
            throw new InvalidArgumentException('Food quantity cannot be negative.');
        }

        $maximum = config('booking.max_food_quantity', 20);
        if (! is_int($maximum) || $maximum < 1) {
            throw new InvalidArgumentException('Maximum food quantity must be a positive integer.');
        }
        if ($quantity > $maximum) {
            throw new InvalidArgumentException("Food quantity cannot exceed {$maximum}.");
        }

        return $quantity;
    }
}
