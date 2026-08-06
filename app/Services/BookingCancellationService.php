<?php

namespace App\Services;

use App\Domain\Bookings\BookingCancellationResult;
use App\Models\Booking;
use App\Models\BookingSeat;
use App\Models\Payment;
use App\Support\SeatPresentation;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

final class BookingCancellationService
{
    private const TERMINAL_UNPAID_PAYMENT_STATUSES = [
        Payment::STATUS_FAILED,
        Payment::STATUS_EXPIRED,
    ];

    public function __construct(
        private readonly BookingSeatLockService $seatLocks,
        private readonly BookingFoodService $food,
        private readonly ActivityLogger $activities,
    ) {}

    public function isCancellable(Booking $booking): bool
    {
        if (! $this->hasCancellableBookingState($booking)) {
            return false;
        }

        $paymentStatuses = $booking->relationLoaded('payments')
            ? $booking->payments->pluck('status')
            : $booking->payments()->pluck('status');

        return $this->onlyHasTerminalUnpaidPayments($paymentStatuses);
    }

    public function cancel(int $bookingId): BookingCancellationResult
    {
        return DB::transaction(function () use ($bookingId): BookingCancellationResult {
            $booking = Booking::query()->lockForUpdate()->findOrFail($bookingId);

            if ($booking->booking_status === 'cancelled') {
                return BookingCancellationResult::alreadyCancelled();
            }

            $paymentStatuses = Payment::query()
                ->where('booking_id', $booking->id)
                ->orderBy('id')
                ->lockForUpdate()
                ->pluck('status');

            if (! $this->hasCancellableBookingState($booking)
                || ! $this->onlyHasTerminalUnpaidPayments($paymentStatuses)) {
                return BookingCancellationResult::notCancellable();
            }

            $lockedSeatRows = BookingSeat::query()
                ->where('booking_id', $booking->id)
                ->where('active_lock_key', BookingSeat::ACTIVE_LOCK_KEY)
                ->orderBy('id')
                ->lockForUpdate()
                ->with('seat:id,seat_code,type,pair_code,pair_position,row,number,x_position,y_position')
                ->get();
            $releasedSeatLabels = $this->activeSeatLabels($lockedSeatRows);

            $booking->forceFill(['booking_status' => 'cancelled'])->save();
            $released = $this->seatLocks->release($booking);
            $this->food->transitionForBooking($booking, 'cancelled');

            // One audit event per successful cancellation. Seat labels are logical and safe;
            // no capability, token or provider payload is ever recorded here.
            $this->activities->log(
                'booking.cancelled',
                $booking,
                ['status' => 'pending_payment'],
                ['status' => 'cancelled'],
                [
                    'booking_code' => $booking->booking_code,
                    'showtime_id' => $booking->showtime_id,
                    'seat_units' => $releasedSeatLabels,
                    'seat_count' => $released,
                    'reason' => 'customer_cancelled_unpaid',
                ],
            );

            return BookingCancellationResult::cancelled();
        });
    }

    /** @return list<string> */
    private function activeSeatLabels(Collection $bookingSeats): array
    {
        return SeatPresentation::groups($bookingSeats->pluck('seat')->filter()->values())
            ->pluck('label')
            ->filter(fn ($label): bool => is_string($label) && $label !== '')
            ->take(50)
            ->values()
            ->all();
    }

    private function hasCancellableBookingState(Booking $booking): bool
    {
        return $booking->booking_status === 'pending_payment'
            && $booking->payment_status === 'unpaid'
            && $booking->expires_at?->isFuture() === true;
    }

    /** @param Collection<int, string> $paymentStatuses */
    private function onlyHasTerminalUnpaidPayments(Collection $paymentStatuses): bool
    {
        return $paymentStatuses->every(
            fn (string $status): bool => in_array($status, self::TERMINAL_UNPAID_PAYMENT_STATUSES, true),
        );
    }
}
