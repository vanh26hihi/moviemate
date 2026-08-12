<?php

namespace Tests\Feature\Authorization;

use App\Models\Booking;
use App\Models\BookingSeat;
use App\Models\BookingTicketDelivery;
use App\Models\Cinema;
use App\Models\Movie;
use App\Models\Payment;
use App\Models\Room;
use App\Models\RoomLayout;
use App\Models\Seat;
use App\Models\Showtime;
use App\Services\CinemaAccessService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class MultiCinemaAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    private Cinema $primary;

    private Cinema $other;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->seedRbac();
        $this->primary = Cinema::query()->active()->primary()->firstOrFail();
        $this->other = Cinema::factory()->create([
            'code' => 'BR2', 'name' => 'MovieMate Branch Two',
            'status' => 'active', 'archived_at' => null,
        ]);
    }

    public function test_global_admin_sees_all_branches_and_can_switch_between_global_and_branch_contexts(): void
    {
        $admin = $this->userWithRole('admin');
        $other = $this->scenario($this->other);
        $primary = $this->scenario($this->primary);

        $this->actingAs($admin)->get(route('admin.cinemas.index'))
            ->assertOk()->assertSee($this->primary->name)->assertSee($this->other->name);
        $this->post(route('admin.cinema-context.update'), ['cinema_id' => (string) $this->other->id])
            ->assertRedirect()->assertSessionHas(CinemaAccessService::SESSION_KEY, $this->other->id);
        $this->get(route('admin.rooms.show', $other['room']))->assertOk();
        $this->get(route('admin.rooms.show', $primary['room']))->assertNotFound();
        $this->post(route('admin.cinema-context.update'), ['cinema_id' => 'all'])
            ->assertSessionHas(CinemaAccessService::SESSION_KEY, 'all');
        $this->get(route('admin.rooms.show', $primary['room']))->assertOk();
    }

    public function test_manager_and_staff_only_see_assigned_branches_and_customer_has_no_assignment(): void
    {
        $manager = $this->userWithRole('manager');
        $staff = $this->userWithRole('staff');
        $customer = $this->userWithRole('user');

        $this->actingAs($manager)->get(route('admin.cinemas.index'))
            ->assertOk()->assertSee($this->primary->name)->assertDontSee($this->other->name);

        // Staff keeps operational-only permissions and no admin panel access, so its branch
        // scope is asserted through the access service and the staff area instead.
        $this->actingAs($staff)->get(route('admin.cinemas.index'))->assertForbidden();
        $staffCinemas = app(CinemaAccessService::class)->accessibleCinemas($staff);
        $this->assertTrue($staffCinemas->contains('id', $this->primary->id));
        $this->assertFalse($staffCinemas->contains('id', $this->other->id));

        $this->assertSame(0, $customer->cinemaAssignments()->count());
    }

    public function test_cross_branch_operational_detail_urls_are_denied(): void
    {
        $manager = $this->userWithRole('manager');
        $scenario = $this->scenario($this->other);

        foreach ([
            route('admin.rooms.show', $scenario['room']),
            route('admin.showtimes.edit', $scenario['showtime']),
            route('admin.bookings.show', $scenario['booking']),
            route('admin.payments.show', $scenario['payment']),
        ] as $url) {
            $this->actingAs($manager)->get($url)->assertNotFound();
        }
    }

    public function test_manager_cannot_promote_global_admin_or_assign_staff_outside_scope(): void
    {
        $manager = $this->userWithRole('manager');
        $staff = $this->userWithRole('staff');

        $this->actingAs($manager)->patch(route('admin.users.role.update', $staff), ['role' => 'admin'])
            ->assertForbidden();
        $this->post(route('admin.users.cinema-assignments.store', $staff), ['cinema_id' => $this->other->id])
            ->assertNotFound();
        $this->assertFalse($staff->fresh()->hasRole('admin'));
        $this->assertDatabaseMissing('user_cinema_assignments', [
            'user_id' => $staff->id, 'cinema_id' => $this->other->id, 'status' => 'active',
        ]);
    }

    public function test_revocation_removes_future_access(): void
    {
        $operator = $this->userWithRole('manager');
        $scenario = $this->scenario($this->primary, $operator->id);
        $assignment = $operator->activeCinemaAssignments()->where('cinema_id', $this->primary->id)->firstOrFail();

        $this->actingAs($operator)->get(route('admin.rooms.show', $scenario['room']))->assertOk();
        $admin = $this->userWithRole('admin');
        $this->actingAs($admin)->delete(route('admin.users.cinema-assignments.destroy', [$operator, $assignment]))
            ->assertRedirect();
        $this->assertSame('revoked', $assignment->fresh()->status);
        $this->actingAs($operator)->get(route('admin.rooms.show', $scenario['room']))->assertForbidden();

    }

    public function test_forged_context_and_inactive_customer_selection_are_rejected(): void
    {
        $manager = $this->userWithRole('manager');
        $this->actingAs($manager)
            ->withSession([CinemaAccessService::SESSION_KEY => $this->other->id])
            ->get(route('admin.rooms.index'))->assertForbidden();

        $this->other->update(['status' => 'inactive', 'archived_at' => now()]);
        $this->post(route('cinema-context.update'), ['cinema_id' => $this->other->id])->assertNotFound();
    }

    public function test_booking_cinema_snapshot_is_derived_from_showtime_not_browser_data(): void
    {
        $scenario = $this->scenario($this->primary, null, false);
        $booking = Booking::query()->create([
            'showtime_id' => $scenario['showtime']->id,
            'cinema_id' => $this->other->id,
            'booking_code' => 'DERIVED-CINEMA',
            'customer_email' => 'customer@example.test',
            'total_amount' => 50000,
            'payment_status' => 'unpaid',
            'booking_status' => 'pending_payment',
        ]);

        $this->assertSame($this->primary->id, $booking->cinema_id);
    }

    /** @return array<string, mixed> */
    private function scenario(Cinema $cinema, ?int $actorId = null, bool $withBooking = true): array
    {
        $room = Room::query()->create([
            'cinema_id' => $cinema->id, 'code' => 'R'.str()->upper(str()->random(7)),
            'name' => 'Room '.$cinema->code, 'room_type' => '2D', 'total_seats' => 1, 'status' => 'active',
        ]);
        $seat = Seat::query()->create([
            'room_id' => $room->id, 'row' => 'A', 'number' => 1,
            'seat_code' => 'A1', 'type' => 'normal', 'status' => 'active',
        ]);
        $layout = RoomLayout::query()->create([
            'room_id' => $room->id, 'version' => 1, 'name' => 'Test',
            'rows' => 1, 'columns' => 1, 'status' => 'published', 'published_at' => now(),
        ]);
        $layout->cells()->create(['x_position' => 1, 'y_position' => 1, 'cell_type' => 'seat', 'seat_id' => $seat->id]);
        $movie = Movie::query()->create([
            'title' => 'Branch Movie '.$cinema->code, 'slug' => 'branch-'.str()->lower(str()->random(8)),
            'duration' => 90, 'status' => 'now_showing',
        ]);
        $showtime = Showtime::query()->create([
            'movie_id' => $movie->id, 'cinema_id' => $cinema->id, 'room_id' => $room->id,
            'room_layout_id' => $layout->id, 'show_date' => now()->addDay()->toDateString(),
            'show_time' => '19:00:00', 'price' => 50000, 'status' => 'active',
        ]);

        if (! $withBooking) {
            return compact('room', 'seat', 'layout', 'movie', 'showtime');
        }

        $booking = Booking::query()->create([
            'showtime_id' => $showtime->id, 'booking_code' => 'BR-'.str()->upper(str()->random(10)),
            'customer_email' => 'branch@example.test', 'total_amount' => 50000,
            'payment_status' => 'unpaid', 'booking_status' => 'pending_payment',
        ]);
        $payment = Payment::createForProvider('vnpay', [
            'booking_id' => $booking->id, 'payment_method' => 'vnpay',
            'order_code' => 'PAY-'.str()->upper(str()->random(10)), 'amount' => 50000,
            'currency' => 'VND', 'status' => Payment::STATUS_PENDING,
        ]);
        $delivery = BookingTicketDelivery::query()->create([
            'booking_id' => $booking->id, 'status' => BookingTicketDelivery::STATUS_PENDING,
            'attempts' => 0, 'available_at' => now(),
        ]);
        $bookingSeat = BookingSeat::query()->create([
            'booking_id' => $booking->id, 'showtime_id' => $showtime->id, 'seat_id' => $seat->id,
            'price' => 50000, 'pricing_unit_key' => 'seat:'.$seat->id,
            'pricing_unit_label' => 'A1', 'seat_type_snapshot' => 'normal', 'final_unit_amount' => 50000,
        ]);
        $bookingSeat->admissionTicket()->firstOrFail();

        return compact('room', 'seat', 'layout', 'movie', 'showtime', 'booking', 'payment', 'delivery');
    }
}
