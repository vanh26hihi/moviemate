<?php

namespace App\Services\Admin;

use App\Models\ActivityLog;
use App\Models\TicketCheckinEvent;
use App\Support\PrivacyMask;
use App\Support\SeatPresentation;

final class AdminTicketCheckinDetailService
{
    public function get(TicketCheckinEvent $event, bool $includeActivity): array
    {
        $event->load([
            'actor:id,name', 'booking.user:id,name,email', 'booking.bookingSeats.seat',
            'showtime.movie', 'showtime.room',
        ]);
        $activity = $includeActivity
            ? ActivityLog::query()->with('actor:id,name')->where('request_id', $event->request_id)
                ->latest('id')->limit(20)->get()
            : collect();

        return [
            'event' => $event,
            'booking' => $event->booking,
            'customerMasked' => PrivacyMask::name($event->booking?->user?->name).' · '.PrivacyMask::email($event->booking?->recipient_email),
            'seatGroups' => $event->booking
                ? SeatPresentation::groups($event->booking->bookingSeats->pluck('seat')->filter()->values())
                : collect(),
            'activity' => $activity,
        ];
    }
}
