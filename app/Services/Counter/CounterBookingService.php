<?php

namespace App\Services\Counter;

use App\Models\Booking;
use App\Models\Order;
use App\Models\Showtime;
use App\Models\User;
use App\Services\ActivityLogger;
use App\Services\BookingCancellationService;
use App\Services\BookingCheckoutResult;
use App\Services\BookingCheckoutService;
use App\Services\BookingFoodService;
use App\Services\CinemaAccessService;
use App\Services\PublicShowtimeCatalog;
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
            'ticketPrint.printedBy:id,name', 'acceptedTicketCheckin.actor:id,name',
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
                || $locked->payments()->lockForUpdate()->exists()) {
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
            $locked->forceFill([
                'food_subtotal' => $breakdown->foodSubtotal,
                'total_amount' => (int) $locked->seat_subtotal + $breakdown->foodSubtotal,
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

    public function cancel(User $actor, Booking $booking): void
    {
        $this->assertPermission($actor, 'counter_sales.cancel');
        $this->authorized($actor, $booking);
        $result = $this->cancellations->cancel(
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
