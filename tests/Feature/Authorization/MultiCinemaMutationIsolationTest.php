<?php

namespace Tests\Feature\Authorization;

use App\Models\Booking;
use App\Models\BookingTicketDelivery;
use App\Models\Cinema;
use App\Models\Movie;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Room;
use App\Models\RoomLayout;
use App\Models\Seat;
use App\Models\Showtime;
use App\Models\TicketCheckinEvent;
use App\Models\UserCinemaAssignment;
use App\Services\CinemaAccessService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Cross-branch protection must reject mutations, not merely hide rows from index pages.
 * Every case here drives a real HTTP route so middleware, policy and query scope all run.
 */
final class MultiCinemaMutationIsolationTest extends TestCase
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
            'code' => 'BR9',
            'name' => 'MovieMate Branch Nine',
            'status' => 'active',
            'archived_at' => null,
        ]);
    }

    public function test_manager_cannot_mutate_records_owned_by_another_branch(): void
    {
        $manager = $this->userWithRole('manager');
        $foreign = $this->scenario($this->other);

        $this->actingAs($manager)
            ->patch(route('admin.rooms.status.update', $foreign['room']), ['status' => 'inactive'])
            ->assertNotFound();
        $this->assertSame('active', $foreign['room']->fresh()->status);

        $this->actingAs($manager)
            ->patch(route('admin.rooms.seat-maintenance.update', [$foreign['room'], $foreign['seat']]), [
                'status' => 'maintenance',
            ])
            ->assertNotFound();
        $this->assertSame('active', $foreign['seat']->fresh()->status);

        $this->actingAs($manager)
            ->post(route('admin.bookings.cancel', $foreign['booking']), ['reason' => 'test'])
            ->assertNotFound();

        $this->actingAs($manager)
            ->post(route('admin.ticket-deliveries.retry', $foreign['delivery']))
            ->assertNotFound();
    }

    public function test_branch_scoped_index_pages_exclude_foreign_records(): void
    {
        $manager = $this->userWithRole('manager');
        $foreign = $this->scenario($this->other);
        $own = $this->scenario($this->primary);

        $pages = [
            route('admin.rooms.index'),
            route('admin.showtimes.index'),
            route('admin.bookings.index'),
            route('admin.payments.index'),
            route('admin.payment-reconciliation.index'),
            route('admin.ticket-deliveries.index'),
            route('admin.ticket-checkins.index'),
            route('admin.food-orders.index'),
        ];

        foreach ($pages as $page) {
            $response = $this->actingAs($manager)->get($page)->assertOk();
            $this->assertStringNotContainsString(
                $foreign['booking']->booking_code,
                $response->getContent(),
                $page.' để lộ dữ liệu chi nhánh khác.'
            );
        }

        $this->actingAs($manager)->get(route('admin.bookings.index'))
            ->assertOk()->assertSee($own['booking']->booking_code);
    }

    public function test_foreign_payment_review_and_food_order_details_are_denied(): void
    {
        $manager = $this->userWithRole('manager');
        $foreign = $this->scenario($this->other);

        $order = Order::query()->create([
            'booking_id' => $foreign['booking']->id,
            'customer_name' => '',
            'customer_email' => 'branch@example.test',
            'pickup_cinema_id' => $this->other->id,
            'subtotal' => 10000,
            'total_amount' => 10000,
            'status' => 'pending',
        ]);

        $this->actingAs($manager)->get(route('admin.food-orders.show', $order))->assertNotFound();
        $this->actingAs($manager)->get(route('admin.payments.show', $foreign['payment']))->assertNotFound();
    }

    public function test_staff_assigned_to_two_branches_sees_both_and_nothing_else(): void
    {
        $third = Cinema::factory()->create([
            'code' => 'BR8',
            'name' => 'MovieMate Branch Eight',
            'status' => 'active',
            'archived_at' => null,
        ]);
        $manager = $this->userWithRole('manager');
        UserCinemaAssignment::query()->create([
            'user_id' => $manager->id,
            'cinema_id' => $this->other->id,
            'status' => UserCinemaAssignment::STATUS_ACTIVE,
            'assigned_at' => now(),
        ]);

        $access = app(CinemaAccessService::class);
        $accessible = $access->accessibleCinemas($manager->fresh())->pluck('id');

        $this->assertTrue($accessible->contains($this->primary->id));
        $this->assertTrue($accessible->contains($this->other->id));
        $this->assertFalse($accessible->contains($third->id));

        // A multi-branch actor still may not claim global scope.
        $this->actingAs($manager->fresh())
            ->post(route('admin.cinema-context.update'), ['cinema_id' => 'all'])
            ->assertForbidden();
    }

    public function test_customer_keeps_cross_branch_history_and_cannot_book_inactive_branch(): void
    {
        $customer = $this->userWithRole('user');
        $here = $this->scenario($this->primary);
        $there = $this->scenario($this->other);

        foreach ([$here, $there] as $scenario) {
            $scenario['booking']->update([
                'user_id' => $customer->id,
                'booking_status' => 'paid',
                'payment_status' => 'paid',
            ]);
        }

        $history = $this->actingAs($customer)->get(route('user.bookings.history'))->assertOk();
        $history->assertSee($here['booking']->booking_code);
        $history->assertSee($there['booking']->booking_code);

        $this->other->update(['status' => 'inactive', 'archived_at' => now()]);

        $this->actingAs($customer)
            ->get(route('user.bookings.selectSeat', $there['showtime']))
            ->assertRedirect();
    }

    public function test_forged_cinema_id_on_checkout_cannot_override_showtime_branch(): void
    {
        $customer = $this->userWithRole('user');
        $scenario = $this->scenario($this->primary);

        // A mismatched cinema_id in the query string must be rejected outright.
        $this->actingAs($customer)
            ->get(route('user.bookings.selectSeat', $scenario['showtime']).'?cinema_id='.$this->other->id)
            ->assertNotFound();

        // Even a directly forged attribute is overwritten from showtime.room.cinema_id.
        $booking = Booking::query()->create([
            'showtime_id' => $scenario['showtime']->id,
            'cinema_id' => $this->other->id,
            'booking_code' => 'FORGED-'.str()->upper(str()->random(6)),
            'customer_email' => 'forge@example.test',
            'total_amount' => 50000,
            'payment_status' => 'unpaid',
            'booking_status' => 'pending_payment',
        ]);

        $this->assertSame($this->primary->id, (int) $booking->cinema_id);
    }

    public function test_revoked_assignment_blocks_context_selection_immediately(): void
    {
        $staff = $this->userWithRole('staff');
        $assignment = $staff->activeCinemaAssignments()->firstOrFail();

        $assignment->update(['status' => UserCinemaAssignment::STATUS_REVOKED]);

        $this->actingAs($staff->fresh())
            ->post(route('admin.cinema-context.update'), ['cinema_id' => (string) $this->primary->id])
            ->assertForbidden();
    }

    /** @return array<string, mixed> */
    private function scenario(Cinema $cinema): array
    {
        $room = Room::query()->create([
            'cinema_id' => $cinema->id,
            'code' => 'R'.str()->upper(str()->random(7)),
            'name' => 'Room '.$cinema->code,
            'room_type' => '2D',
            'total_seats' => 1,
            'status' => 'active',
        ]);
        $seat = Seat::query()->create([
            'room_id' => $room->id,
            'row' => 'A',
            'number' => 1,
            'seat_code' => 'A1',
            'type' => 'normal',
            'status' => 'active',
        ]);
        $layout = RoomLayout::query()->create([
            'room_id' => $room->id,
            'version' => 1,
            'name' => 'Test',
            'rows' => 1,
            'columns' => 1,
            'status' => 'published',
            'published_at' => now(),
        ]);
        $layout->cells()->create([
            'x_position' => 1,
            'y_position' => 1,
            'cell_type' => 'seat',
            'seat_id' => $seat->id,
        ]);
        $movie = Movie::query()->create([
            'title' => 'Branch Movie '.$cinema->code,
            'slug' => 'branch-'.str()->lower(str()->random(8)),
            'duration' => 90,
            'status' => 'now_showing',
        ]);
        $showtime = Showtime::query()->create([
            'movie_id' => $movie->id,
            'cinema_id' => $cinema->id,
            'room_id' => $room->id,
            'room_layout_id' => $layout->id,
            'show_date' => now()->addDay()->toDateString(),
            'show_time' => '19:00:00',
            'price' => 50000,
            'status' => 'active',
        ]);
        $booking = Booking::query()->create([
            'showtime_id' => $showtime->id,
            'booking_code' => 'BR-'.str()->upper(str()->random(10)),
            'customer_email' => 'branch@example.test',
            'total_amount' => 50000,
            'payment_status' => 'unpaid',
            'booking_status' => 'pending_payment',
        ]);
        $payment = Payment::createForProvider('vnpay', [
            'booking_id' => $booking->id,
            'payment_method' => 'vnpay',
            'order_code' => 'PAY-'.str()->upper(str()->random(10)),
            'amount' => 50000,
            'currency' => 'VND',
            'status' => Payment::STATUS_PENDING,
        ]);
        $delivery = BookingTicketDelivery::query()->create([
            'booking_id' => $booking->id,
            'status' => BookingTicketDelivery::STATUS_FAILED,
            'attempts' => 1,
            'available_at' => now(),
        ]);
        $checkin = TicketCheckinEvent::query()->create([
            'booking_id' => $booking->id,
            'showtime_id' => $showtime->id,
            'result' => TicketCheckinEvent::RESULT_REJECTED,
            'scanned_at' => now(),
        ]);

        return compact('room', 'seat', 'layout', 'movie', 'showtime', 'booking', 'payment', 'delivery', 'checkin');
    }
}
