<?php

namespace App\Services;

use App\Domain\Bookings\FoodLine;
use App\Domain\Bookings\FoodPriceBreakdown;
use App\Domain\Money\VndAmount;
use App\Models\FoodItem;
use App\Models\Order;
use Illuminate\Support\Collection;
use InvalidArgumentException;

class BookingFoodService
{
    public function __construct(private readonly CinemaContext $cinemaContext) {}

    public function calculate(array|Collection|null $payload): FoodPriceBreakdown
    {
        $quantities = $this->normalize($payload instanceof Collection ? $payload->all() : ($payload ?? []));
        if ($quantities === []) {
            return FoodPriceBreakdown::empty();
        }

        $foods = FoodItem::query()->whereIn('id', array_keys($quantities))->get()->keyBy('id');
        $lines = [];
        $subtotal = VndAmount::zero();

        foreach ($quantities as $foodId => $quantity) {
            /** @var FoodItem|null $food */
            $food = $foods->get($foodId);
            if (! $food) {
                throw new InvalidArgumentException("Food item {$foodId} does not exist.");
            }
            if (! $food->active) {
                throw new InvalidArgumentException("Food item {$foodId} is not available.");
            }

            $unitPrice = VndAmount::fromDatabase($food->getRawOriginal('price') ?? $food->price);
            $lineTotal = $unitPrice->multiply($quantity);
            $lines[] = new FoodLine(
                (int) $food->getKey(),
                (string) $food->name,
                $unitPrice->value(),
                $quantity,
                $lineTotal->value(),
            );
            $subtotal = $subtotal->add($lineTotal);
        }

        return new FoodPriceBreakdown(
            $subtotal->value(),
            $lines,
            $this->cinemaContext->id(),
        );
    }

    public function persist(FoodPriceBreakdown $food, array $attributes = []): ?Order
    {
        if ($food->isEmpty()) {
            return null;
        }
        if ($food->pickupCinemaId === null) {
            throw new InvalidArgumentException('A food order requires the canonical pickup cinema.');
        }

        $order = Order::query()->create([
            'booking_id' => $attributes['booking_id'] ?? null,
            'user_id' => $attributes['user_id'] ?? null,
            'customer_name' => $attributes['customer_name'] ?? '',
            'customer_phone' => $attributes['customer_phone'] ?? null,
            'customer_email' => $attributes['customer_email'] ?? null,
            'pickup_cinema_id' => $food->pickupCinemaId,
            'subtotal' => $food->foodSubtotal,
            'total_amount' => $food->foodSubtotal,
            'status' => 'pending',
        ]);

        foreach ($food->lines as $line) {
            $order->items()->create([
                'food_item_id' => $line->foodId,
                'quantity' => $line->quantity,
                'snapshot_name' => $line->snapshotName,
                'unit_price' => $line->unitPrice,
                'line_total' => $line->lineTotal,
                'price' => $line->unitPrice,
                'total' => $line->lineTotal,
            ]);
        }

        return $order->load('items');
    }

    /** @return array<int, int> */
    private function normalize(array $payload): array
    {
        if ($payload === []) {
            return [];
        }

        $normalized = [];
        if (array_is_list($payload)) {
            foreach ($payload as $line) {
                if (! is_array($line) || ! array_key_exists('food_id', $line) || ! array_key_exists('quantity', $line)) {
                    throw new InvalidArgumentException('Each food line requires food_id and quantity.');
                }
                $foodId = $this->positiveInteger($line['food_id'], 'food_id');
                if (array_key_exists($foodId, $normalized)) {
                    throw new InvalidArgumentException("Duplicate food item {$foodId}.");
                }
                $normalized[$foodId] = $this->quantity($line['quantity']);
            }
        } else {
            foreach ($payload as $foodId => $quantity) {
                $normalized[$this->positiveInteger($foodId, 'food_id')] = $this->quantity($quantity);
            }
        }

        return array_filter($normalized, fn (int $quantity) => $quantity !== 0);
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
        $maximum = (int) config('booking.max_food_quantity', 20);
        if ($quantity > $maximum) {
            throw new InvalidArgumentException("Food quantity cannot exceed {$maximum}.");
        }

        return $quantity;
    }
}
