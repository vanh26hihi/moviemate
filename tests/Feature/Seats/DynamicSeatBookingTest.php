<?php

namespace Tests\Feature\Seats;

use App\Models\Booking;
use App\Models\BookingSeat;
use App\Models\Cinema;
use App\Models\Room;
use App\Models\Seat;
use App\Models\Showtime;
use App\Services\BookingTokenService;
use App\Services\CinemaContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class DynamicSeatBookingTest extends TestCase
{
    use RefreshDatabase;

    private Cinema $cinema;

    private $rooms;

    private Showtime $showtime;

    private string $checkoutToken;

    protected function setUp(): void
    {
        parent::setUp();
        $this->cinema = Cinema::query()->where('canonical_key', CinemaContext::CANONICAL_KEY)->firstOrFail();
        foreach (['P01', 'P02', 'P03'] as $index => $code) {
            Room::query()->create([
                'cinema_id' => $this->cinema->id, 'code' => $code, 'name' => 'Phòng '.($index + 1),
                'room_type' => '2D', 'total_seats' => 0, 'status' => 'active',
            ]);
        }
        $this->artisan('moviemate:rebuild-seat-layouts', ['--force' => true])->assertSuccessful();
        $this->rooms = Room::query()->whereIn('code', ['P01', 'P02', 'P03'])->get()->keyBy('code');
        $movieId = DB::table('movies')->insertGetId([
            'title' => 'Booking Movie', 'slug' => 'booking-movie', 'duration' => 100,
            'age_rating' => 'P', 'status' => 'now_showing', 'created_at' => now(), 'updated_at' => now(),
        ]);
        $room = $this->rooms['P01'];
        $this->showtime = Showtime::query()->create([
            'movie_id' => $movieId, 'cinema_id' => $this->cinema->id, 'room_id' => $room->id,
            'room_layout_id' => $room->latestPublishedLayout()->first()->id,
            'show_date' => now()->addDays(10)->toDateString(), 'show_time' => '10:00:00',
            'price' => 50000, 'vip_price' => 70000, 'status' => 'active',
        ]);
        $this->checkoutToken = app(BookingTokenService::class)->issueCheckoutToken();
    }

    public function test_user_map_renders_dynamic_coordinates_aisle_screen_and_pair_metadata(): void
    {
        $response = $this->get(route('user.bookings.selectSeat', $this->showtime));
        $response->assertOk()
            ->assertSee('Layout v1')
            ->assertSee('repeat(13', false)
            ->assertSee('Lối đi')
            ->assertSee('data-pair-code="K-PAIR-1"', false)
            ->assertSee('data-seat-code="K1"', false);
    }

    public function test_half_couple_is_rejected_at_checkout_and_store(): void
    {
        $half = Seat::query()->where('room_id', $this->rooms['P01']->id)->where('seat_code', 'K1')->firstOrFail();
        $this->get(route('user.bookings.checkout', ['showtime' => $this->showtime, 'selected_seats' => $half->id]))
            ->assertRedirect(route('user.bookings.selectSeat', $this->showtime))
            ->assertSessionHas('error');

        $this->post(route('user.bookings.store'), [
            'showtime_id' => $this->showtime->id, 'seat_ids' => [$half->id],
            'payment_method' => 'fake', 'customer_email' => 'guest@example.test',
            'checkout_token' => $this->checkoutToken,
        ])->assertSessionHasErrors('seat_ids');
        $this->assertDatabaseCount('bookings', 0);
    }

    public function test_complete_couple_pair_reaches_checkout(): void
    {
        $pair = Seat::query()->where('room_id', $this->rooms['P01']->id)->where('pair_code', 'K-PAIR-1')->orderBy('number')->get();
        $this->assertCount(2, $pair);
        $this->get(route('user.bookings.checkout', [
            'showtime' => $this->showtime,
            'selected_seats' => $pair->pluck('id')->implode(','),
        ]))->assertOk()->assertSee('K1')->assertSee('K2');
    }

    public function test_seat_from_another_layout_is_rejected(): void
    {
        $foreign = Seat::query()->where('room_id', $this->rooms['P02']->id)->where('status', 'active')->firstOrFail();
        $this->get(route('user.bookings.checkout', ['showtime' => $this->showtime, 'selected_seats' => $foreign->id]))
            ->assertRedirect(route('user.bookings.selectSeat', $this->showtime))->assertSessionHas('error');

        $this->post(route('user.bookings.store'), [
            'showtime_id' => $this->showtime->id, 'seat_ids' => [$foreign->id],
            'payment_method' => 'fake', 'customer_email' => 'guest@example.test',
            'checkout_token' => $this->checkoutToken,
        ])->assertSessionHasErrors('seat_ids');
    }

    public function test_maintenance_seat_is_disabled_and_rejected_by_backend(): void
    {
        $room = $this->rooms['P02'];
        $maintenance = Seat::query()->where('room_id', $room->id)->where('status', 'maintenance')->firstOrFail();
        $showtime = Showtime::query()->create([
            'movie_id' => $this->showtime->movie_id, 'cinema_id' => $this->cinema->id, 'room_id' => $room->id,
            'room_layout_id' => $room->latestPublishedLayout()->first()->id,
            'show_date' => now()->addDays(10)->toDateString(), 'show_time' => '12:00:00',
            'price' => 50000, 'vip_price' => 70000, 'status' => 'active',
        ]);

        $this->get(route('user.bookings.selectSeat', $showtime))
            ->assertOk()->assertSee('aria-label="Ghế F6, vip, maintenance', false)->assertSee('disabled', false);
        $this->post(route('user.bookings.store'), [
            'showtime_id' => $showtime->id, 'seat_ids' => [$maintenance->id],
            'payment_method' => 'fake', 'customer_email' => 'guest@example.test',
            'checkout_token' => $this->checkoutToken,
        ])->assertSessionHasErrors('seat_ids');
    }

    public function test_booked_seat_is_disabled(): void
    {
        $seat = Seat::query()->where('room_id', $this->rooms['P01']->id)->where('seat_code', 'A1')->firstOrFail();
        $booking = Booking::query()->create([
            'user_id' => null, 'customer_email' => 'old@example.test', 'showtime_id' => $this->showtime->id,
            'booking_code' => 'MMT-2026-9999', 'total_amount' => 50000,
            'payment_status' => 'paid', 'booking_status' => 'paid',
        ]);
        BookingSeat::query()->create([
            'booking_id' => $booking->id,
            'showtime_id' => $this->showtime->id,
            'seat_id' => $seat->id,
            'active_lock_key' => BookingSeat::ACTIVE_LOCK_KEY,
            'price' => 50000,
        ]);

        $this->get(route('user.bookings.selectSeat', $this->showtime))
            ->assertOk()->assertSee('aria-label="Ghế A1', false)->assertSee('cursor-not-allowed', false);
    }

    public function test_fake_frontend_total_is_ignored_and_backend_recalculates(): void
    {
        Mail::fake();
        $seat = Seat::query()->where('room_id', $this->rooms['P01']->id)->where('seat_code', 'A1')->firstOrFail();

        $this->post(route('user.bookings.store'), [
            'showtime_id' => $this->showtime->id, 'seat_ids' => [$seat->id],
            'payment_method' => 'fake', 'customer_email' => 'guest@example.test',
            'checkout_token' => $this->checkoutToken,
            'total_amount' => 1, 'price' => 1, 'room_id' => $this->rooms['P02']->id, 'room_layout_id' => 999,
        ])->assertRedirect();

        $this->assertDatabaseHas('bookings', ['showtime_id' => $this->showtime->id, 'total_amount' => 50000]);
        $this->assertDatabaseHas('bookings', [
            'showtime_id' => $this->showtime->id,
            'total_amount' => 50000,
            'booking_status' => 'pending_payment',
            'payment_status' => 'unpaid',
        ]);
        $this->assertDatabaseCount('payments', 0);
        Mail::assertNothingSent();
    }

    public function test_showtime_without_published_layout_cannot_open_seat_map(): void
    {
        $this->showtime->update(['room_layout_id' => null]);
        $this->get(route('user.bookings.selectSeat', $this->showtime))
            ->assertRedirect(route('user.movies.show', 'booking-movie'));
    }
}
