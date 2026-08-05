<?php

namespace App\Services;

use App\Models\BookingSeat;
use App\Models\Seat;
use App\Models\Showtime;
use App\Support\SeatPresentation;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class BookingCheckoutPreviewService
{
    public function __construct(
        private readonly CinemaContext $cinemaContext,
        private readonly RoomLayoutService $layouts,
        private readonly BookingPricingService $pricing,
        private readonly BookingFoodService $food,
    ) {}

    public function preview(array $draft): BookingCheckoutPreview
    {
        $showtime = Showtime::query()
            ->with(['movie', 'cinema', 'room', 'roomLayout'])
            ->findOrFail((int) $draft['showtime_id']);
        $this->assertShowtimeAvailable($showtime);

        $seatIds = collect($draft['seat_ids'])
            ->map(fn ($seatId): int => (int) $seatId)
            ->unique()
            ->sort()
            ->values();
        $layout = $this->layouts->resolveForShowtime($showtime);
        $seats = Seat::query()
            ->where('room_id', $showtime->room_id)
            ->whereHas('layoutCells', fn ($query) => $query->where('room_layout_id', $layout->id))
            ->whereIn('id', $seatIds)
            ->orderBy('row')
            ->orderBy('number')
            ->get();

        $this->assertSeatsAvailable($showtime, $seats, $seatIds, $layout->id);
        $food = $this->food->calculate($draft['food_items'] ?? []);
        $prices = $this->pricing->calculate($showtime, $seats)->withFood($food);

        return new BookingCheckoutPreview($showtime, $seats, $prices);
    }

    private function assertShowtimeAvailable(Showtime $showtime): void
    {
        $startsAt = Carbon::parse($showtime->show_date->format('Y-m-d').' '.$showtime->show_time);
        $canonicalCinemaId = $this->cinemaContext->id();

        if ($showtime->status !== 'active'
            || $showtime->cinema_id !== $canonicalCinemaId
            || $showtime->room?->status !== 'active'
            || $showtime->room?->cinema_id !== $canonicalCinemaId
            || ! $showtime->roomLayout
            || $showtime->roomLayout->status !== 'published'
            || $showtime->roomLayout->room_id !== $showtime->room_id
            || ! $startsAt->isFuture()) {
            throw ValidationException::withMessages([
                'showtime' => 'Suất chiếu không còn khả dụng.',
            ]);
        }
    }

    private function assertSeatsAvailable(
        Showtime $showtime,
        Collection $seats,
        Collection $seatIds,
        int $layoutId,
    ): void {
        if ($seats->count() !== $seatIds->count()
            || $seats->contains(fn ($seat): bool => $seat->status !== 'active')) {
            throw ValidationException::withMessages([
                'seat_ids' => 'Ghế đã chọn không hợp lệ hoặc không còn khả dụng.',
            ]);
        }

        foreach ($seats->where('type', 'couple')->groupBy('pair_code') as $pairCode => $pair) {
            $positions = $pair->pluck('pair_position')->sort()->values()->all();
            $layoutPairCount = $pairCode
                ? Seat::query()
                    ->where('room_id', $showtime->room_id)
                    ->where('type', 'couple')
                    ->where('pair_code', $pairCode)
                    ->whereHas('layoutCells', fn ($query) => $query->where('room_layout_id', $layoutId))
                    ->count()
                : 0;

            if ($pair->count() !== 2 || $positions !== ['left', 'right'] || $layoutPairCount !== 2
                || ! SeatPresentation::isValidCouple($pair)) {
                throw ValidationException::withMessages([
                    'seat_ids' => 'Ghế đôi phải được chọn đủ cả cặp.',
                ]);
            }
        }

        if (BookingSeat::query()
            ->where('showtime_id', $showtime->id)
            ->where('active_lock_key', BookingSeat::ACTIVE_LOCK_KEY)
            ->whereIn('seat_id', $seatIds)
            ->exists()) {
            throw ValidationException::withMessages([
                'seat_ids' => 'Một hoặc nhiều ghế vừa được người khác giữ.',
            ]);
        }
    }
}
