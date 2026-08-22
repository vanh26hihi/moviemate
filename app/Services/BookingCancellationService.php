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
        private readonly PromotionService $promotions,
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

    public function cancel(
        int $bookingId,
        string $reason = 'customer_cancelled_unpaid',
        string $activity = 'booking.cancelled',
    ): BookingCancellationResult {
        return DB::transaction(function () use ($bookingId, $reason, $activity): BookingCancellationResult {
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

            return $this->transitionLockedBooking(
                $booking,
                $this->lockActiveSeats($booking),
                $reason,
                $activity,
            );
        });
    }

    public function cancelCustomer(int $bookingId): BookingCancellationResult
    {
        return DB::transaction(function () use ($bookingId): BookingCancellationResult {
            $booking = Booking::query()->lockForUpdate()->findOrFail($bookingId);

            if ($booking->booking_status === 'cancelled') {
                return BookingCancellationResult::alreadyCancelled();
            }

            $payments = Payment::query()
                ->where('booking_id', $booking->id)
                ->orderBy('id')
                ->lockForUpdate()
                ->get();
            if (! $this->hasCancellableBookingState($booking)
                || $payments->contains(fn (Payment $payment): bool => $payment->hasAuthoritativeSuccessEvidence())) {
                return BookingCancellationResult::notCancellable();
            }

            $activeAttempts = $payments
                ->filter(fn (Payment $payment): bool => in_array($payment->status, Payment::UNSAFE_RETRY_STATUSES, true))
                ->values();
            if ($activeAttempts->count() > 1) {
                return BookingCancellationResult::notCancellable();
            }

            /** @var Payment|null $activeAttempt */
            $activeAttempt = $activeAttempts->first();
            if ($activeAttempt !== null
                && ($activeAttempt->status !== Payment::STATUS_PENDING
                    || ! in_array($activeAttempt->provider, ['vnpay', 'zalopay'], true))) {
                return BookingCancellationResult::notCancellable();
            }

            $lockedSeatRows = $this->lockActiveSeats($booking);
            if ($activeAttempt !== null) {
                $activeAttempt->forceFill([
                    'status' => Payment::STATUS_FAILED,
                    'failure_reason' => 'customer_cancelled_pending_payment',
                    'failed_at' => now(),
                ])->save();
            }

            return $this->transitionLockedBooking(
                $booking,
                $lockedSeatRows,
                'customer_cancelled_unpaid',
                'booking.cancelled',
                $activeAttempt === null ? [] : [
                    'payment_id' => $activeAttempt->id,
                    'provider' => $activeAttempt->provider,
                    'result' => 'customer_cancelled',
                ],
            );
        });
    }

    public function cancelForSeatIncident(int $bookingId, int $incidentId): BookingCancellationResult
    {
        return DB::transaction(function () use ($bookingId, $incidentId): BookingCancellationResult {
            $booking = Booking::query()->lockForUpdate()->findOrFail($bookingId);

            if ($booking->booking_status === 'cancelled') {
                return BookingCancellationResult::alreadyCancelled();
            }

            $payments = Payment::query()
                ->where('booking_id', $booking->id)
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

            $retained = $payments->contains(fn (Payment $payment): bool => in_array($payment->status, [
                Payment::STATUS_PROCESSING,
                Payment::STATUS_UNRESOLVED,
                Payment::STATUS_REVIEW,
            ], true));
            if ($booking->booking_status !== 'pending_payment'
                || $booking->payment_status !== 'unpaid'
                || $retained
                || $payments->contains(fn (Payment $payment): bool => $payment->hasAuthoritativeSuccessEvidence())) {
                return BookingCancellationResult::notCancellable();
            }

            return $this->transitionLockedBooking(
                $booking,
                $this->lockActiveSeats($booking),
                'seat_incident',
                'booking.cancelled_by_seat_incident',
                ['seat_incident_id' => $incidentId, 'source' => 'seat_incident'],
            );
        }, 3);
    }

    public function cancelVerifiedPayment(
        int $paymentId,
        string $provider,
        string $merchantReference,
        int $amount,
        string $reason,
        array $paymentOutcome = [],
        array $activityContext = [],
    ): BookingCancellationResult {
        $bookingId = Payment::query()->whereKey($paymentId)->value('booking_id');
        if (! is_numeric($bookingId)) {
            return BookingCancellationResult::notCancellable();
        }

        return DB::transaction(function () use (
            $paymentId,
            $provider,
            $merchantReference,
            $amount,
            $reason,
            $paymentOutcome,
            $activityContext,
            $bookingId,
        ): BookingCancellationResult {
            $booking = Booking::query()->lockForUpdate()->findOrFail((int) $bookingId);
            $payments = Payment::query()
                ->where('booking_id', $booking->id)
                ->orderBy('id')
                ->lockForUpdate()
                ->get();
            $payment = $payments->firstWhere('id', $paymentId);

            if (! $payment
                || $payment->provider !== $provider
                || ! hash_equals($payment->order_code, $merchantReference)
                || $payment->amount !== $amount
                || $payments->contains(fn (Payment $candidate): bool => $candidate->hasAuthoritativeSuccessEvidence())
                || $booking->payment_status === 'paid'
                || $booking->booking_status === 'paid') {
                return BookingCancellationResult::notCancellable();
            }

            if ($booking->booking_status === 'cancelled'
                && $payment->status === Payment::STATUS_FAILED
                && $payment->failure_reason === $reason) {
                return BookingCancellationResult::alreadyCancelled();
            }

            $otherPaymentStatuses = $payments
                ->where('id', '!=', $payment->id)
                ->pluck('status');
            $eligiblePayment = in_array($payment->status, Payment::RECONCILABLE_STATUSES, true)
                || ($payment->status === Payment::STATUS_FAILED && $payment->failure_reason === $reason);
            if ($booking->booking_status !== 'pending_payment'
                || $booking->payment_status !== 'unpaid'
                || ! $eligiblePayment
                || ! $this->onlyHasTerminalUnpaidPayments($otherPaymentStatuses)) {
                return BookingCancellationResult::notCancellable();
            }

            $lockedSeatRows = $this->lockActiveSeats($booking);
            $allowedOutcome = array_intersect_key($paymentOutcome, array_flip([
                'response_code',
                'transaction_status',
                'bank_code',
                'card_type',
                'callback_received_at',
                'callback_payload_hash',
            ]));
            $payment->forceFill([
                ...$allowedOutcome,
                'status' => Payment::STATUS_FAILED,
                'failure_reason' => $reason,
                'failed_at' => now(),
            ])->save();

            return $this->transitionLockedBooking(
                $booking,
                $lockedSeatRows,
                $reason,
                'booking.payment_cancelled',
                [
                    ...$activityContext,
                    'payment_id' => $payment->id,
                    'provider' => $payment->provider,
                    'result' => 'customer_cancelled',
                    'cinema_id' => $booking->cinema_id,
                ],
            );
        });
    }

    private function transitionLockedBooking(
        Booking $booking,
        Collection $lockedSeatRows,
        string $reason,
        string $activity,
        array $activityContext = [],
    ): BookingCancellationResult {
        $releasedSeatLabels = $this->activeSeatLabels($lockedSeatRows);

        $booking->forceFill(['booking_status' => 'cancelled'])->save();
        $released = $this->seatLocks->release($booking);
        $this->food->transitionForBooking($booking, 'cancelled');
        $this->promotions->release($booking);

        // One audit event per successful cancellation. Seat labels are logical and safe;
        // no capability, token or provider payload is ever recorded here.
        $this->activities->log(
            $activity,
            $booking,
            ['status' => 'pending_payment'],
            ['status' => 'cancelled'],
            [
                ...$activityContext,
                'booking_id' => $booking->id,
                'booking_code' => $booking->booking_code,
                'showtime_id' => $booking->showtime_id,
                'seat_units' => $releasedSeatLabels,
                'seat_count' => $released,
                'reason' => $reason,
            ],
        );

        return BookingCancellationResult::cancelled();
    }

    /** @return Collection<int, BookingSeat> */
    private function lockActiveSeats(Booking $booking): Collection
    {
        return BookingSeat::query()
            ->where('booking_id', $booking->id)
            ->where('active_lock_key', BookingSeat::ACTIVE_LOCK_KEY)
            ->orderBy('id')
            ->lockForUpdate()
            ->with('seat:id,seat_code,type,pair_code,pair_position,row,number,x_position,y_position')
            ->get();
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
