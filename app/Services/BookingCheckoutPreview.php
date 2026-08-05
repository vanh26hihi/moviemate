<?php

namespace App\Services;

use App\Domain\Bookings\BookingPriceBreakdown;
use App\Models\Showtime;
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
        return $this->seats->map(fn ($seat): array => [
            'id' => (int) $seat->id,
            'seat_code' => (string) $seat->seat_code,
            'type' => (string) $seat->type,
            'price' => $this->prices->seatSnapshots[$seat->id],
        ]);
    }
}
