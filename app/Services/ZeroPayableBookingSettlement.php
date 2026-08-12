<?php

namespace App\Services;

use App\Exceptions\PaymentInitiationException;
use App\Models\Booking;
use App\Models\BookingSeat;
use App\Models\Order;
use App\Models\Payment;
use App\Services\Tickets\TicketDeliveryOutbox;
use Illuminate\Support\Facades\DB;

final class ZeroPayableBookingSettlement
{
    public function __construct(
        private readonly PromotionService $promotions,
        private readonly TicketDeliveryOutbox $ticketDeliveries,
        private readonly ActivityLogger $activities,
    ) {}

    public function settle(Booking $booking): Payment
    {
        return DB::transaction(function () use ($booking): Payment {
            $lockedBooking = Booking::query()->lockForUpdate()->findOrFail($booking->id);
            $gross = (int) $lockedBooking->gross_amount;
            $discount = (int) $lockedBooking->promotion_discount_amount;

            if ((int) $lockedBooking->total_amount !== 0 || $gross <= 0 || $discount !== $gross) {
                throw new PaymentInitiationException('Booking is not eligible for zero-payable settlement.');
            }

            $existing = Payment::query()
                ->where('booking_id', $lockedBooking->id)
                ->where('provider', Payment::PROVIDER_INTERNAL_ZERO)
                ->lockForUpdate()
                ->first();
            if ($existing?->status === Payment::STATUS_SUCCESS) {
                $booking->refresh();

                return $existing;
            }

            if ($lockedBooking->payment_status !== 'unpaid'
                || $lockedBooking->booking_status !== 'pending_payment'
                || ! $lockedBooking->expires_at
                || $lockedBooking->expires_at->isPast()) {
                throw new PaymentInitiationException('Zero-payable booking is no longer settleable.');
            }

            $ownedSeats = BookingSeat::query()->where('booking_id', $lockedBooking->id)
                ->orderBy('id')->lockForUpdate()->get();
            if ($ownedSeats->isEmpty()
                || $ownedSeats->contains(fn (BookingSeat $seat): bool => $seat->active_lock_key !== BookingSeat::ACTIVE_LOCK_KEY)) {
                throw new PaymentInitiationException('Zero-payable booking no longer owns its seats.');
            }

            $now = now();
            $payment = new Payment;
            $payment->forceFill([
                'booking_id' => $lockedBooking->id,
                'provider' => Payment::PROVIDER_INTERNAL_ZERO,
                'payment_method' => Payment::PROVIDER_INTERNAL_ZERO,
                'order_code' => 'ZERO-'.$lockedBooking->booking_code,
                'transaction_code' => 'ZERO-'.$lockedBooking->booking_code,
                'amount' => 0,
                'currency' => $lockedBooking->currency ?: 'VND',
                'status' => Payment::STATUS_SUCCESS,
                'description' => 'Internal zero-payable promotion settlement',
                'verified_at' => $now,
                'paid_at' => $now,
            ])->save();

            $lockedBooking->forceFill([
                'payment_method' => Payment::PROVIDER_INTERNAL_ZERO,
                'payment_status' => 'paid',
                'booking_status' => 'paid',
                'paid_at' => $now,
                'expires_at' => null,
            ])->save();
            Order::query()->where('booking_id', $lockedBooking->id)->where('status', 'pending')
                ->lockForUpdate()->update(['status' => 'paid']);
            $this->promotions->redeem($lockedBooking);
            $this->ticketDeliveries->enqueueVerifiedBooking($lockedBooking);
            $this->activities->log('payment.zero_payable_settled', $payment, context: [
                'payment_id' => $payment->id,
                'booking_id' => $lockedBooking->id,
                'cinema_id' => $lockedBooking->cinema_id,
                'provider' => Payment::PROVIDER_INTERNAL_ZERO,
                'result' => 'internal_zero_payable_success',
            ]);
            $booking->refresh();

            return $payment;
        }, 3);
    }
}
