<?php

namespace App\Services;

use App\Domain\Bookings\BookingPriceBreakdown;
use App\Models\Showtime;
use App\Support\SeatPresentation;
use Illuminate\Support\Collection;

final readonly class BookingCheckoutPreview
{
    public function __construct(
        public Showtime $showtime,
        public Collection $seats,
        public BookingPriceBreakdown $prices,
    ) {}

    public function seatSummaries(): Collection
    {
        return SeatPresentation::groups($this->seats)->map(fn (array $group): array => [
            'id' => $group['seat_ids'][0] ?? 0,
            'seat_ids' => $group['seat_ids'],
            'seat_code' => $group['seat_code'],
            'label' => $group['label'],
            'type' => $group['type'],
            'is_couple' => $group['is_couple'],
            'price' => $group['seats']->sum(fn ($seat): int => (int) ($this->prices->seatSnapshots[$seat->id] ?? 0)),
        ]);
    }
}
