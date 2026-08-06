<?php

namespace App\Services;

use App\Exceptions\BookingCheckoutConflictException;
use App\Models\Booking;
use App\Models\Seat;
use App\Models\Showtime;
use App\Support\SeatPresentation;
use Carbon\Carbon;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;
use LogicException;

class BookingCheckoutService
{
    private const BOOKING_CODE_ATTEMPTS = 3;

    public function __construct(
        private readonly RoomLayoutService $layouts,
        private readonly BookingSeatLockService $seatLocks,
        private readonly BookingTokenService $tokens,
        private readonly BookingCheckoutFingerprint $fingerprints,
        private readonly BookingCodeGenerator $bookingCodes,
        private readonly BookingPricingService $pricing,
        private readonly BookingFoodService $food,
    ) {}

    public function createPendingBooking(
        int $showtimeId,
        array $seatIds,
        ?int $userId,
        string $customerEmail,
        string $checkoutToken,
        array|Collection|null $foodSelection = null,
    ): BookingCheckoutResult {
        if (! $this->tokens->isValidCheckoutToken($checkoutToken)) {
            throw new InvalidArgumentException('The checkout idempotency token was not issued by MovieMate.');
        }

        $checkoutHash = $this->tokens->hash($checkoutToken);
        $requestFingerprint = $this->fingerprints->hash(
            $showtimeId,
            $seatIds,
            $customerEmail,
            $userId,
            $foodSelection,
        );
        $existing = Booking::query()
            ->where('checkout_idempotency_key_hash', $checkoutHash)
            ->first();

        if ($existing) {
            return $this->result($existing, $checkoutToken, $requestFingerprint, true);
        }

        for ($attempt = 1; $attempt <= self::BOOKING_CODE_ATTEMPTS; $attempt++) {
            $bookingCode = $this->bookingCodes->generate();

            try {
                $booking = DB::transaction(function () use (
                    $showtimeId,
                    $seatIds,
                    $userId,
                    $customerEmail,
                    $checkoutHash,
                    $checkoutToken,
                    $requestFingerprint,
                    $bookingCode,
                    $foodSelection,
                ): Booking {
                    $existing = Booking::query()
                        ->where('checkout_idempotency_key_hash', $checkoutHash)
                        ->lockForUpdate()
                        ->first();

                    if ($existing) {
                        return $existing;
                    }

                    $showtime = Showtime::query()
                        ->with(['cinema', 'room', 'roomLayout'])
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

                    $foodBreakdown = $this->food->calculate($foodSelection, (int) $showtime->cinema_id);
                    $priceBreakdown = $this->pricing
                        ->calculate($showtime, $seats)
                        ->withFood($foodBreakdown);

                    $guestToken = $userId === null
                        ? $this->tokens->guestAccessTokenForCheckout($checkoutToken)
                        : null;

                    $booking = Booking::query()->create([
                        'user_id' => $userId,
                        'customer_email' => $customerEmail,
                        'guest_access_token_hash' => $guestToken === null ? null : $this->tokens->hash($guestToken),
                        'guest_access_expires_at' => $guestToken === null
                            ? null
                            : now()->addMinutes(max(1, (int) config('booking.guest_access_ttl_minutes', 1440))),
                        'checkout_idempotency_key_hash' => $checkoutHash,
                        'checkout_request_fingerprint_hash' => $requestFingerprint,
                        'showtime_id' => $showtime->id,
                        'booking_code' => $bookingCode,
                        'total_amount' => $priceBreakdown->grandTotal,
                        'seat_subtotal' => $priceBreakdown->seatSubtotal,
                        'food_subtotal' => $priceBreakdown->foodSubtotal,
                        'currency' => $priceBreakdown->currency,
                        'payment_status' => 'unpaid',
                        'booking_status' => 'pending_payment',
                        'expires_at' => now()->addMinutes(max(1, (int) config('booking.pending_ttl_minutes', 15))),
                    ]);

                    $this->seatLocks->acquire($booking, $seats, $priceBreakdown->seatSnapshots);
                    $this->food->persist($foodBreakdown, [
                        'booking_id' => $booking->id,
                    ]);

                    return $booking;
                });
            } catch (UniqueConstraintViolationException) {
                $booking = Booking::query()
                    ->where('checkout_idempotency_key_hash', $checkoutHash)
                    ->first();

                if ($booking) {
                    return $this->result(
                        $booking,
                        $checkoutToken,
                        $requestFingerprint,
                        true,
                    );
                }

                if ($attempt < self::BOOKING_CODE_ATTEMPTS
                    && Booking::query()->where('booking_code', $bookingCode)->exists()) {
                    continue;
                }

                throw ValidationException::withMessages([
                    'seat_ids' => 'Một hoặc nhiều ghế đã được giữ cho suất chiếu này.',
                ]);
            }

            return $this->result(
                $booking,
                $checkoutToken,
                $requestFingerprint,
                ! $booking->wasRecentlyCreated,
            );
        }

        throw new LogicException('Không thể tạo mã đặt vé sau nhiều lần thử.');
    }

    private function result(
        Booking $booking,
        string $checkoutToken,
        string $requestFingerprint,
        bool $replayed,
    ): BookingCheckoutResult {
        if (! $this->fingerprints->matches(
            $booking->checkout_request_fingerprint_hash,
            $requestFingerprint,
        )) {
            throw new BookingCheckoutConflictException;
        }

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

            if ($selectedPair->count() !== 2 || ! $validPositions || $layoutPairCount !== 2
                || ! SeatPresentation::isValidCouple($selectedPair)) {
                throw ValidationException::withMessages([
                    'seat_ids' => 'Ghế đôi phải được giữ đủ cả cặp trong cùng layout.',
                ]);
            }
        }
    }
}
