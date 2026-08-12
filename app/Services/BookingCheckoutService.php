<?php

namespace App\Services;

use App\Exceptions\BookingCheckoutConflictException;
use App\Exceptions\PricingConfigurationException;
use App\Models\Booking;
use App\Models\Seat;
use App\Models\Showtime;
use App\Models\User;
use App\Services\Seats\SeatAvailabilitySnapshot;
use App\Services\Seats\SeatSelectionPolicy;
use App\Support\SeatPresentation;
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
        private readonly SeatSelectionPolicy $seatSelectionPolicy,
        private readonly BookingExpirationService $expiration,
        private readonly PromotionService $promotions,
        private readonly ShowtimeLifecycleService $lifecycle,
    ) {}

    public function createPendingBooking(
        int $showtimeId,
        array $seatIds,
        ?int $userId,
        string $customerEmail,
        string $checkoutToken,
        array|Collection|null $foodSelection = null,
        string $salesChannel = Booking::SALES_CHANNEL_ONLINE,
        ?User $counterActor = null,
        ?string $customerName = null,
        ?string $customerPhone = null,
        array $discountCodes = [],
    ): BookingCheckoutResult {
        if (! in_array($salesChannel, Booking::SALES_CHANNELS, true)) {
            throw new InvalidArgumentException('Unsupported booking sales channel.');
        }
        if ($salesChannel === Booking::SALES_CHANNEL_COUNTER
            && (! $counterActor?->isActive() || ! $counterActor->hasPermission('counter_sales.create'))) {
            throw new LogicException('The authenticated actor cannot create counter bookings.');
        }
        if ($salesChannel === Booking::SALES_CHANNEL_ONLINE && $counterActor !== null) {
            throw new LogicException('Online bookings cannot have a counter creator.');
        }

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
            $salesChannel,
            $counterActor?->getKey(),
            $discountCodes,
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
                    $salesChannel,
                    $counterActor,
                    $customerName,
                    $customerPhone,
                    $discountCodes,
                ): Booking {
                    $existing = Booking::query()
                        ->where('checkout_idempotency_key_hash', $checkoutHash)
                        ->lockForUpdate()
                        ->first();

                    if ($existing) {
                        return $existing;
                    }

                    $showtime = Showtime::query()
                        ->with(['movie', 'cinema', 'room.cinema', 'roomLayout'])
                        ->lockForUpdate()
                        ->findOrFail($showtimeId);

                    $this->assertShowtimeCanBeReserved($showtime, $salesChannel);
                    $normalizedSeatIds = collect($seatIds)
                        ->map(fn ($id) => (int) $id)
                        ->unique()
                        ->values();
                    $this->expiration->expireStaleForSeats($showtime->id, $normalizedSeatIds);

                    $layout = $this->layouts->resolveForShowtime($showtime);
                    $seats = Seat::query()
                        ->where('room_id', $showtime->room_id)
                        ->whereHas('layoutCells', fn ($query) => $query->where('room_layout_id', $layout->id))
                        ->whereIn('id', $normalizedSeatIds)
                        ->lockForUpdate()
                        ->orderBy('id')
                        ->get();

                    $this->assertSeatsCanBeReserved($seats, $normalizedSeatIds, $layout);
                    if ($salesChannel === Booking::SALES_CHANNEL_ONLINE) {
                        $this->assertLogicalSeatLimit($seats);
                    }

                    // Final authoritative gap check inside the showtime transaction. The shared
                    // showtime lock serializes checkout writers, selected Seat rows are locked,
                    // and the snapshot locks existing BookingSeat inventory before insertion.
                    $this->assertNoIsolatedSeat($showtime, $layout, $normalizedSeatIds);

                    $foodBreakdown = $this->food->calculate($foodSelection, (int) $showtime->cinema_id);
                    try {
                        $priceBreakdown = $this->pricing
                            ->calculate($showtime, $seats)
                            ->withFood($foodBreakdown);
                    } catch (PricingConfigurationException $exception) {
                        throw ValidationException::withMessages(['pricing' => $exception->getMessage()]);
                    }

                    $guestToken = $userId === null && $salesChannel === Booking::SALES_CHANNEL_ONLINE
                        ? $this->tokens->guestAccessTokenForCheckout($checkoutToken)
                        : null;

                    $booking = new Booking;
                    $booking->forceFill([
                        'user_id' => $userId,
                        'sales_channel' => $salesChannel,
                        'created_by_staff_id' => $counterActor?->getKey(),
                        'customer_name' => $customerName === null ? null : trim($customerName),
                        'customer_phone' => $customerPhone === null ? null : trim($customerPhone),
                        'customer_email' => trim($customerEmail) === '' ? null : trim($customerEmail),
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
                        'gross_amount' => $priceBreakdown->grandTotal,
                        'promotion_discount_amount' => 0,
                        'currency' => $priceBreakdown->currency,
                        'payment_status' => 'unpaid',
                        'booking_status' => 'pending_payment',
                        'expires_at' => now()->addMinutes(max(1, (int) config('booking.pending_ttl_minutes', 15))),
                    ])->save();

                    $promotionQuote = $this->promotions->reserveForBooking($booking, $discountCodes, $priceBreakdown->grandTotal);
                    $booking->forceFill([
                        'promotion_discount_amount' => $promotionQuote->discountAmount,
                        'total_amount' => $promotionQuote->finalAmount,
                    ])->save();

                    $this->seatLocks->acquire(
                        $booking,
                        $seats,
                        $priceBreakdown->seatSnapshots,
                        $priceBreakdown->seatPricingSnapshots,
                    );
                    $this->food->persist($foodBreakdown, [
                        'booking_id' => $booking->id,
                        'customer_name' => $booking->customer_name,
                        'customer_phone' => $booking->customer_phone,
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

    /**
     * Reject a hold that would leave exactly one isolated available seat. Runs inside the
     * hold transaction after the shared showtime lock and selected Seat locks. The snapshot
     * also locks existing BookingSeat inventory before the hold is persisted.
     */
    private function assertNoIsolatedSeat(Showtime $showtime, $layout, Collection $seatIds): void
    {
        $snapshot = SeatAvailabilitySnapshot::for($showtime, $layout, lockHolds: true);

        $message = $this->seatSelectionPolicy->violationMessage(
            $layout,
            $snapshot->unavailableSeatIds,
            $seatIds,
            $snapshot->cells,
        );
        if ($message !== null) {
            throw ValidationException::withMessages([
                'seat_ids' => $message,
            ]);
        }
    }

    private function assertLogicalSeatLimit(Collection $seats): void
    {
        $maximum = max(1, (int) config('booking.max_logical_seat_units', 8));
        if (SeatPresentation::groups($seats)->count() > $maximum) {
            throw ValidationException::withMessages([
                'seat_ids' => "Mỗi đơn chỉ được chọn tối đa {$maximum} ghế hoặc cặp ghế đôi.",
            ]);
        }
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
            $booking->user_id === null && $booking->sales_channel === Booking::SALES_CHANNEL_ONLINE
                ? $this->tokens->guestAccessTokenForCheckout($checkoutToken)
                : null,
            $replayed,
        );
    }

    private function assertShowtimeCanBeReserved(Showtime $showtime, string $salesChannel): void
    {
        if ($showtime->status !== 'active'
            || $showtime->cinema?->status !== 'active'
            || $showtime->cinema?->archived_at !== null
            || $showtime->room?->status !== 'active'
            || $showtime->room?->cinema_id !== $showtime->cinema_id
            || ! $showtime->roomLayout
            || $showtime->roomLayout->status !== 'published'
            || $showtime->roomLayout->room_id !== $showtime->room_id) {
            throw ValidationException::withMessages([
                'showtime' => 'Suất chiếu không còn khả dụng.',
            ]);
        }

        $snapshot = $this->lifecycle->snapshot($showtime);
        $open = $salesChannel === Booking::SALES_CHANNEL_ONLINE
            ? $this->lifecycle->isCustomerBookingOpen($showtime, $snapshot['now'])
            : $snapshot['now']->lt($snapshot['starts_at']);

        if (! $open) {
            throw ValidationException::withMessages([
                'showtime' => $salesChannel === Booking::SALES_CHANNEL_ONLINE
                    ? 'Suất chiếu này đã đóng nhận đặt vé.'
                    : 'Suất chiếu không còn khả dụng.',
            ]);
        }
    }

    private function assertSeatsCanBeReserved(Collection $seats, Collection $seatIds, $layout): void
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
            $layoutPair = $pairCode
                ? $layout->cells->whereIn('seat_id', $selectedPair->pluck('id'))->values()
                : collect();
            $layoutPairIsContiguous = $layoutPair->count() === 2
                && $layoutPair->pluck('y_position')->unique()->count() === 1
                && abs((int) $layoutPair[0]->x_position - (int) $layoutPair[1]->x_position) === 1;

            if ($selectedPair->count() !== 2 || ! $validPositions || ! $layoutPairIsContiguous
                || ! SeatPresentation::isValidCouple($selectedPair)) {
                throw ValidationException::withMessages([
                    'seat_ids' => 'Ghế đôi phải được giữ đủ cả cặp trong cùng layout.',
                ]);
            }
        }
    }
}
