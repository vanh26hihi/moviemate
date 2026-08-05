<?php

namespace App\Services;

use App\Domain\Bookings\BookingPriceBreakdown;
use App\Domain\Money\VndAmount;
use App\Models\Showtime;
use Illuminate\Support\Collection;
use InvalidArgumentException;

class BookingPricingService
{
    public function calculate(Showtime $showtime, Collection $seats): BookingPriceBreakdown
    {
        $basePrice = VndAmount::fromDatabase($showtime->getRawOriginal('price') ?? $showtime->price);
        $vipPrice = $showtime->vip_price === null
            ? $basePrice
            : VndAmount::fromDatabase($showtime->getRawOriginal('vip_price') ?? $showtime->vip_price);

        $snapshots = [];
        $subtotal = VndAmount::zero();

        foreach ($seats->reject(fn ($seat) => strtolower((string) $seat->type) === 'couple') as $seat) {
            $seatId = $this->seatId($seat);
            $price = strtolower((string) $seat->type) === 'vip' ? $vipPrice : $basePrice;
            $this->assertUniqueSeat($snapshots, $seatId);
            $snapshots[$seatId] = $price->value();
            $subtotal = $subtotal->add($price);
        }

        foreach ($seats->filter(fn ($seat) => strtolower((string) $seat->type) === 'couple')->groupBy('pair_code') as $pairCode => $pair) {
            if ($pairCode === '' || $pairCode === null || $pair->count() !== 2) {
                throw new InvalidArgumentException('A couple selection must contain one complete pair.');
            }

            $positions = $pair->keyBy(fn ($seat) => strtolower((string) $seat->pair_position));
            if ($positions->count() !== 2 || ! $positions->has('left') || ! $positions->has('right')) {
                throw new InvalidArgumentException('A couple pair must contain one left and one right seat.');
            }

            $multiplier = config('booking.couple_price_multiplier', 2);
            if (! is_int($multiplier) || $multiplier < 1) {
                throw new InvalidArgumentException('Couple price multiplier must be a positive integer.');
            }

            $pairTotal = $basePrice->multiply($multiplier);
            $leftSnapshot = intdiv($pairTotal->value(), 2);
            $rightSnapshot = $pairTotal->value() - $leftSnapshot;
            $leftId = $this->seatId($positions->get('left'));
            $rightId = $this->seatId($positions->get('right'));
            $this->assertUniqueSeat($snapshots, $leftId);
            $this->assertUniqueSeat($snapshots, $rightId);
            $snapshots[$leftId] = $leftSnapshot;
            $snapshots[$rightId] = $rightSnapshot;
            $subtotal = $subtotal->add($pairTotal);
        }

        ksort($snapshots);

        return BookingPriceBreakdown::forSeats($subtotal->value(), $snapshots);
    }

    private function seatId(object $seat): int
    {
        $seatId = (int) $seat->getKey();
        if ($seatId < 1) {
            throw new InvalidArgumentException('Every priced seat must have a persisted ID.');
        }

        return $seatId;
    }

    /** @param array<int, int> $snapshots */
    private function assertUniqueSeat(array $snapshots, int $seatId): void
    {
        if (array_key_exists($seatId, $snapshots)) {
            throw new InvalidArgumentException('A seat cannot be priced more than once.');
        }
    }
}
