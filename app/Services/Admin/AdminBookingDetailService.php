<?php

namespace App\Services\Admin;

use App\Models\ActivityLog;
use App\Models\Booking;
use App\Models\BookingTicketPrintEvent;
use App\Models\Payment;
use App\Models\SeatIncidentImpact;
use App\Services\BookingCancellationService;
use App\Services\Tickets\BookingTicketEligibility;
use App\Services\Tickets\TicketDeliveryRetryService;
use App\Support\PrivacyMask;
use App\Support\SeatPresentation;

final class AdminBookingDetailService
{
    public function __construct(
        private readonly BookingCancellationService $cancellations,
        private readonly BookingTicketEligibility $ticketEligibility,
        private readonly TicketDeliveryRetryService $deliveryRetries,
    ) {}

    public function get(Booking $booking, bool $includeActivity): array
    {
        $booking->load([
            'user:id,name,email',
            'createdByStaff:id,name,email',
            'showtime.movie',
            'showtime.cinema',
            'showtime.room.cinema',
            'showtime.roomLayout:id,room_id,version,status',
            'bookingSeats.seat',
            'foodOrder.items.food:id,name',
            'promotionUsage',
            'ticketDelivery',
            'ticketPrint.printedBy:id,name',
            'ticketPrint.lastFailedBy:id,name',
        ]);

        $payments = $booking->payments()->with('settledBy:id,name,email')->latest('id')->limit(100)->get();
        $booking->setRelation('payments', $payments);
        $authoritativePayment = $payments
            ->filter(fn (Payment $payment): bool => $payment->hasAuthoritativeSuccessEvidence())
            ->sortByDesc('id')
            ->first();
        $seatGroups = SeatPresentation::groups($booking->bookingSeats->pluck('seat')->filter()->values())
            ->map(function (array $group) use ($booking): array {
                $prices = $booking->bookingSeats->whereIn('seat_id', $group['seat_ids']);

                return $group + ['price' => $prices->sum(fn ($seat): int => (int) $seat->price)];
            });

        $activities = $includeActivity
            ? ActivityLog::query()->with('actor:id,name')->where('subject_type', $booking->getMorphClass())
                ->where('subject_id', (string) $booking->getKey())->latest('id')->limit(100)->get()
            : collect();

        $printState = $booking->ticketPrint;
        $printEvents = $printState
            ? BookingTicketPrintEvent::query()->with('actor:id,name')
                ->where('booking_id', $booking->id)->latest('id')->get()
            : collect();
        $latestPrintEvent = $printEvents->firstWhere('event_type', 'print_started');
        $latestReprintEvent = $printEvents->firstWhere('event_type', 'reprint_requested');
        $incidentImpacts = SeatIncidentImpact::query()
            ->whereHas('bookingSeat', fn ($query) => $query->where('booking_id', $booking->id))
            ->with([
                'bookingSeat:id,booking_id,seat_id',
                'bookingSeat.seat:id,seat_code',
                'incident:id,room_id,status,reason,created_at',
                'resolution:id,seat_incident_impact_id,resolution_type,original_seat_id,replacement_seat_id,reprint_required,reprint_satisfied_at',
                'resolution.originalSeat:id,seat_code',
                'resolution.replacementSeat:id,seat_code',
            ])
            ->latest('id')
            ->limit(50)
            ->get();

        return [
            'booking' => $booking,
            'customer' => [
                'name' => $booking->user?->name ?? $booking->customer_name ?? 'Khách đặt vé',
                'email' => PrivacyMask::email($booking->recipient_email),
                'phone' => PrivacyMask::phone($booking->customer_phone),
                'kind' => $booking->user_id ? 'Tài khoản MovieMate' : ($booking->sales_channel === Booking::SALES_CHANNEL_COUNTER ? 'Khách tại quầy' : 'Khách đặt vé'),
            ],
            'seatGroups' => $seatGroups,
            'payments' => $payments,
            'authoritativePayment' => $authoritativePayment,
            'paymentCategories' => $payments->mapWithKeys(fn (Payment $payment): array => [
                $payment->id => $this->paymentCategory($payment),
            ]),
            'activities' => $activities,
            'includeActivity' => $includeActivity,
            'cancellable' => $this->cancellations->isCancellable($booking),
            'ticketEligible' => $this->ticketEligibility->isUsable($booking),
            'deliveryRetryAllowed' => $booking->ticketDelivery?->status === 'failed'
                && $this->ticketEligibility->isDeliverable($booking)
                && ! $this->deliveryRetries->hasActiveClaim($booking->ticketDelivery),
            'printState' => $printState,
            'printEvents' => $printEvents,
            'latestPrintEvent' => $latestPrintEvent,
            'latestReprintEvent' => $latestReprintEvent,
            'incidentImpacts' => $incidentImpacts,
        ];
    }

    private function paymentCategory(Payment $payment): string
    {
        return match ($payment->status) {
            Payment::STATUS_SUCCESS => $payment->provider === Payment::PROVIDER_COUNTER_CASH && $payment->hasAuthoritativeSuccessEvidence()
                ? 'Đã thu tiền mặt tại quầy'
                : ($payment->verified_at ? 'Đã xác minh từ nhà cung cấp' : 'Thiếu dấu xác minh'),
            Payment::STATUS_PENDING, Payment::STATUS_PROCESSING => 'Đang chờ nhà cung cấp',
            Payment::STATUS_UNRESOLVED => 'Chưa có kết quả chắc chắn',
            Payment::STATUS_REVIEW => 'Cần đối soát',
            Payment::STATUS_EXPIRED => 'Hết thời gian thanh toán',
            default => 'Không thành công',
        };
    }
}
