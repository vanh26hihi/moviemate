<?php

namespace Tests\Unit;

use App\Models\Booking;
use App\Models\BookingSeat;
use App\Models\Seat;
use App\Models\Showtime;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Tests\TestCase;

class BookingPresentationTest extends TestCase
{
    public function test_it_does_not_expose_an_external_qr_url_accessor(): void
    {
        $booking = new Booking(['booking_code' => 'MMT 2026/0001']);

        $this->assertFalse(method_exists($booking, 'getQrCodeUrlAttribute'));
    }

    public function test_it_prefers_checkout_email_and_falls_back_to_user_email(): void
    {
        $booking = new Booking(['customer_email' => 'checkout@example.com']);
        $booking->setRelation('user', new User(['email' => 'account@example.com']));

        $this->assertSame('checkout@example.com', $booking->recipient_email);

        $booking->customer_email = null;

        $this->assertSame('account@example.com', $booking->recipient_email);
    }

    public function test_it_formats_showtime_and_seat_codes_for_ticket_views(): void
    {
        $booking = new Booking;
        $booking->setRelation('showtime', new Showtime([
            'show_date' => '2026-08-03',
            'show_time' => '19:30:00',
        ]));

        $booking->setRelation('bookingSeats', new Collection([
            $this->bookingSeatWithCode('A1'),
            $this->bookingSeatWithCode('A2'),
        ]));

        $this->assertSame('03/08/2026 19:30', $booking->showtime_label);
        $this->assertSame('A1, A2', $booking->seat_codes);
    }

    public function test_it_formats_ticket_status_and_total(): void
    {
        $booking = new Booking([
            'booking_status' => 'paid',
            'total_amount' => 180000,
        ]);

        $this->assertSame('Chưa sử dụng', $booking->status_label);
        $this->assertSame('180.000 VNĐ', $booking->formatted_total);
    }

    private function bookingSeatWithCode(string $code): BookingSeat
    {
        $bookingSeat = new BookingSeat;
        $bookingSeat->setRelation('seat', new Seat(['seat_code' => $code]));

        return $bookingSeat;
    }
}
