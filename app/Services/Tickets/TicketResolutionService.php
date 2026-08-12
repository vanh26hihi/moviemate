<?php

namespace App\Services\Tickets;

use App\Models\AdmissionTicket;
use App\Models\Booking;
use App\Models\User;
use App\Services\CinemaAccessService;

final class TicketResolutionService
{
    public function __construct(
        private readonly BookingLookupCapability $capabilities,
        private readonly CinemaAccessService $cinemaAccess,
    ) {}

    public function resolve(string $capability, User $actor): Booking
    {
        $bookingId = $this->capabilities->bookingId($capability);
        abort_if($bookingId === null, 404, 'Mã đơn không hợp lệ.');
        $booking = Booking::query()->find($bookingId);
        abort_unless($booking && $this->capabilities->isValid($booking, $capability), 404, 'Mã đơn không hợp lệ.');

        return $this->authorizedBooking($booking, $actor);
    }

    public function authorizedBooking(Booking $booking, User $actor): Booking
    {
        abort_unless($booking->cinema_id, 404);
        $this->cinemaAccess->authorizeCinema($actor, (int) $booking->cinema_id);

        return $this->loadBooking($booking);
    }

    public function authorizedTicket(AdmissionTicket $ticket, User $actor): AdmissionTicket
    {
        $ticket = $this->loadTicket($ticket);
        abort_unless($ticket->booking->cinema_id, 404);
        $this->cinemaAccess->authorizeCinema($actor, (int) $ticket->booking->cinema_id);

        return $ticket;
    }

    public function authorizedFirstTicket(Booking $booking, User $actor): AdmissionTicket
    {
        abort_unless($booking->cinema_id, 404);
        $this->cinemaAccess->authorizeCinema($actor, (int) $booking->cinema_id);
        $ticket = AdmissionTicket::query()
            ->with('printState')
            ->where('booking_id', $booking->id)
            ->oldest('id')
            ->first();
        abort_unless($ticket, 409, 'Đơn chưa có vé xem phim đủ điều kiện.');

        return $ticket;
    }

    public function resolveBookingCode(string $bookingCode, User $actor): Booking
    {
        abort_unless(preg_match('/^MMT-[0-9]{4}-[A-F0-9]{16}$/D', $bookingCode) === 1, 404, 'Không tìm thấy mã đơn.');
        $booking = Booking::query()->where('booking_code', $bookingCode)->first();
        abort_unless($booking, 404, 'Không tìm thấy mã đơn.');

        return $this->authorizedBooking($booking, $actor);
    }

    private function loadBooking(Booking $booking): Booking
    {
        return $booking->load([
            'user:id,name,email',
            'payments',
            'showtime.movie',
            'showtime.cinema',
            'showtime.room',
            'bookingSeats.seat',
            'admissionTickets.bookingSeat.seat',
            'admissionTickets.printState.printedBy:id,name',
            'admissionTickets.printState.events.actor:id,name',
            'ticketDelivery',
            'foodOrder.items',
            'foodPickupVoucher.printEvents.actor:id,name',
        ]);
    }

    private function loadTicket(AdmissionTicket $ticket): AdmissionTicket
    {
        return $ticket->load([
            'booking.user:id,name,email',
            'booking.payments',
            'booking.showtime.movie',
            'booking.showtime.cinema',
            'booking.showtime.room',
            'booking.bookingSeats.seat',
            'booking.foodOrder.items',
            'booking.foodPickupVoucher',
            'bookingSeat.seat',
            'printState.printedBy:id,name',
        ]);
    }
}
