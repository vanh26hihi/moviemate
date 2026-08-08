<?php

namespace Tests\Unit;

use App\Models\Booking;
use App\Models\BookingSeat;
use App\Models\Cinema;
use App\Models\Movie;
use App\Models\Room;
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

    public function test_it_uses_the_checkout_email_snapshot_and_never_follows_account_changes(): void
    {
        $booking = new Booking(['customer_email' => 'checkout@example.com']);
        $booking->setRelation('user', new User(['email' => 'account@example.com']));

        $this->assertSame('checkout@example.com', $booking->recipient_email);

        $booking->customer_email = null;

        $this->assertNull($booking->recipient_email);
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
            'seat_subtotal' => 150000,
            'food_subtotal' => 30000,
            'currency' => 'VND',
        ]);

        $this->assertSame('Chưa sử dụng', $booking->status_label);
        $this->assertSame('VNĐ', $booking->currency_label);
        $this->assertSame('150.000 VNĐ', $booking->formatted_seat_subtotal);
        $this->assertSame('30.000 VNĐ', $booking->formatted_food_subtotal);
        $this->assertSame('180.000 VNĐ', $booking->formatted_total);
    }

    public function test_it_preserves_a_non_vnd_currency_label(): void
    {
        $booking = new Booking([
            'total_amount' => 25,
            'seat_subtotal' => 20,
            'food_subtotal' => 5,
            'currency' => 'usd',
        ]);

        $this->assertSame('USD', $booking->currency_label);
        $this->assertSame('20 USD', $booking->formatted_seat_subtotal);
        $this->assertSame('5 USD', $booking->formatted_food_subtotal);
        $this->assertSame('25 USD', $booking->formatted_total);
    }

    public function test_it_provides_complete_ticket_context_and_safe_fallbacks(): void
    {
        $showtime = new Showtime;
        $showtime->setRelation('movie', new Movie(['title' => 'MovieMate Premiere']));
        $showtime->setRelation('cinema', new Cinema(['name' => 'MovieMate FPT', 'address' => 'Quận 12']));
        $showtime->setRelation('room', new Room(['name' => 'Phòng 01']));

        $booking = new Booking;
        $booking->setRelation('showtime', $showtime);
        $booking->setRelation('bookingSeats', new Collection);

        $this->assertSame('MovieMate Premiere', $booking->movie_title);
        $this->assertSame('MovieMate FPT - Quận 12', $booking->cinema_label);
        $this->assertSame('Phòng 01', $booking->room_label);
        $this->assertSame('Chưa có thông tin ghế', $booking->seat_codes);

        $booking->setRelation('showtime', null);

        $this->assertSame('Phim đang cập nhật', $booking->movie_title);
        $this->assertSame('Rạp đang cập nhật', $booking->cinema_label);
        $this->assertSame('Phòng đang cập nhật', $booking->room_label);
    }

    public function test_it_presents_a_couple_pair_once_with_a_combined_label(): void
    {
        $booking = new Booking;
        $left = $this->bookingSeatWithCode('H13', 'couple', 'H-PAIR-7', 'left', 13);
        $right = $this->bookingSeatWithCode('H14', 'couple', 'H-PAIR-7', 'right', 14);
        $booking->setRelation('bookingSeats', new Collection([$left, $right]));

        $this->assertSame('Ghế đôi H13–H14', $booking->seat_codes);
        $this->assertCount(1, $booking->seat_display_groups);
        $this->assertSame([13, 14], $booking->seat_display_groups->first()['seat_ids']);
    }

    private function bookingSeatWithCode(
        string $code,
        string $type = 'normal',
        ?string $pairCode = null,
        ?string $pairPosition = null,
        ?int $id = null,
    ): BookingSeat {
        $bookingSeat = new BookingSeat;
        $seat = new Seat([
            'seat_code' => $code,
            'row' => preg_replace('/\d+/', '', $code),
            'number' => (int) preg_replace('/\D+/', '', $code),
            'type' => $type,
            'pair_code' => $pairCode,
            'pair_position' => $pairPosition,
        ]);
        if ($id !== null) {
            $seat->setAttribute('id', $id);
        }
        $bookingSeat->setRelation('seat', $seat);

        return $bookingSeat;
    }
}
