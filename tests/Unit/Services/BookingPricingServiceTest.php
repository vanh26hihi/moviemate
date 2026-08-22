<?php

namespace Tests\Unit\Services;

use App\Domain\Money\VndAmount;
use App\Models\Seat;
use App\Models\SeatType;
use App\Models\Showtime;
use App\Models\ShowtimeTicketPrice;
use App\Services\BookingPricingService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use OverflowException;
use Tests\TestCase;

class BookingPricingServiceTest extends TestCase
{
    public function test_vnd_amount_stores_an_integer_and_is_immutable(): void
    {
        $amount = VndAmount::fromInt(50_000);

        $this->assertSame(50_000, $amount->value());
        $this->assertSame('50.000 ₫', $amount->format());
    }

    public function test_vnd_amount_adds_and_compares(): void
    {
        $left = VndAmount::fromInt(10_000);
        $sum = $left->add(VndAmount::fromInt(20_000));

        $this->assertSame(30_000, $sum->value());
        $this->assertSame(10_000, $left->value());
        $this->assertSame(-1, $left->compareTo($sum));
        $this->assertTrue($sum->equals(VndAmount::fromInt(30_000)));
    }

    public function test_vnd_amount_multiplies_by_an_integer(): void
    {
        $this->assertSame(60_000, VndAmount::fromInt(20_000)->multiply(3)->value());
    }

    public function test_vnd_amount_safely_parses_whole_decimal_database_values(): void
    {
        $this->assertSame(120_000, VndAmount::fromDatabase('120000.00')->value());
        $this->assertSame(7, VndAmount::fromDatabase('0007.000')->value());
    }

    public function test_vnd_amount_rejects_fractional_values(): void
    {
        $this->expectException(InvalidArgumentException::class);

        VndAmount::fromDatabase('120000.50');
    }

    public function test_vnd_amount_rejects_float_input(): void
    {
        $this->expectException(InvalidArgumentException::class);

        VndAmount::fromDatabase(120000.0);
    }

    public function test_vnd_amount_rejects_negative_values(): void
    {
        $this->expectException(InvalidArgumentException::class);

        VndAmount::fromInt(-1);
    }

    public function test_vnd_amount_rejects_arithmetic_overflow(): void
    {
        $this->expectException(OverflowException::class);

        VndAmount::fromInt(PHP_INT_MAX)->add(VndAmount::fromInt(1));
    }

    public function test_prices_a_normal_seat(): void
    {
        $result = $this->service()->calculate($this->showtime(), collect([$this->seat(1, 'normal')]));

        $this->assertSame(50_000, $result->seatSubtotal);
        $this->assertSame([1 => 50_000], $result->seatSnapshots);
        $this->assertSame('VND', $result->currency);
    }

    public function test_prices_a_vip_seat_from_showtime_snapshot(): void
    {
        $result = $this->service()->calculate($this->showtime(), collect([$this->seat(2, 'vip')]));

        $this->assertSame(70_000, $result->seatSubtotal);
        $this->assertSame([2 => 70_000], $result->seatSnapshots);
    }

    public function test_prices_multiple_regular_seats_individually(): void
    {
        $result = $this->service()->calculate($this->showtime(), collect([
            $this->seat(1, 'normal'),
            $this->seat(2, 'vip'),
            $this->seat(3, 'normal'),
        ]));

        $this->assertSame(170_000, $result->seatSubtotal);
        $this->assertSame($result->seatSubtotal, array_sum($result->seatSnapshots));
    }

    public function test_prices_a_couple_pair_once_instead_of_per_seat(): void
    {
        $result = $this->service()->calculate($this->showtime(), $this->couple(10, 11, 'PAIR-1'));

        $this->assertSame(100_000, $result->seatSubtotal);
        $this->assertSame([10 => 50_000, 11 => 50_000], $result->seatSnapshots);
        $this->assertNotSame(200_000, $result->seatSubtotal, 'Couple pricing must never produce base × 4.');
    }

    public function test_rejects_half_of_a_couple_pair(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->service()->calculate($this->showtime(), collect([
            $this->seat(10, 'couple', 'PAIR-1', 'left'),
        ]));
    }

    public function test_prices_two_couple_pairs_independently(): void
    {
        $seats = $this->couple(10, 11, 'PAIR-1')->concat($this->couple(12, 13, 'PAIR-2'));
        $result = $this->service()->calculate($this->showtime(), $seats);

        $this->assertSame(200_000, $result->seatSubtotal);
        $this->assertCount(4, $result->seatSnapshots);
        $this->assertSame($result->seatSubtotal, array_sum($result->seatSnapshots));
    }

    public function test_odd_couple_total_is_split_floor_left_and_remainder_right(): void
    {
        config()->set('booking.couple_price_multiplier', 1);
        $result = $this->service()->calculate($this->showtime('100001.00'), $this->couple(10, 11, 'PAIR-1'));

        $this->assertSame(100_001, $result->seatSubtotal);
        $this->assertSame(50_000, $result->seatSnapshots[10]);
        $this->assertSame(50_001, $result->seatSnapshots[11]);
        $this->assertSame($result->seatSubtotal, array_sum($result->seatSnapshots));
    }

    public function test_frontend_price_attributes_are_ignored(): void
    {
        $seat = $this->seat(1, 'normal');
        $seat->setAttribute('price', 1);
        $seat->setAttribute('frontend_price', 1);

        $result = $this->service()->calculate($this->showtime(), collect([$seat]));

        $this->assertSame(50_000, $result->seatSubtotal);
    }

    public function test_pricing_an_already_loaded_collection_performs_no_queries(): void
    {
        DB::flushQueryLog();
        DB::enableQueryLog();

        $this->service()->calculate($this->showtime(), collect([
            $this->seat(1, 'normal'),
            $this->seat(2, 'vip'),
            ...$this->couple(10, 11, 'PAIR-1')->all(),
        ]));

        $this->assertCount(0, DB::getQueryLog());
    }

    private function service(): BookingPricingService
    {
        return new BookingPricingService;
    }

    private function showtime(string $coupleAmount = '100000'): Showtime
    {
        $showtime = new Showtime;
        $showtime->setRelation('ticketPrices', collect([
            $this->ticketPrice(1, 'normal', 50_000),
            $this->ticketPrice(2, 'vip', 70_000),
            $this->ticketPrice(3, 'couple', (int) $coupleAmount),
        ]));

        return $showtime;
    }

    private function seat(int $id, string $type, ?string $pairCode = null, ?string $position = null): Seat
    {
        $seat = new Seat([
            'type' => $type,
            'seat_type_id' => match ($type) {
                'normal' => 1, 'vip' => 2, 'couple' => 3
            },
            'pair_code' => $pairCode,
            'pair_position' => $position,
        ]);
        $seat->setAttribute('id', $id);

        return $seat;
    }

    private function ticketPrice(int $id, string $code, int $amount): ShowtimeTicketPrice
    {
        $seatType = new SeatType(['code' => $code, 'name' => ucfirst($code), 'status' => true]);
        $seatType->setAttribute('id', $id);
        $snapshot = new ShowtimeTicketPrice([
            'seat_type_id' => $id,
            'price_book_version_id' => 1,
            'base_price_vnd' => 50_000,
            'adjustment_total_vnd' => $amount - 50_000,
            'final_unit_amount_vnd' => $amount,
            'breakdown_json' => ['adjustments' => [], 'final_unit_amount_vnd' => $amount],
            'pricing_fingerprint' => str_repeat((string) $id, 64),
        ]);
        $snapshot->setAttribute('id', $id);
        $snapshot->setRelation('seatType', $seatType);

        return $snapshot;
    }

    private function couple(int $leftId, int $rightId, string $pairCode): Collection
    {
        return collect([
            $this->seat($leftId, 'couple', $pairCode, 'left'),
            $this->seat($rightId, 'couple', $pairCode, 'right'),
        ]);
    }
}
