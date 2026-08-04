<?php

namespace App\Console\Commands;

use App\Models\Booking;
use App\Models\BookingTicketDelivery;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class RecoverTicketDelivery extends Command
{
    protected $signature = 'payments:recover-ticket-delivery
        {payment : Successful payment ID}
        {--actor= : Active operator user ID with bookings.operate permission}';

    protected $description = 'Explicitly create a missing ticket-delivery outbox row for a verified paid booking';

    public function handle(): int
    {
        $actorId = filter_var($this->option('actor'), FILTER_VALIDATE_INT, [
            'options' => ['min_range' => 1],
        ]);
        $actor = $actorId
            ? User::query()->with('role.permissions')->find($actorId)
            : null;

        if (! $actor
            || $actor->status !== 'active'
            || ! $actor->role?->hasPermission('bookings.operate')) {
            $this->error('An active operator user ID with bookings.operate permission is required.');

            return self::FAILURE;
        }

        $paymentId = filter_var($this->argument('payment'), FILTER_VALIDATE_INT, [
            'options' => ['min_range' => 1],
        ]);
        $payment = $paymentId ? Payment::query()->find($paymentId) : null;

        if (! $payment) {
            $this->error('The payment was not found.');

            return self::FAILURE;
        }

        $delivery = DB::transaction(function () use ($payment): ?BookingTicketDelivery {
            $booking = Booking::query()->lockForUpdate()->findOrFail($payment->booking_id);
            $lockedPayment = Payment::query()->lockForUpdate()->findOrFail($payment->id);

            if ($lockedPayment->status !== Payment::STATUS_SUCCESS
                || $booking->payment_status !== 'paid'
                || $booking->booking_status !== 'paid') {
                return null;
            }

            return BookingTicketDelivery::query()->firstOrCreate(
                ['booking_id' => $booking->id],
                [
                    'status' => BookingTicketDelivery::STATUS_PENDING,
                    'attempts' => 0,
                    'available_at' => now(),
                ],
            );
        });

        if (! $delivery) {
            $this->error('Recovery requires a successful payment whose booking is still fully paid. No row was written.');

            return self::FAILURE;
        }

        Log::notice('Ticket-delivery recovery was explicitly requested.', [
            'actor_user_id' => $actor->id,
            'payment_id' => $payment->id,
            'booking_id' => $payment->booking_id,
            'ticket_delivery_id' => $delivery->id,
            'created' => $delivery->wasRecentlyCreated,
        ]);

        $this->info($delivery->wasRecentlyCreated
            ? "Created pending ticket delivery {$delivery->id} for booking {$payment->booking_id}."
            : "Ticket delivery {$delivery->id} already exists; no duplicate was created.");

        return self::SUCCESS;
    }
}
