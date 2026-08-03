<?php

namespace App\Domain\Bookings;

final readonly class FoodLine
{
    public function __construct(
        public int $foodId,
        public string $snapshotName,
        public int $unitPrice,
        public int $quantity,
        public int $lineTotal,
    ) {}

    public function toArray(): array
    {
        return [
            'food_id' => $this->foodId,
            'snapshot_name' => $this->snapshotName,
            'unit_price' => $this->unitPrice,
            'quantity' => $this->quantity,
            'line_total' => $this->lineTotal,
        ];
    }
}
