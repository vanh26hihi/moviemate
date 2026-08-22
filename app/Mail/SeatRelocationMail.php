<?php

namespace App\Mail;

use App\Models\Booking;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

final class SeatRelocationMail extends Mailable
{
    use Queueable, SerializesModels;

    /** @param list<array{original:string,replacement:string,reprint_required:bool}> $relocations */
    public function __construct(public Booking $booking, public array $relocations) {}

    public function build(): self
    {
        $this->booking->loadMissing(['showtime.movie', 'showtime.cinema', 'showtime.room']);

        return $this->subject('MovieMate - Cập nhật ghế cho đơn '.$this->booking->booking_code)
            ->view('emails.seat-relocation');
    }
}
