<?php

namespace App\Services;

use App\Models\Showtime;

final class CustomerShowtimePriceReadService
{
    public function __construct(private readonly PublicShowtimeCatalog $catalog) {}

    /** @return array<string, mixed>|null */
    public function get(int $showtimeId): ?array
    {
        $showtime = Showtime::query()->find($showtimeId);
        if (! $showtime || ! $this->catalog->isCustomerSellable($showtime)) {
            return null;
        }

        $prices = collect($this->catalog->pricesFor($showtime))->values()
            ->sortBy(fn ($snapshot) => (int) $snapshot->seatType->sort_order)
            ->map(fn ($snapshot): array => [
                'seat_type_code' => $snapshot->seatType->code,
                'seat_type_name' => $snapshot->seatType->name,
                'logical_unit_seat_count' => $snapshot->seatType->is_pair ? 2 : 1,
                'amount_vnd' => (int) $snapshot->final_unit_amount_vnd,
            ])->values()->all();

        return [
            'showtime_id' => (int) $showtime->id,
            'prices' => $prices,
            'final_booking_total' => null,
            'message' => 'Đây là giá snapshot theo đơn vị ghế logic; tổng thanh toán cuối cùng do luồng đặt vé MovieMate xác định.',
        ];
    }
}
