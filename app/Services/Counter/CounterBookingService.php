<?php

namespace App\Services\Counter;

use App\Models\Booking;
use App\Models\BookingPromotion;
use App\Models\Order;
use App\Models\Showtime;
use App\Models\User;
use App\Services\ActivityLogger;
use App\Services\BookingCancellationService;
use App\Services\BookingCheckoutResult;
use App\Services\BookingCheckoutService;
use App\Services\BookingFoodService;
use App\Services\CinemaAccessService;
use App\Services\PromotionService;
use App\Services\PublicShowtimeCatalog;
use App\Services\ZeroPayableBookingSettlement;
use App\Support\SeatPresentation;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class CounterBookingService
{
    public function __construct(
        private readonly BookingCheckoutService $checkout,
        private readonly BookingFoodService $food,
        private readonly BookingCancellationService $cancellations,
        private readonly CinemaAccessService $cinemas,
        private readonly PublicShowtimeCatalog $showtimes,
        private readonly PromotionService $promotions,
        private readonly ZeroPayableBookingSettlement $zeroPayable,
        private readonly ActivityLogger $activities,
    ) {}

    public function createHold(
        User $actor,
        Showtime $showtime,
        array $seatIds,
        string $checkoutToken,
        ?string $customerName,
        ?string $customerPhone,
        ?string $customerEmail,
    ): BookingCheckoutResult {
        $this->assertPermission($actor, 'counter_sales.create');
        $this->cinemas->authorizeCinema($actor, (int) $showtime->cinema_id);
        if (! $this->showtimes->isSellable($showtime)) {
            throw ValidationException::withMessages(['showtime' => 'Suất chiếu không còn khả dụng để bán tại quầy.']);
        }

        $result = $this->checkout->createPendingBooking(
            (int) $showtime->id,
            $seatIds,
            null,
            (string) $customerEmail,
            $checkoutToken,
            null,
            Booking::SALES_CHANNEL_COUNTER,
            $actor,
            $customerName,
            $customerPhone,
        );

        if (! $result->replayed) {
            $booking = $result->booking->load('bookingSeats.seat');
            $this->activities->log(
                'counter.booking_created',
                $booking,
                [],
                ['sales_channel' => Booking::SALES_CHANNEL_COUNTER, 'status' => 'pending_payment'],
                $this->context($booking),
            );
        }

        return $result;
    }

    public function authorized(User $actor, Booking $booking): Booking
    {
        abort_unless($booking->sales_channel === Booking::SALES_CHANNEL_COUNTER, 404);
        $this->cinemas->authorizeCinema($actor, (int) $booking->cinema_id);

        return $booking->load([
            'createdByStaff:id,name,email', 'showtime.movie', 'showtime.cinema', 'showtime.room',
            'bookingSeats.seat', 'foodOrder.items', 'payments.settledBy:id,name,email',
            'ticketPrint.printedBy:id,name',
        ]);
    }

    public function updateFood(User $actor, Booking $booking, ?array $selection): Booking
    {
        $this->assertPermission($actor, 'counter_sales.create');
        $this->authorized($actor, $booking);

        return DB::transaction(function () use ($booking, $selection): Booking {
            $locked = Booking::query()->lockForUpdate()->findOrFail($booking->id);
            if ($locked->sales_channel !== Booking::SALES_CHANNEL_COUNTER
                || $locked->booking_status !== 'pending_payment'
                || $locked->payment_status !== 'unpaid'
                || ! $locked->expires_at?->isFuture()
                || $locked->payments()->lockForUpdate()->exists()
                || $locked->promotionUsage()->lockForUpdate()->exists()) {
                throw ValidationException::withMessages(['booking' => 'Đơn tại quầy không còn cho phép cập nhật đồ ăn.']);
            }

            $breakdown = $this->food->calculate($selection, (int) $locked->cinema_id);
            $existing = Order::query()->where('booking_id', $locked->id)->lockForUpdate()->first();
            if ($existing) {
                $existing->items()->delete();
                $existing->delete();
            }
            $this->food->persist($breakdown, [
                'booking_id' => $locked->id,
                'customer_name' => $locked->customer_name,
                'customer_phone' => $locked->customer_phone,
            ]);
            $gross = (int) $locked->seat_subtotal + $breakdown->foodSubtotal;
            $locked->forceFill([
                'food_subtotal' => $breakdown->foodSubtotal,
                'gross_amount' => $gross,
                'promotion_discount_amount' => 0,
                'total_amount' => $gross,
            ])->save();

            $this->activities->log(
                'counter.food_updated',
                $locked,
                [],
                ['food_subtotal' => $breakdown->foodSubtotal, 'total_amount' => (int) $locked->total_amount],
                $this->context($locked),
            );

            return $locked->fresh();
        });
    }

    public function applyPromotion(User $actor, Booking $booking, string $promotionCode): Booking
    {
        $this->assertPermission($actor, 'counter_sales.settle');
        $this->authorized($actor, $booking);

        return DB::transaction(function () use ($booking, $promotionCode): Booking {
            $locked = Booking::query()->lockForUpdate()->findOrFail($booking->id);
            if ($locked->sales_channel !== Booking::SALES_CHANNEL_COUNTER
                || $locked->booking_status !== 'pending_payment'
                || $locked->payment_status !== 'unpaid'
                || ! $locked->expires_at?->isFuture()
                || $locked->payments()->lockForUpdate()->exists()) {
                throw ValidationException::withMessages(['promotion_code' => 'Đơn tại quầy không còn cho phép áp dụng mã khuyến mãi.']);
            }

            $existing = $locked->promotionUsage()->lockForUpdate()->first();
            if ($existing !== null) {
                throw ValidationException::withMessages([
                    'promotion_code' => $existing->status === BookingPromotion::STATUS_RESERVED
                        ? 'Đơn đã áp dụng một mã khuyến mãi. Mỗi đơn chỉ được dùng một mã.'
                        : 'Lịch sử khuyến mãi của đơn đã được chốt và không thể thay đổi.',
                ]);
            }

            $gross = (int) $locked->seat_subtotal + (int) $locked->food_subtotal;
            if ($gross <= 0 || (int) $locked->gross_amount !== $gross) {
                throw ValidationException::withMessages(['promotion_code' => 'Tổng tiền của đơn chưa đồng bộ; chưa thể áp dụng mã khuyến mãi.']);
            }

            $quote = $this->promotions->reserveForBooking($locked, $promotionCode, $gross);
            $locked->forceFill([
                'promotion_discount_amount' => $quote->discountAmount,
                'total_amount' => $quote->finalAmount,
            ])->save();

            $this->activities->log(
                'counter.promotion_applied',
                $locked,
                [],
                [
                    'promotion_code' => $locked->promotionUsage()->value('code_snapshot'),
                    'promotion_discount_amount' => $quote->discountAmount,
                    'total_amount' => $quote->finalAmount,
                ],
                $this->context($locked),
            );

            if ($quote->finalAmount === 0) {
                $this->zeroPayable->settle($locked);
            }

            return $locked->fresh(['promotionUsage']);
        }, 3);
    }

    public function cancel(User $actor, Booking $booking): void
    {
        $this->assertPermission($actor, 'counter_sales.cancel');
        $this->authorized($actor, $booking);
        $result = $this->cancellations->cancelCustomer(
            (int) $booking->id,
            'counter_customer_walkaway',
            'counter.booking_cancelled',
        );
        if (! $result->cancelled && ! $result->alreadyCancelled) {
            throw ValidationException::withMessages(['booking' => 'Đơn tại quầy không thể hủy ở trạng thái hiện tại.']);
        }
    }

    private function assertPermission(User $actor, string $permission): void
    {
        abort_unless($actor->isActive() && $actor->hasPermission($permission), 403);
    }

    private function context(Booking $booking): array
    {
        $booking->loadMissing('bookingSeats.seat');

        return [
            'booking_id' => $booking->id,
            'booking_code' => $booking->booking_code,
            'cinema_id' => $booking->cinema_id,
            'showtime_id' => $booking->showtime_id,
            'sales_channel' => $booking->sales_channel,
            'seat_count' => $booking->bookingSeats->count(),
            'seat_units' => SeatPresentation::groups($booking->bookingSeats->pluck('seat')->filter()->values())
                ->pluck('label')->filter()->take(50)->values()->all(),
            'total_amount' => (int) $booking->total_amount,
            'food_total' => (int) $booking->food_subtotal,
        ];
    }
}
