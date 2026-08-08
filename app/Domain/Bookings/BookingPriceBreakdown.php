<?php

namespace App\Domain\Bookings;

use App\Domain\Money\VndAmount;

final readonly class BookingPriceBreakdown
{
    /**
     * @param  array<int, int>  $seatSnapshots
     * @param  array<int, array<string, mixed>>  $seatPricingSnapshots
     * @param  list<FoodLine>  $foodLines
     */
    public function __construct(
        public int $seatSubtotal,
        public int $foodSubtotal,
        public int $grandTotal,
        public array $seatSnapshots,
        public array $seatPricingSnapshots = [],
        public array $foodLines = [],
        public string $currency = 'VND',
        public ?int $pickupCinemaId = null,
    ) {}

    /** @param array<int, int> $seatSnapshots */
    public static function forSeats(int $seatSubtotal, array $seatSnapshots, array $seatPricingSnapshots = []): self
    {
        return new self($seatSubtotal, 0, $seatSubtotal, $seatSnapshots, $seatPricingSnapshots);
    }

    public function withFood(FoodPriceBreakdown $food): self
    {
        $grandTotal = VndAmount::fromInt($this->seatSubtotal)
            ->add(VndAmount::fromInt($food->foodSubtotal))
            ->value();

        return new self(
            $this->seatSubtotal,
            $food->foodSubtotal,
            $grandTotal,
            $this->seatSnapshots,
            $this->seatPricingSnapshots,
            $food->lines,
            'VND',
            $food->pickupCinemaId,
        );
    }

    public function toArray(): array
    {
        return [
            'seat_subtotal' => $this->seatSubtotal,
            'food_subtotal' => $this->foodSubtotal,
            'grand_total' => $this->grandTotal,
            'currency' => $this->currency,
            'seat_snapshots' => $this->seatSnapshots,
            'seat_pricing_snapshots' => $this->seatPricingSnapshots,
            'food_lines' => array_map(fn (FoodLine $line) => $line->toArray(), $this->foodLines),
            'pickup_cinema_id' => $this->pickupCinemaId,
        ];
    }
}
