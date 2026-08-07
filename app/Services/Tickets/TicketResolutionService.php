<?php

namespace App\Services\Tickets;

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
        $bookingId = $this->capabilities->bookingId($capability);
        abort_if($bookingId === null, 404, 'Mã vé không hợp lệ.');

        $booking = Booking::query()->find($bookingId);
        abort_unless($booking && $this->capabilities->isValid($booking, $capability), 404, 'Mã vé không hợp lệ.');
        abort_unless($booking->cinema_id, 404);
        $this->cinemaAccess->authorizeCinema($actor, (int) $booking->cinema_id);

        return $this->load($booking);
    }

    public function resolvePublic(string $capability): Booking
    {
        $bookingId = $this->capabilities->bookingId($capability);
        abort_if($bookingId === null, 404, 'Mã vé không hợp lệ.');

        $booking = Booking::query()->find($bookingId);
        abort_unless($booking && $this->capabilities->isValid($booking, $capability), 404, 'Mã vé không hợp lệ.');

        return $this->load($booking);
    }

    public function authorizedBooking(Booking $booking, User $actor): Booking
    {
        abort_unless($booking->cinema_id, 404);
        $this->cinemaAccess->authorizeCinema($actor, (int) $booking->cinema_id);

        return $this->load($booking);
    }

    public function resolveBookingCode(string $bookingCode, User $actor): Booking
    {
        abort_unless(preg_match('/^MMT-[0-9]{4}-[A-F0-9]{16}$/D', $bookingCode) === 1, 404, 'Không tìm thấy mã vé.');
        $booking = Booking::query()->where('booking_code', $bookingCode)->first();
        abort_unless($booking, 404, 'Không tìm thấy mã vé.');

        return $this->authorizedBooking($booking, $actor);
    }

    private function load(Booking $booking): Booking
    {
        return $booking->load([
            'user:id,name,email',
            'payments',
            'showtime.movie',
            'showtime.cinema',
            'showtime.room',
            'bookingSeats.seat',
            'ticketDelivery',
            'ticketPrint.printedBy:id,name',
            'acceptedTicketCheckin.actor:id,name',
            'foodOrder.items',
        ]);
    }
}
