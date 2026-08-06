<?php

namespace App\Services\Admin;

use App\Models\ActivityLog;
use App\Models\BookingTicketDelivery;
use App\Services\Tickets\BookingTicketEligibility;
use App\Services\Tickets\TicketDeliveryRetryService;
use App\Support\PrivacyMask;
use App\Support\SeatPresentation;

final class AdminTicketDeliveryDetailService
{
    public function __construct(
        private readonly BookingTicketEligibility $eligibility,
        private readonly TicketDeliveryRetryService $retries,
    ) {}

    public function get(BookingTicketDelivery $delivery, bool $includeActivity): array
    {
        $delivery->load([
            'booking.user:id,name,email', 'booking.payments', 'booking.showtime.movie',
            'booking.showtime.room', 'booking.bookingSeats.seat',
        ]);
        $booking = $delivery->booking;
        $activities = $includeActivity
            ? ActivityLog::query()->with('actor:id,name')->where('subject_type', $delivery->getMorphClass())
                ->where('subject_id', (string) $delivery->getKey())->latest('id')->limit(50)->get()
            : collect();

        $eligible = $booking ? $this->eligibility->isDeliverable($booking) : false;
        $retryAllowed = $eligible
            && $delivery->status !== BookingTicketDelivery::STATUS_SENT
            && ! $this->retries->hasActiveClaim($delivery)
            && ! ($delivery->status === BookingTicketDelivery::STATUS_PENDING
                && ($delivery->available_at === null || $delivery->available_at->isPast()));

        return [
            'delivery' => $delivery,
            'booking' => $booking,
            'recipientMasked' => PrivacyMask::email($booking?->recipient_email),
            'seatGroups' => $booking ? SeatPresentation::groups($booking->bookingSeats->pluck('seat')->filter()->values()) : collect(),
            'eligible' => $eligible,
            'retryAllowed' => $retryAllowed,
            'activities' => $activities,
        ];
    }
}
