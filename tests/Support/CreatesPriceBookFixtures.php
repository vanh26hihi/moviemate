<?php

namespace Tests\Support;

use App\Models\Cinema;
use App\Models\PriceBook;
use App\Models\PriceBookVersion;
use App\Models\Room;
use App\Models\RoomType;
use App\Models\Seat;
use App\Models\SeatType;
use App\Models\Showtime;
use App\Services\PriceBookVersionService;
use App\Services\ShowtimeScheduleService;
use App\Services\ShowtimeTicketPriceService;

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
            'is_pair' => $pair,
            'status' => true,
            'sort_order' => 10,
        ]);
    }

    protected function ensurePublishedPriceBook(int $base = 50_000): PriceBookVersion
    {
        $published = PriceBookVersion::query()->where('status', PriceBookVersion::STATUS_PUBLISHED)->first();
        if ($published) {
            return $published;
        }

        $this->seatType('normal');
        $vip = $this->seatType('vip');
        $couple = $this->seatType('couple', true);
        $vipAdjustment = $base === 80_000 ? 30_000 : 20_000;
        $coupleAdjustment = $base === 80_000 ? 80_000 : 50_000;
        $version = app(PriceBookVersionService::class)->createDraft($this->chainPriceBook(), [
            'base_price_vnd' => $base,
            'effective_from' => now()->subYears(2)->toDateString(),
            'effective_until' => now()->addYears(5)->toDateString(),
        ]);
        app(PriceBookVersionService::class)->replaceAdjustments($version, [
            ['dimension' => 'seat_type', 'label' => 'VIP', 'seat_type_id' => $vip->id, 'amount_vnd' => $vipAdjustment],
            ['dimension' => 'seat_type', 'label' => 'Couple', 'seat_type_id' => $couple->id, 'amount_vnd' => $coupleAdjustment],
        ]);
        app(PriceBookVersionService::class)->publish($version);

        return $version->refresh();
    }

    protected function assignLogicalSeatType(Seat $seat): void
    {
        $seatType = $this->seatType((string) $seat->type, $seat->type === 'couple');
        $seat->forceFill(['seat_type_id' => $seatType->id])->save();
    }

    protected function snapshotShowtime(Showtime $showtime): void
    {
        $this->ensurePublishedPriceBook();
        $showtime->loadMissing(['room.cinema', 'room.roomType', 'roomLayout.cells.seat.seatType', 'movie']);
        $schedule = app(ShowtimeScheduleService::class);
        $snapshots = app(ShowtimeTicketPriceService::class)->preview(
            $showtime->room,
            $showtime->roomLayout,
            $schedule->windowFor($showtime),
        );
        app(ShowtimeTicketPriceService::class)->persist($showtime, $snapshots);
        $showtime->load('ticketPrices.seatType');
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
