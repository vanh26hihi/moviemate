<?php

namespace Tests\Support;

use App\Models\Cinema;
use App\Models\PriceBook;
use App\Models\PriceBookVersion;
use App\Models\Room;
use App\Models\RoomType;
use App\Models\SeatType;
use App\Services\PriceBookVersionService;

trait CreatesPriceBookFixtures
{
    protected function chainPriceBook(): PriceBook
    {
        return PriceBook::query()->firstOrCreate([
            'code' => PriceBook::CHAIN_CODE,
        ], [
            'name' => 'MovieMate Chain Price Book',
        ]);
    }

    protected function priceBookDraft(array $attributes = []): PriceBookVersion
    {
        return app(PriceBookVersionService::class)->createDraft($this->chainPriceBook(), [
            'base_price_vnd' => 80_000,
            'effective_from' => '2026-01-01',
            'effective_until' => '2027-01-01',
            ...$attributes,
        ]);
    }

    protected function seatType(string $code = 'normal', bool $pair = false): SeatType
    {
        return SeatType::query()->firstOrCreate(['code' => $code], [
            'name' => ucfirst($code),
            'slug' => $code,
            'price_modifier' => $code === 'vip' ? 999_999 : 0,
            'is_pair' => $pair,
            'status' => true,
            'sort_order' => 10,
        ]);
    }

    /** @return array{Cinema, RoomType, Room} */
    protected function pricingContext(string $suffix = 'A'): array
    {
        $cinema = Cinema::query()->create([
            'code' => 'PB'.$suffix,
            'name' => 'PriceBook Cinema '.$suffix,
            'address' => 'Test address',
            'city' => 'HCM',
            'timezone' => 'Asia/Ho_Chi_Minh',
            'status' => 'active',
        ]);
        $roomType = RoomType::query()->create([
            'code' => 'PB_TYPE_'.$suffix,
            'name' => 'PriceBook Type '.$suffix,
            'slug' => 'PB_TYPE_'.$suffix,
            'is_active' => true,
            'status' => true,
            'sort_order' => 10,
        ]);
        $room = Room::query()->create([
            'cinema_id' => $cinema->id,
            'code' => 'PB_ROOM_'.$suffix,
            'name' => 'PriceBook Room '.$suffix,
            'room_type' => $roomType->code,
            'room_type_id' => $roomType->id,
            'status' => 'active',
        ]);

        return [$cinema, $roomType, $room];
    }

    /** @return list<array<string, mixed>> */
    protected function standardAdjustments(): array
    {
        $vip = $this->seatType('vip');
        $couple = $this->seatType('couple', true);

        return [
            ['dimension' => 'seat_type', 'label' => 'VIP', 'seat_type_id' => $vip->id, 'amount_vnd' => 30_000],
            ['dimension' => 'seat_type', 'label' => 'Couple', 'seat_type_id' => $couple->id, 'amount_vnd' => 80_000],
            ['dimension' => 'time_window', 'label' => 'Evening', 'time_start' => '18:00', 'time_end' => '22:00', 'amount_vnd' => 15_000],
            ['dimension' => 'weekend', 'label' => 'Weekend', 'weekend_days' => [6, 7], 'amount_vnd' => 10_000],
            ['dimension' => 'holiday', 'label' => 'Holiday', 'holiday_date_from' => '2026-09-01', 'holiday_date_until' => '2026-09-02', 'amount_vnd' => 20_000],
        ];
    }
}
