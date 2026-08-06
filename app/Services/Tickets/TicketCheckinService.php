<?php

namespace App\Services\Tickets;

use App\Domain\Tickets\TicketCheckinResult;
use App\Models\Booking;
use App\Models\Payment;
use App\Models\TicketCheckinEvent;
use App\Models\User;
use App\Services\ActivityLogger;
use App\Services\CinemaAccessService;
use Illuminate\Support\Facades\DB;

final class TicketCheckinService
{
    public function __construct(
        private readonly TicketCheckinCapability $capabilities,
        private readonly TicketCheckinRecorder $events,
        private readonly ActivityLogger $activities,
        private readonly CinemaAccessService $cinemaAccess,
    ) {}

    public function checkIn(string $capability, User $actor): TicketCheckinResult
    {
        $bookingId = $this->capabilities->bookingId($capability);
        if ($bookingId === null) {
            return new TicketCheckinResult(TicketCheckinEvent::RESULT_INVALID_TOKEN, 'Mã vé không hợp lệ hoặc đã bị thay đổi.');
        }

        return DB::transaction(function () use ($bookingId, $capability, $actor): TicketCheckinResult {
            $booking = Booking::query()->lockForUpdate()->find($bookingId);
            if (! $booking) {
                return new TicketCheckinResult(TicketCheckinEvent::RESULT_INVALID_TOKEN, 'Không tìm thấy vé phù hợp.');
            }
            abort_unless($booking->cinema_id && $this->cinemaAccess->canAccessCinema($actor, (int) $booking->cinema_id), 404);

            if (! $this->capabilities->isValid($booking, $capability)) {
                return $this->rejected($booking, $actor, TicketCheckinEvent::RESULT_INVALID_TOKEN, 'invalid_capability', 'Mã vé không hợp lệ hoặc đã bị thay đổi.');
            }

            if ($booking->booking_status === 'used') {
                $event = $this->events->record($booking, $actor, TicketCheckinEvent::RESULT_ALREADY_USED, 'booking_already_used');
                $this->activities->log('ticket.checkin_duplicate', $booking, context: [
                    'booking_id' => $booking->id,
                    'booking_code' => $booking->booking_code,
                    'checkin_event_id' => $event->id,
                    'checkin_result' => TicketCheckinEvent::RESULT_ALREADY_USED,
                ]);

                return new TicketCheckinResult(TicketCheckinEvent::RESULT_ALREADY_USED, 'Vé đã được sử dụng trước đó.', $booking, $event);
            }

            if ($booking->booking_status === 'cancelled') {
                return $this->rejected($booking, $actor, TicketCheckinEvent::RESULT_CANCELLED, 'booking_cancelled', 'Vé thuộc đơn đã hủy.');
            }
            if ($booking->booking_status === 'expired') {
                return $this->rejected($booking, $actor, TicketCheckinEvent::RESULT_EXPIRED, 'booking_expired', 'Vé đã hết hạn.');
            }

            $verifiedPayment = Payment::query()
                ->where('booking_id', $booking->id)
                ->where('status', Payment::STATUS_SUCCESS)
                ->whereNotNull('verified_at')
                ->latest('id')
                ->lockForUpdate()
                ->first();
            if ($booking->payment_status !== 'paid' || $booking->booking_status !== 'paid' || ! $verifiedPayment) {
                return $this->rejected($booking, $actor, TicketCheckinEvent::RESULT_UNPAID, 'booking_not_paid', 'Vé chưa có thanh toán được xác minh.');
            }

            $booking->forceFill(['booking_status' => 'used', 'used_at' => now()])->save();
            $event = $this->events->record($booking, $actor, TicketCheckinEvent::RESULT_ACCEPTED, 'verified_paid_ticket');
            $this->activities->log('ticket.checkin_accepted', $booking, [
                'status' => 'paid',
            ], [
                'status' => 'used',
            ], [
                'booking_id' => $booking->id,
                'booking_code' => $booking->booking_code,
                'checkin_event_id' => $event->id,
                'checkin_result' => TicketCheckinEvent::RESULT_ACCEPTED,
            ]);

            return new TicketCheckinResult(TicketCheckinEvent::RESULT_ACCEPTED, 'Soát vé thành công.', $booking, $event);
        });
    }

    private function rejected(Booking $booking, User $actor, string $result, string $reason, string $message): TicketCheckinResult
    {
        $event = $this->events->record($booking, $actor, $result, $reason);

        return new TicketCheckinResult($result, $message, $booking, $event);
    }
}
