<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\BookingSeat;
use App\Models\Seat;
use App\Models\Showtime;
use App\Services\Seats\SeatAvailabilitySnapshot;
use App\Services\Seats\SeatSelectionPolicy;
use App\Support\SeatPresentation;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class BookingCheckoutPreviewService
{
    public function __construct(
        private readonly RoomLayoutService $layouts,
        private readonly BookingPricingService $pricing,
        private readonly BookingFoodService $food,
        private readonly SeatSelectionPolicy $seatSelectionPolicy,
        private readonly BookingTokenService $tokens,
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

        $ownBookingId = $this->ownActiveBookingId($draft, $showtime);
        $this->assertSeatsAvailable($showtime, $seats, $seatIds, $layout, $ownBookingId);
        $this->assertNoIsolatedSeat($showtime, $layout, $seatIds, $ownBookingId);
        $food = $this->food->calculate($draft['food_items'] ?? [], (int) $showtime->cinema_id);
        $prices = $this->pricing->calculate($showtime, $seats)->withFood($food);

        return new BookingCheckoutPreview($showtime, $seats, $prices);
    }

    private function assertShowtimeAvailable(Showtime $showtime): void
    {
        $startsAt = Carbon::parse($showtime->show_date->format('Y-m-d').' '.$showtime->show_time);
        if ($showtime->status !== 'active'
            || $showtime->cinema?->status !== 'active'
            || $showtime->cinema?->archived_at !== null
            || $showtime->room?->status !== 'active'
            || $showtime->room?->cinema_id !== $showtime->cinema_id
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
        $layout,
        ?int $ownBookingId,
    ): void {
        if ($seats->count() !== $seatIds->count()
            || $seats->contains(fn ($seat): bool => $seat->status !== 'active')) {
            throw ValidationException::withMessages([
                'seat_ids' => 'Ghế đã chọn không hợp lệ hoặc không còn khả dụng.',
            ]);
        }

        foreach ($seats->where('type', 'couple')->groupBy('pair_code') as $pairCode => $pair) {
            $positions = $pair->pluck('pair_position')->sort()->values()->all();
            $layoutPair = $pairCode
                ? $layout->cells->whereIn('seat_id', $pair->pluck('id'))->values()
                : collect();
            $layoutPairIsContiguous = $layoutPair->count() === 2
                && $layoutPair->pluck('y_position')->unique()->count() === 1
                && abs((int) $layoutPair[0]->x_position - (int) $layoutPair[1]->x_position) === 1;

            if ($pair->count() !== 2 || $positions !== ['left', 'right'] || ! $layoutPairIsContiguous
                || ! SeatPresentation::isValidCouple($pair)) {
                throw ValidationException::withMessages([
                    'seat_ids' => 'Ghế đôi phải được chọn đủ cả cặp.',
                ]);
            }
        }

        if (BookingSeat::query()
            ->where('showtime_id', $showtime->id)
            ->where('active_lock_key', BookingSeat::ACTIVE_LOCK_KEY)
            ->when($ownBookingId !== null, fn ($query) => $query->where('booking_id', '!=', $ownBookingId))
            ->whereIn('seat_id', $seatIds)
            ->exists()) {
            throw ValidationException::withMessages([
                'seat_ids' => 'Một hoặc nhiều ghế vừa được người khác giữ.',
            ]);
        }
    }

    /**
     * Reject selections that would leave exactly one isolated available seat. Evaluated
     * against the authoritative layout snapshot, not against browser-supplied seat state.
     */
    private function assertNoIsolatedSeat(
        Showtime $showtime,
        $layout,
        Collection $seatIds,
        ?int $ownBookingId,
    ): void {
        $snapshot = SeatAvailabilitySnapshot::for(
            $showtime,
            $layout,
            excludeBookingId: $ownBookingId,
        );

        if ($this->seatSelectionPolicy->violates(
            $layout,
            $snapshot->unavailableSeatIds,
            $seatIds,
            $snapshot->cells,
        )) {
            throw ValidationException::withMessages([
                'seat_ids' => SeatSelectionPolicy::MESSAGE_ISOLATED_SEAT,
            ]);
        }
    }

    /**
     * Resolve an exclusion only from the signed, session-held checkout capability. A browser
     * cannot nominate an arbitrary booking id, and an expired/cancelled aggregate is never
     * treated as the current checkout's valid hold.
     */
    private function ownActiveBookingId(array $draft, Showtime $showtime): ?int
    {
        $checkoutToken = $draft['checkout_token'] ?? null;
        if (! is_string($checkoutToken) || ! $this->tokens->isValidCheckoutToken($checkoutToken)) {
            return null;
        }

        $bookingId = Booking::query()
            ->where('checkout_idempotency_key_hash', $this->tokens->hash($checkoutToken))
            ->where('showtime_id', $showtime->id)
            ->where('booking_status', 'pending_payment')
            ->where('payment_status', 'unpaid')
            ->where('expires_at', '>', now())
            ->value('id');

        return $bookingId === null ? null : (int) $bookingId;
    }
}
