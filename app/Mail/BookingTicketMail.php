<?php

namespace App\Mail;

use App\Models\Booking;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class BookingTicketMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Booking $booking,
        public string $ticketAccessUrl,
        public string $ticketQrPng,
    ) {}

    public function build(): self
    {
        $this->booking->loadMissing([
            'user',
            'showtime.movie',
            'showtime.cinema',
            'showtime.room',
            'bookingSeats.seat',
            'payments',
            'foodOrder.items',
        ]);

        return $this
            ->subject('Vé điện tử MovieMate - '.$this->booking->booking_code)
            ->view('emails.booking-ticket');
    }
}
