<?php

namespace App\Services;

use App\Domain\Bookings\BookingPriceBreakdown;
use App\Domain\Money\VndAmount;
use App\Exceptions\PricingConfigurationException;
use App\Models\Showtime;
use App\Models\ShowtimeTicketPrice;
use Illuminate\Support\Collection;
use InvalidArgumentException;

class BookingPricingService
{
    public function calculate(Showtime $showtime, Collection $seats): BookingPriceBreakdown
    {
        $showtime->loadMissing('ticketPrices.seatType');
        $prices = $showtime->ticketPrices->keyBy('seat_type_id');
        $snapshots = [];
        $pricingSnapshots = [];
        $subtotal = VndAmount::zero();

        foreach ($seats->reject(fn ($seat) => strtolower((string) $seat->type) === 'couple') as $seat) {
            $seatId = $this->seatId($seat);
            $calculation = $this->priceFor($prices, $seat);
            $price = VndAmount::fromInt($calculation->finalAmount);
            $this->assertUniqueSeat($snapshots, $seatId);
            $snapshots[$seatId] = $price->value();
            $pricingSnapshots[$seatId] = $this->snapshot($calculation, 'seat:'.$seatId, (string) $seat->seat_code);
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

            $calculation = $this->priceFor($prices, $pair->first());
            $pairTotal = VndAmount::fromInt($calculation->finalAmount);
            $leftSnapshot = intdiv($pairTotal->value(), 2);
            $rightSnapshot = $pairTotal->value() - $leftSnapshot;
            $leftId = $this->seatId($positions->get('left'));
            $rightId = $this->seatId($positions->get('right'));
            $this->assertUniqueSeat($snapshots, $leftId);
            $this->assertUniqueSeat($snapshots, $rightId);
            $snapshots[$leftId] = $leftSnapshot;
            $snapshots[$rightId] = $rightSnapshot;
            $unitKey = 'couple:'.$pairCode;
            $unitLabel = 'Ghế đôi '.$pair->pluck('seat_code')->sort()->implode('/');
            $pricingSnapshots[$leftId] = $this->snapshot($calculation, $unitKey, $unitLabel);
            $pricingSnapshots[$rightId] = $this->snapshot($calculation, $unitKey, $unitLabel);
            $subtotal = $subtotal->add($pairTotal);
        }

        ksort($snapshots);
        ksort($pricingSnapshots);

        return BookingPriceBreakdown::forSeats($subtotal->value(), $snapshots, $pricingSnapshots);
    }

    private function snapshot(ShowtimeTicketPrice $calculation, string $unitKey, string $unitLabel): array
    {
        return [
            'showtime_ticket_price_id' => (int) $calculation->id,
            'pricing_unit_key' => $unitKey,
            'pricing_unit_label' => $unitLabel,
            'seat_type_snapshot' => $calculation->seat_type_code,
            'base_amount' => $calculation->baseAmount,
            'surcharge_total' => $calculation->surchargeTotal,
            'final_unit_amount' => $calculation->finalAmount,
            'pricing_breakdown' => $calculation->breakdown(),
            'pricing_fingerprint' => $calculation->pricing_fingerprint,
        ];
    }

    private function priceFor(Collection $prices, object $seat): ShowtimeTicketPrice
    {
        $seatTypeId = (int) $seat->seat_type_id;
        $price = $prices->get($seatTypeId);
        if ($seatTypeId < 1 || ! $price instanceof ShowtimeTicketPrice) {
            throw new PricingConfigurationException('The Showtime has no immutable price for the selected logical SeatType.');
        }

        return $price;
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
