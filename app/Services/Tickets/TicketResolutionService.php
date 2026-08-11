<?php

namespace App\Services\Tickets;

use App\Models\AdmissionTicket;
use App\Models\Booking;
use App\Models\User;
use App\Services\CinemaAccessService;

final class TicketResolutionService
{
    public function __construct(
        private readonly TicketCheckinCapability $capabilities,
        private readonly CinemaAccessService $cinemaAccess,
    ) {}

    public function resolve(string $capability, User $actor): Booking
    {
        return $this->resolveTicket($capability, $actor)->booking;
    }

    public function resolvePublic(string $capability): Booking
    {
        return $this->resolvePublicTicket($capability)->booking;
    }

    public function resolveTicket(string $capability, User $actor): AdmissionTicket
    {
        $ticket = $this->findValidTicket($capability);
        $this->cinemaAccess->authorizeCinema($actor, (int) $ticket->booking->cinema_id);

        return $ticket;
    }

    public function resolvePublicTicket(string $capability): AdmissionTicket
    {
        return $this->findValidTicket($capability);
    }

    public function resolveTicketCode(string $ticketCode, User $actor): AdmissionTicket
    {
        abort_unless(preg_match('/^AT-[A-Z0-9]{26}$/D', $ticketCode) === 1, 404, 'Mã vé không hợp lệ.');
        $ticket = AdmissionTicket::query()->where('ticket_code', $ticketCode)->first();
        abort_unless($ticket, 404, 'Mã vé không hợp lệ.');

        return $this->authorizedTicket($ticket, $actor);
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
        abort_unless(preg_match('/^MMT-[0-9]{4}-[A-F0-9]{16}$/D', $bookingCode) === 1, 404, 'Không tìm thấy mã vé.');
        $booking = Booking::query()->where('booking_code', $bookingCode)->first();
        abort_unless($booking, 404, 'Không tìm thấy mã vé.');

        return $this->authorizedBooking($booking, $actor);
    }

    private function findValidTicket(string $capability): AdmissionTicket
    {
        $ticketId = $this->capabilities->ticketId($capability);
        abort_if($ticketId === null, 404, 'Mã vé không hợp lệ.');

        $ticket = AdmissionTicket::query()->with('booking')->find($ticketId);
        abort_unless($ticket && $this->capabilities->isValid($ticket, $capability), 404, 'Mã vé không hợp lệ.');
        abort_unless($ticket->booking->cinema_id, 404);

        return $this->loadTicket($ticket);
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
            'admissionTickets.acceptedCheckin.actor:id,name',
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
            'acceptedCheckin.actor:id,name',
        ]);
    }
}
