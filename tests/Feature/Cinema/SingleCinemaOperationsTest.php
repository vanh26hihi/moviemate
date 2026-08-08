<?php

namespace Tests\Feature\Cinema;

use App\Models\Cinema;
use App\Models\FoodItem;
use App\Models\Movie;
use App\Models\Room;
use App\Models\Seat;
use App\Models\Showtime;
use App\Services\BookingTokenService;
use App\Services\CinemaContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SingleCinemaOperationsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRbac();
    }

    public function test_fresh_database_has_the_exact_fpt_profile(): void
    {
        $cinema = app(CinemaContext::class)->current();

        $this->assertSame(1, $cinema->id);
        $this->assertSame('MovieMate Cinema – FPT Polytechnic', $cinema->name);
        $this->assertSame(CinemaContext::SCHOOL_NAME, $cinema->school_name);
        $this->assertSame(CinemaContext::ADDRESS, $cinema->address);
        $this->assertSame(CinemaContext::CITY, $cinema->city);
        $this->assertSame(CinemaContext::COUNTRY, $cinema->country);
        $this->assertSame('21.03812980000000', $cinema->latitude);
        // SQLite stores DECIMAL with numeric affinity; MySQL preserves all 14 decimal places.
        $this->assertEqualsWithDelta(105.44239119453124, (float) $cinema->longitude, 0.00000000001);
        $this->assertNull($cinema->phone);
        $this->assertSame(1, Cinema::query()->primary()->active()->count());
    }

    public function test_legacy_cinema_route_still_ignores_tampering_of_fixed_identity_fields(): void
    {
        $manager = $this->userWithRole('manager');
        $cinema = app(CinemaContext::class)->current();

        $this->actingAs($manager)->get(route('admin.cinema.show'))->assertOk();
        $this->actingAs($manager)->patch(route('admin.cinema.update'), [
            'name' => 'Tampered cinema name',
            'phone' => '0123456789',
            'canonical_key' => 'tampered',
            'address' => 'Tampered address',
            'city' => 'Tampered city',
            'country' => 'Tampered country',
            'latitude' => 0,
            'longitude' => 0,
            'is_primary' => false,
            'status' => 'inactive',
        ])->assertRedirect(route('admin.cinemas.show', $cinema));

        $cinema->refresh();
        $this->assertSame('MovieMate Cinema – FPT Polytechnic', $cinema->name);
        $this->assertSame(CinemaContext::CANONICAL_KEY, $cinema->canonical_key);
        $this->assertSame(CinemaContext::ADDRESS, $cinema->address);
        $this->assertSame(CinemaContext::CITY, $cinema->city);
        $this->assertSame(CinemaContext::COUNTRY, $cinema->country);
        $this->assertTrue($cinema->is_primary);
        $this->assertSame('active', $cinema->status);
        $this->assertSame('0123456789', $cinema->phone);

        // Multi-cinema intentionally introduces branch CRUD for global Admin, but a branch
        // must never be hard-deleted; deactivation preserves historical bookings.
        $this->assertTrue(app('router')->getRoutes()->hasNamedRoute('admin.cinemas.create'));
        $this->assertTrue(app('router')->getRoutes()->hasNamedRoute('admin.cinemas.store'));
        $this->assertFalse(app('router')->getRoutes()->hasNamedRoute('admin.cinemas.destroy'));
    }

    public function test_admin_matrix_protects_the_singleton_cinema_page(): void
    {
        $this->actingAs($this->userWithRole('admin'))->get(route('admin.cinema.show'))->assertOk();
        $this->actingAs($this->userWithRole('manager'))->get(route('admin.cinema.show'))->assertOk();
        $this->actingAs($this->userWithRole('staff'))->get(route('admin.cinema.show'))->assertForbidden();
        $this->actingAs($this->userWithRole('user'))->get(route('admin.cinema.show'))->assertForbidden();
    }

    public function test_canonical_cinema_cannot_be_deleted_through_the_model(): void
    {
        $this->expectException(\LogicException::class);

        app(CinemaContext::class)->current()->delete();
    }

    public function test_room_create_and_update_always_use_canonical_cinema(): void
    {
        $manager = $this->userWithRole('manager');
        $canonical = app(CinemaContext::class)->current();
        $legacy = Cinema::factory()->legacy()->create();

        $response = $this->actingAs($manager)->post(route('admin.rooms.store'), [
            'cinema_id' => $legacy->id,
            'code' => 'P99',
            'name' => 'Phòng 99',
            'room_type' => '2D',
            'total_seats' => 20,
            'status' => 'active',
        ]);

        $room = Room::query()->where('code', 'P99')->sole();
        $response->assertRedirect(route('admin.rooms.layout.show', $room));
        $this->assertSame($canonical->id, $room->cinema_id);
        $this->assertSame(0, $room->total_seats);

        $this->actingAs($manager)->put(route('admin.rooms.update', $room), [
            'cinema_id' => $legacy->id,
            'code' => 'P98',
            'name' => 'phòng 98',
            'room_type' => '3D',
            'total_seats' => 24,
            'status' => 'active',
        ])->assertRedirect(route('admin.rooms.show', $room));

        $room->refresh();
        $this->assertSame($canonical->id, $room->cinema_id);
        $this->assertSame('P98', $room->code);

        $this->actingAs($manager)->post(route('admin.rooms.store'), [
            'code' => 'P97',
            'name' => 'Phòng 98',
            'room_type' => '2D',
            'total_seats' => 0,
            'status' => 'active',
        ])->assertSessionHasErrors('name');
    }

    public function test_showtime_backend_rejects_archived_and_legacy_rooms(): void
    {
        $manager = $this->userWithRole('manager');
        $canonical = app(CinemaContext::class)->current();
        $archived = Room::factory()->create([
            'id' => 12,
            'cinema_id' => $canonical->id,
            'code' => 'ARCH-12',
            'name' => 'Phòng 1 (Ngừng hoạt động)',
            'status' => 'inactive',
        ]);
        $legacy = Cinema::factory()->legacy()->create();
        $legacyRoom = Room::factory()->create(['cinema_id' => $legacy->id, 'code' => 'LEG-01']);
        $movie = Movie::query()->create(['title' => 'Test Movie', 'slug' => 'test-movie', 'status' => Movie::STATUS_NOW_SHOWING]);
        $payload = [
            'movie_id' => $movie->id,
            'show_date' => now()->addDay()->toDateString(),
            'show_time' => '20:00',
            'price' => 80000,
            'status' => 'active',
        ];

        $this->actingAs($manager)->post(route('admin.showtimes.store'), [
            ...$payload, 'room_id' => $archived->id,
        ])->assertSessionHasErrors('room_id');
        // Under multi-cinema a room from another branch is refused by the branch scope before
        // validation runs, so the caller gets 404 instead of a field error. Either way the
        // showtime is never created.
        $this->actingAs($manager)->post(route('admin.showtimes.store'), [
            ...$payload, 'room_id' => $legacyRoom->id,
        ])->assertNotFound();
        $this->actingAs($manager)->get(route('admin.rooms.edit', $archived))->assertOk();
        $this->actingAs($manager)->get(route('admin.seats.manage', $archived))->assertNotFound();

        $this->assertDatabaseCount('showtimes', 0);

        $archivedShowtime = Showtime::query()->create([
            ...$payload,
            'show_time' => '20:00:00',
            'cinema_id' => $canonical->id,
            'room_id' => $archived->id,
        ]);
        $seat = Seat::query()->create([
            'room_id' => $archived->id,
            'row' => 'A',
            'number' => 1,
            'seat_code' => 'A1',
            'type' => 'normal',
            'status' => 'active',
        ]);

        $this->get(route('user.bookings.selectSeat', $archivedShowtime))
            ->assertRedirect(route('user.movies.show', $movie->slug));
        $this->post(route('user.bookings.store'), [
            'showtime_id' => $archivedShowtime->id,
            'seat_ids' => [$seat->id],
            'payment_method' => 'fake',
            'customer_email' => 'customer@example.com',
            'checkout_token' => app(BookingTokenService::class)->issueCheckoutToken(),
        ])->assertGone();
        $this->assertDatabaseCount('bookings', 0);
    }

    public function test_public_branch_selector_lists_only_active_branches_and_standalone_food_checkout_is_retired(): void
    {
        $canonical = app(CinemaContext::class)->current();
        $legacy = Cinema::factory()->legacy()->create();
        $food = FoodItem::query()->create(['name' => 'Bắp rang', 'price' => 50000, 'active' => true]);

        // Multi-cinema restores a customer branch selector. It must offer active branches
        // only, so an inactive legacy branch stays unreachable from the public UI.
        $this->get(route('home'))
            ->assertOk()
            ->assertSee($canonical->name)
            ->assertSee('name="cinema"', false)
            ->assertSee('value="'.$canonical->code.'"', false)
            ->assertDontSee('value="'.$legacy->id.'"', false);

        $this->withSession(['food_cart' => [$food->id => 2]])
            ->get(route('foods.checkout'))
            ->assertGone()
            ->assertDontSee('name="pickup_cinema_id"', false);

        $this->withSession(['food_cart' => [$food->id => 2]])
            ->post(route('foods.store'), [
                'customer_name' => 'Khách thử nghiệm',
                'pickup_cinema_id' => $legacy->id,
            ])->assertGone();

        $this->assertDatabaseCount('orders', 0);
        $this->assertDatabaseCount('order_items', 0);
    }
}
