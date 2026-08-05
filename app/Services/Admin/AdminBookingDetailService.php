<?php

namespace App\Services\Admin;

use App\Models\ActivityLog;
use App\Models\Booking;
use App\Models\Payment;
use App\Services\BookingCancellationService;
use App\Services\Tickets\BookingTicketEligibility;
use App\Support\PrivacyMask;
use App\Support\SeatPresentation;

final class AdminBookingDetailService
{
    public function __construct(
        private readonly BookingCancellationService $cancellations,
        private readonly BookingTicketEligibility $ticketEligibility,
    ) {}

    public function get(Booking $booking, bool $includeActivity): array
    {
        $booking->load([
            'user:id,name,email',
            'showtime.movie',
            'showtime.cinema',
            'showtime.room.cinema',
            'showtime.roomLayout:id,room_id,version,status',
            'bookingSeats.seat',
            'foodOrder.items.food:id,name',
            'ticketDelivery',
        ]);

        $payments = $booking->payments()->latest('id')->limit(100)->get();
        $booking->setRelation('payments', $payments);
        $authoritativePayment = $payments
            ->where('status', Payment::STATUS_SUCCESS)
            ->filter(fn (Payment $payment): bool => $payment->verified_at !== null)
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

        return [
            'booking' => $booking,
            'customer' => [
                'name' => $booking->user?->name ?? 'Khách đặt vé',
                'email' => PrivacyMask::email($booking->recipient_email),
                'kind' => $booking->user_id ? 'Tài khoản MovieMate' : 'Khách đặt vé',
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
            'ticketEligible' => $this->ticketEligibility->isPrintable($booking),
        ];
    }

    private function paymentCategory(Payment $payment): string
    {
        return match ($payment->status) {
            Payment::STATUS_SUCCESS => $payment->verified_at ? 'Đã xác minh từ nhà cung cấp' : 'Thiếu dấu xác minh',
            Payment::STATUS_PENDING, Payment::STATUS_PROCESSING => 'Đang chờ nhà cung cấp',
            Payment::STATUS_UNRESOLVED => 'Chưa có kết quả chắc chắn',
            Payment::STATUS_REVIEW => 'Cần đối soát',
            Payment::STATUS_EXPIRED => 'Hết thời gian thanh toán',
            default => 'Không thành công',
        };
    }
}
