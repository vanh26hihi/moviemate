<?php

namespace App\Services\Tickets;

use App\Jobs\Payments\SendBookingTicket;
use App\Models\Booking;
use App\Models\BookingTicketDelivery;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use RuntimeException;

final class TicketDeliveryOutbox
{
    public function __construct(private readonly BookingTicketEligibility $eligibility) {}

    public function enqueueVerifiedBooking(Booking $booking): BookingTicketDelivery
    {
        if (! $this->eligibility->isDeliverable($booking)) {
            throw new RuntimeException('ticket_booking_not_eligible');
        }

        $delivery = BookingTicketDelivery::query()->firstOrCreate(
            ['booking_id' => $booking->getKey()],
            [
                'status' => BookingTicketDelivery::STATUS_PENDING,
                'attempts' => 0,
                'available_at' => now(),
            ],
        );

        SendBookingTicket::dispatchAfterResponse($booking->getKey());

        return $delivery;
    }

    public function requestResend(Booking $booking, ?int $actorUserId): BookingTicketDelivery
    {
        $delivery = DB::transaction(function () use ($booking, $actorUserId): BookingTicketDelivery {
            $lockedBooking = Booking::query()->lockForUpdate()->findOrFail($booking->getKey());
            if (! $this->eligibility->isDeliverable($lockedBooking)) {
                throw new RuntimeException('ticket_booking_not_eligible');
            }

            $delivery = BookingTicketDelivery::query()
                ->where('booking_id', $lockedBooking->getKey())
                ->lockForUpdate()
                ->first();

            if ($delivery === null) {
                $delivery = BookingTicketDelivery::query()->create([
                    'booking_id' => $lockedBooking->getKey(),
                    'status' => BookingTicketDelivery::STATUS_PENDING,
                    'attempts' => 0,
                    'available_at' => now(),
                ]);
            } elseif ($delivery->status !== BookingTicketDelivery::STATUS_PROCESSING
                || $delivery->lease_expires_at === null
                || $delivery->lease_expires_at?->isPast()) {
                $delivery->forceFill([
                    'status' => BookingTicketDelivery::STATUS_PENDING,
                    'available_at' => now(),
                    'processing_started_at' => null,
                    'lease_expires_at' => null,
                    'sent_at' => null,
                    'last_error_code' => null,
                ])->save();
            }

            Log::notice('Ticket email resend was queued through the durable outbox.', [
                'booking_id' => $lockedBooking->getKey(),
                'ticket_delivery_id' => $delivery->getKey(),
                'actor_user_id' => $actorUserId,
                'already_processing' => $delivery->status === BookingTicketDelivery::STATUS_PROCESSING,
            ]);

            return $delivery;
        });

        SendBookingTicket::dispatchAfterResponse($booking->getKey());

        return $delivery;
    }
}
