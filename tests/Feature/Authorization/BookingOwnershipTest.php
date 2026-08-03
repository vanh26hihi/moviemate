<?php

namespace Tests\Feature\Authorization;

use App\Models\Booking;
use App\Models\Cinema;
use App\Models\Movie;
use App\Models\Room;
use App\Models\Showtime;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BookingOwnershipTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRbac();
    }

    public function test_customer_can_view_own_ticket_but_not_another_customers_ticket(): void
    {
        $owner = $this->userWithRole('user');
        $otherCustomer = $this->userWithRole('user');
        $booking = $this->bookingFor($owner->id);

        $this->actingAs($owner)->get(route('user.bookings.ticket', $booking))->assertOk();
        $this->actingAs($otherCustomer)->get(route('user.bookings.ticket', $booking))->assertForbidden();
    }

    public function test_guest_booking_requires_a_session_capability_from_its_hashed_access_token(): void
    {
        $booking = $this->bookingFor(null);
        $token = str()->random(64);
        $booking->update([
            'guest_access_token_hash' => hash('sha256', $token),
            'guest_access_expires_at' => now()->addHour(),
        ]);

        $this->get(route('user.bookings.ticket', $booking))->assertNotFound();
        $this->get(route('user.bookings.ticket', [
            'booking' => $booking,
            'guest_token' => $token,
        ]))->assertNotFound();
        $this->post(route('user.bookings.access.exchange', $booking), [
            'token' => $token,
            'destination' => 'ticket',
        ])->assertOk();
        $this->get(route('user.bookings.ticket', $booking))->assertOk();
    }

    private function bookingFor(?int $userId): Booking
    {
        $movie = Movie::query()->create(['title' => 'Test Movie', 'slug' => 'test-movie-'.uniqid()]);
        $cinema = Cinema::query()->create(['name' => 'Test Cinema', 'address' => '1 Test St', 'city' => 'Test']);
        $room = Room::query()->create(['cinema_id' => $cinema->id, 'name' => 'Room 1']);
        $showtime = Showtime::query()->create([
            'movie_id' => $movie->id,
            'cinema_id' => $cinema->id,
            'room_id' => $room->id,
            'show_date' => now()->addDay()->toDateString(),
            'show_time' => '19:00:00',
            'price' => 100000,
        ]);

        return Booking::query()->create([
            'user_id' => $userId,
            'showtime_id' => $showtime->id,
            'booking_code' => 'TEST-'.str()->upper(str()->random(8)),
            'total_amount' => 100000,
            'payment_status' => 'paid',
            'booking_status' => 'paid',
        ]);
    }
}
