<?php

namespace App\Services\Counter;

use App\Models\Booking;
use App\Models\BookingSeat;
use App\Models\Order;
use App\Models\Payment;
use App\Models\User;
use App\Services\ActivityLogger;
use App\Services\CinemaAccessService;
use App\Services\Tickets\TicketDeliveryOutbox;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class CounterCashPaymentService
{
    public function __construct(
        private readonly CinemaAccessService $cinemas,
        private readonly TicketDeliveryOutbox $ticketDeliveries,
        private readonly ActivityLogger $activities,
    ) {}

    public function settle(Booking $booking, User $actor): Payment
    {
        abort_unless($actor->isActive() && $actor->hasPermission('counter_sales.settle'), 403);
        abort_unless($booking->sales_channel === Booking::SALES_CHANNEL_COUNTER, 404);
        $this->cinemas->authorizeCinema($actor, (int) $booking->cinema_id);

        return DB::transaction(function () use ($booking, $actor): Payment {
            $locked = Booking::query()->lockForUpdate()->findOrFail($booking->id);
            abort_unless($locked->sales_channel === Booking::SALES_CHANNEL_COUNTER, 404);
            $this->cinemas->authorizeCinema($actor, (int) $locked->cinema_id);

            $payments = Payment::query()->where('booking_id', $locked->id)
                ->orderBy('id')->lockForUpdate()->get();
            $existing = $payments->first(fn (Payment $payment): bool => $payment->provider === Payment::PROVIDER_COUNTER_CASH
                && $payment->hasAuthoritativeSuccessEvidence()
            );
            if ($existing && $locked->booking_status === 'paid' && $locked->payment_status === 'paid') {
                return $existing;
            }

            if ($locked->booking_status !== 'pending_payment'
                || $locked->payment_status !== 'unpaid'
                || ! $locked->expires_at?->isFuture()) {
                throw ValidationException::withMessages(['booking' => 'Đơn tại quầy không còn đủ điều kiện thu tiền.']);
            }
            if ($payments->isNotEmpty()) {
                throw ValidationException::withMessages(['booking' => 'Đơn có lịch sử thanh toán khác và không thể thu tiền mặt tại quầy.']);
            }

            $seatRows = BookingSeat::query()->where('booking_id', $locked->id)
                ->orderBy('id')->lockForUpdate()->get();
            if ($seatRows->isEmpty() || $seatRows->contains(fn (BookingSeat $seat): bool => (int) $seat->showtime_id !== (int) $locked->showtime_id
                || $seat->active_lock_key !== BookingSeat::ACTIVE_LOCK_KEY
            )) {
                throw ValidationException::withMessages(['booking' => 'Đơn không còn sở hữu đầy đủ ghế đã giữ.']);
            }

            $amount = (int) $locked->total_amount;
            if ($amount < 0 || $amount !== (int) $locked->seat_subtotal + (int) $locked->food_subtotal) {
                throw ValidationException::withMessages(['booking' => 'Tổng tiền lưu trên đơn không hợp lệ.']);
            }

            $now = now();
            $payment = new Payment;
            $payment->forceFill([
                'booking_id' => $locked->id,
                'provider' => Payment::PROVIDER_COUNTER_CASH,
                'payment_method' => Payment::PROVIDER_COUNTER_CASH,
                'amount' => $amount,
                'currency' => $locked->currency,
                'status' => Payment::STATUS_SUCCESS,
                'verified_at' => null,
                'settled_by_user_id' => $actor->id,
                'settled_at' => $now,
                'paid_at' => $now,
                'description' => 'Thanh toán tiền mặt tại quầy',
            ]);
            $payment->save();
            $payment->forceFill(['transaction_code' => 'COUNTER-'.$payment->id])->save();

            $locked->forceFill([
                'payment_method' => Payment::PROVIDER_COUNTER_CASH,
                'payment_status' => 'paid',
                'booking_status' => 'paid',
                'paid_at' => $now,
            ])->save();
            Order::query()->where('booking_id', $locked->id)->where('status', 'pending')
                ->lockForUpdate()->update(['status' => 'paid']);

            if ($locked->recipient_email !== null) {
                $this->ticketDeliveries->enqueueVerifiedBooking($locked);
            }
            $this->activities->log(
                'counter.cash_settled',
                $payment,
                ['status' => null],
                ['status' => Payment::STATUS_SUCCESS],
                [
                    'payment_id' => $payment->id,
                    'booking_id' => $locked->id,
                    'booking_code' => $locked->booking_code,
                    'cinema_id' => $locked->cinema_id,
                    'showtime_id' => $locked->showtime_id,
                    'sales_channel' => $locked->sales_channel,
                    'amount' => $amount,
                    'actor_id' => $actor->id,
                ],
            );

            return $payment->load('settledBy:id,name,email');
        });
    }
}
