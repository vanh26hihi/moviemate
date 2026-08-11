<?php

namespace App\Services\Tickets;

use App\Domain\Tickets\TicketCheckinResult;
use App\Models\AdmissionTicket;
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
        $ticketId = $this->capabilities->ticketId($capability);
        if ($ticketId === null) {
            return new TicketCheckinResult(TicketCheckinEvent::RESULT_INVALID_TOKEN, 'Mã vé không hợp lệ hoặc đã bị thay đổi.');
        }

        return DB::transaction(function () use ($ticketId, $capability, $actor): TicketCheckinResult {
            $ticket = AdmissionTicket::query()->lockForUpdate()->find($ticketId);
            if (! $ticket) {
                return new TicketCheckinResult(TicketCheckinEvent::RESULT_INVALID_TOKEN, 'Không tìm thấy vé phù hợp.');
            }

            $booking = Booking::query()->lockForUpdate()->findOrFail($ticket->booking_id);
            $ticket->setRelation('booking', $booking);
            abort_unless($booking->cinema_id, 404);
            $this->cinemaAccess->authorizeCinema($actor, (int) $booking->cinema_id);

            if (! $this->capabilities->isValid($ticket, $capability)) {
                return $this->rejected($ticket, $actor, TicketCheckinEvent::RESULT_INVALID_TOKEN, 'invalid_capability', 'Mã vé không hợp lệ hoặc đã bị thay đổi.');
            }

            if ($ticket->used_at !== null) {
                $event = $this->events->record($ticket, $actor, TicketCheckinEvent::RESULT_ALREADY_USED, 'ticket_already_used');
                $this->activities->log('ticket.checkin_duplicate', $booking, context: [
                    'booking_id' => $booking->id,
                    'admission_ticket_id' => $ticket->id,
                    'ticket_code' => $ticket->ticket_code,
                    'checkin_event_id' => $event->id,
                    'checkin_result' => TicketCheckinEvent::RESULT_ALREADY_USED,
                ]);

                return new TicketCheckinResult(TicketCheckinEvent::RESULT_ALREADY_USED, 'Vé đã được sử dụng.', $booking, $event, $ticket);
            }

            if ($booking->booking_status === 'cancelled') {
                return $this->rejected($ticket, $actor, TicketCheckinEvent::RESULT_CANCELLED, 'booking_cancelled', 'Vé thuộc đơn đã hủy.');
            }
            if ($booking->booking_status === 'expired') {
                return $this->rejected($ticket, $actor, TicketCheckinEvent::RESULT_EXPIRED, 'booking_expired', 'Vé đã hết hạn.');
            }

            $verifiedPayment = Payment::query()
                ->where('booking_id', $booking->id)
                ->where('status', Payment::STATUS_SUCCESS)
                ->where(function ($query): void {
                    $query->whereNotNull('verified_at')
                        ->orWhere(function ($counter): void {
                            $counter->where('provider', Payment::PROVIDER_COUNTER_CASH)
                                ->whereNotNull('settled_at')
                                ->whereNotNull('settled_by_user_id');
                        });
                })
                ->latest('id')
                ->lockForUpdate()
                ->first();
            if ($booking->payment_status !== 'paid'
                || ! in_array($booking->booking_status, ['paid', 'used'], true)
                || ! $verifiedPayment) {
                return $this->rejected($ticket, $actor, TicketCheckinEvent::RESULT_UNPAID, 'booking_not_paid', 'Vé chưa có thanh toán được xác minh.');
            }

            $usedAt = now();
            $ticket->forceFill(['used_at' => $usedAt, 'used_by_user_id' => $actor->id])->save();
            if ($booking->booking_status !== 'used') {
                $booking->forceFill(['booking_status' => 'used', 'used_at' => $usedAt])->save();
            }
            $event = $this->events->record($ticket, $actor, TicketCheckinEvent::RESULT_ACCEPTED, 'verified_paid_ticket');
            $this->activities->log('ticket.checkin_accepted', $booking, ['status' => 'paid'], ['status' => 'used'], [
                'booking_id' => $booking->id,
                'admission_ticket_id' => $ticket->id,
                'ticket_code' => $ticket->ticket_code,
                'checkin_event_id' => $event->id,
                'checkin_result' => TicketCheckinEvent::RESULT_ACCEPTED,
            ]);

            return new TicketCheckinResult(TicketCheckinEvent::RESULT_ACCEPTED, 'Xác nhận cho vào thành công.', $booking, $event, $ticket);
        }, 3);
    }

    private function rejected(AdmissionTicket $ticket, User $actor, string $result, string $reason, string $message): TicketCheckinResult
    {
        $event = $this->events->record($ticket, $actor, $result, $reason);

        return new TicketCheckinResult($result, $message, $ticket->booking, $event, $ticket);
    }
}
