<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\Seat;
use App\Models\Showtime;
use Carbon\Carbon;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

class BookingCheckoutService
{
    public function __construct(
        private readonly RoomLayoutService $layouts,
        private readonly BookingSeatLockService $seatLocks,
        private readonly BookingTokenService $tokens,
    ) {}

    public function createPendingBooking(
        int $showtimeId,
        array $seatIds,
        ?int $userId,
        string $customerEmail,
        string $checkoutToken,
    ): BookingCheckoutResult {
        if (! $this->tokens->isValidCheckoutToken($checkoutToken)) {
            throw new InvalidArgumentException('The checkout idempotency token was not issued by MovieMate.');
        }

        $checkoutHash = $this->tokens->hash($checkoutToken);
        $existing = Booking::query()->where('checkout_idempotency_key_hash', $checkoutHash)->first();

        if ($existing) {
            return $this->result($existing, $checkoutToken, true);
        }

        try {
            $booking = DB::transaction(function () use (
                $showtimeId,
                $seatIds,
                $userId,
                $customerEmail,
                $checkoutHash,
                $checkoutToken,
            ): Booking {
                $existing = Booking::query()
                    ->where('checkout_idempotency_key_hash', $checkoutHash)
                    ->lockForUpdate()
                    ->first();

                if ($existing) {
                    return $existing;
                }

                $showtime = Showtime::query()
                    ->with(['room', 'roomLayout'])
                    ->lockForUpdate()
                    ->findOrFail($showtimeId);

                $this->assertShowtimeCanBeReserved($showtime);

                $normalizedSeatIds = collect($seatIds)
                    ->map(fn ($id) => (int) $id)
                    ->unique()
                    ->values();
                $layout = $this->layouts->resolveForShowtime($showtime);
                $seats = Seat::query()
                    ->where('room_id', $showtime->room_id)
                    ->whereHas('layoutCells', fn ($query) => $query->where('room_layout_id', $layout->id))
                    ->whereIn('id', $normalizedSeatIds)
                    ->lockForUpdate()
                    ->orderBy('id')
                    ->get();

                $this->assertSeatsCanBeReserved($seats, $normalizedSeatIds, $layout->id);

                $priceSnapshots = [];
                $totalAmount = 0;
                foreach ($seats as $seat) {
                    $price = $showtime->priceForSeatType($seat->type);
                    $priceSnapshots[$seat->id] = $price;
                    $totalAmount += $price;
                }

                $guestToken = $userId === null
                    ? $this->tokens->guestAccessTokenForCheckout($checkoutToken)
                    : null;

                $booking = Booking::query()->create([
                    'user_id' => $userId,
                    'customer_email' => $customerEmail,
                    'guest_access_token_hash' => $guestToken === null ? null : $this->tokens->hash($guestToken),
                    'checkout_idempotency_key_hash' => $checkoutHash,
                    'showtime_id' => $showtime->id,
                    'booking_code' => $this->generateBookingCode(),
                    'total_amount' => $totalAmount,
                    'payment_status' => 'unpaid',
                    'booking_status' => 'pending_payment',
                    'expires_at' => now()->addMinutes(max(1, (int) config('booking.pending_ttl_minutes', 15))),
                ]);

                $this->seatLocks->acquire($booking, $seats, $priceSnapshots);

                return $booking;
            });
        } catch (UniqueConstraintViolationException $exception) {
            $booking = Booking::query()->where('checkout_idempotency_key_hash', $checkoutHash)->first();

            if (! $booking) {
                throw ValidationException::withMessages([
                    'seat_ids' => 'Một hoặc nhiều ghế đã được giữ cho suất chiếu này.',
                ]);
            }

            return $this->result($booking, $checkoutToken, true);
        }

        return $this->result($booking, $checkoutToken, false);
    }

    private function result(Booking $booking, string $checkoutToken, bool $replayed): BookingCheckoutResult
    {
        return new BookingCheckoutResult(
            $booking,
            $booking->user_id === null ? $this->tokens->guestAccessTokenForCheckout($checkoutToken) : null,
            $replayed,
        );
    }

    private function assertShowtimeCanBeReserved(Showtime $showtime): void
    {
        $startsAt = Carbon::parse($showtime->show_date->format('Y-m-d').' '.$showtime->show_time);

        if ($showtime->status !== 'active'
            || $showtime->room?->status !== 'active'
            || ! $showtime->roomLayout
            || $showtime->roomLayout->status !== 'published'
            || $showtime->roomLayout->room_id !== $showtime->room_id
            || ! $startsAt->isFuture()) {
            throw ValidationException::withMessages([
                'showtime' => 'Suất chiếu không còn khả dụng.',
            ]);
        }
    }

    private function assertSeatsCanBeReserved(Collection $seats, Collection $seatIds, int $layoutId): void
    {
        if ($seats->count() !== $seatIds->count()) {
            throw ValidationException::withMessages([
                'seat_ids' => 'Ghế đã chọn không hợp lệ hoặc không thuộc layout của suất chiếu.',
            ]);
        }

        if ($seats->contains(fn ($seat) => $seat->status !== 'active')) {
            throw ValidationException::withMessages([
                'seat_ids' => 'Có ghế đang bảo trì hoặc không khả dụng.',
            ]);
        }

        foreach ($seats->where('type', 'couple')->groupBy('pair_code') as $pairCode => $selectedPair) {
            $validPositions = $selectedPair->pluck('pair_position')->sort()->values()->all() === ['left', 'right'];
            $layoutPairCount = $pairCode
                ? Seat::query()
                    ->where('room_id', $selectedPair->first()->room_id)
                    ->where('type', 'couple')
                    ->where('pair_code', $pairCode)
                    ->whereHas('layoutCells', fn ($query) => $query->where('room_layout_id', $layoutId))
                    ->count()
                : 0;

            if ($selectedPair->count() !== 2 || ! $validPositions || $layoutPairCount !== 2) {
                throw ValidationException::withMessages([
                    'seat_ids' => 'Ghế đôi phải được giữ đủ cả cặp trong cùng layout.',
                ]);
            }
        }
    }

    private function generateBookingCode(): string
    {
        $year = now()->format('Y');
        $latestBooking = Booking::query()
            ->whereYear('created_at', $year)
            ->lockForUpdate()
            ->latest('id')
            ->first();
        $nextNumber = $latestBooking
            ? ((int) substr($latestBooking->booking_code, -4)) + 1
            : 1;

        return 'MMT-'.$year.'-'.str_pad((string) $nextNumber, 4, '0', STR_PAD_LEFT);
    }
}
