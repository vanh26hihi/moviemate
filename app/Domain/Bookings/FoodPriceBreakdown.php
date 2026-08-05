<?php

namespace App\Domain\Bookings;

final readonly class FoodPriceBreakdown
{
    /** @param list<FoodLine> $lines */
    public function __construct(
        public int $foodSubtotal,
        public array $lines,
        public ?int $pickupCinemaId,
        public string $currency = 'VND',
    ) {}

    public static function empty(): self
    {
        return new self(0, [], null);
    }

    public function isEmpty(): bool
    {
        return $this->lines === [];
    }

    public function toArray(): array
    {
        return [
            'food_subtotal' => $this->foodSubtotal,
            'currency' => $this->currency,
            'pickup_cinema_id' => $this->pickupCinemaId,
            'lines' => array_map(fn (FoodLine $line) => $line->toArray(), $this->lines),
        ];
    }
}
