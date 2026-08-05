<?php

namespace App\Services;

use App\Domain\Bookings\BookingCancellationResult;
use App\Models\Booking;
use App\Models\Payment;
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

            $booking->forceFill(['booking_status' => 'cancelled'])->save();
            $this->seatLocks->release($booking);
            $this->food->transitionForBooking($booking, 'cancelled');

            return BookingCancellationResult::cancelled();
        });
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
