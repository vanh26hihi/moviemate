<?php

namespace Tests\Feature\Seats;

use App\Models\ActivityLog;
use App\Models\Booking;
use App\Models\BookingSeat;
use App\Models\Movie;
use App\Models\Payment;
use App\Models\Permission;
use App\Models\Room;
use App\Models\RoomLayout;
use App\Models\RoomLayoutCell;
use App\Models\Seat;
use App\Models\Showtime;
use App\Services\CinemaContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class SeatMaintenanceOperationsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRbac();
    }

    public function test_room_scoped_routes_enforce_authentication_permissions_and_ownership(): void
    {
        [$room, $layout, $seats] = $this->roomWithSeats();
        [$otherRoom, , $otherSeats] = $this->roomWithSeats('OTHER');

        $this->get(route('admin.rooms.seat-maintenance.index', $room))->assertRedirect(route('login'));
        $this->actingAs($this->userWithRole('user'))
            ->get(route('admin.rooms.seat-maintenance.index', $room))->assertForbidden();
        $this->actingAs($this->userWithRole('staff'))
            ->get(route('admin.rooms.seat-maintenance.index', $room))->assertForbidden();
        $this->actingAs($this->userWithRole('admin', ['status' => 'inactive']))
            ->get(route('admin.rooms.seat-maintenance.index', $room))->assertRedirect(route('login'));

        $manager = $this->userWithRole('manager');
        $this->actingAs($manager)
            ->get(route('admin.rooms.seat-maintenance.index', $room))
            ->assertOk()
            ->assertSee($layout->name)
            ->assertSee($seats[0]->seat_code);
        $this->actingAs($manager)
            ->get(route('admin.seats.index'))
            ->assertRedirect(route('admin.rooms.index'));
        $this->actingAs($manager)
            ->patch(route('admin.rooms.seat-maintenance.update', [$room, $otherSeats[0]]), ['status' => 'maintenance'])
            ->assertNotFound();
        $this->assertSame($otherRoom->id, $otherSeats[0]->fresh()->room_id);
        $this->assertSame('active', $otherSeats[0]->fresh()->status);

        $manager->role->permissions()->detach(
            Permission::query()->where('slug', 'seats.manage')->value('id')
        );
        $this->actingAs($manager->fresh())
            ->patch(route('admin.rooms.seat-maintenance.update', [$room, $seats[0]]), ['status' => 'maintenance'])
            ->assertSessionHas('success');
        $this->actingAs($manager->fresh())
            ->post(route('admin.rooms.layout.publish', $room))
            ->assertForbidden();

        $manager->role->permissions()->detach(
            Permission::query()->where('slug', 'seats.maintenance.update')->value('id')
        );
        $this->actingAs($manager->fresh())
            ->get(route('admin.rooms.seat-maintenance.index', $room))->assertOk();
        $this->actingAs($manager->fresh())
            ->patch(route('admin.rooms.seat-maintenance.update', [$room, $seats[0]]), ['status' => 'maintenance'])
            ->assertForbidden();
    }

    public function test_room_pages_link_to_maintenance_and_index_filters_and_paginates_units(): void
    {
        [$room, $layout, $seats] = $this->roomWithSeats('FILTER');
        $seats[1]->update(['status' => 'maintenance']);
        foreach (range(3, 18) as $number) {
            $seat = $this->seat($room, 'A', $number);
            $this->cell($layout, $seat, $number, 1);
        }
        $room->update(['total_seats' => 18]);
        $manager = $this->userWithRole('manager');
        $url = route('admin.rooms.seat-maintenance.index', $room);

        $this->actingAs($manager)->get(route('admin.rooms.index'))
            ->assertOk()->assertSee($url);
        $this->actingAs($manager)->get(route('admin.rooms.show', $room))
            ->assertOk()->assertSee($url);
        $this->actingAs($manager)->get($url.'?status=maintenance')
            ->assertOk()
            ->assertSee('>A2</td>', false)
            ->assertDontSee('>A1</td>', false);
        $this->actingAs($manager)->get($url.'?seat_code=A18&sort=updated_at&direction=desc')
            ->assertOk()
            ->assertSee('>A18</td>', false)
            ->assertDontSee('>A1</td>', false);
        $this->actingAs($manager)->get($url.'?per_page=15')
            ->assertOk()
            ->assertSee('>A15</td>', false)
            ->assertDontSee('>A16</td>', false)
            ->assertSee('?per_page=15&amp;page=2', false);
        $this->actingAs($manager)->get($url.'?sort=id')
            ->assertSessionHasErrors('sort');
    }

    public function test_single_transition_changes_only_status_and_is_idempotent(): void
    {
        [$room, , $seats] = $this->roomWithSeats();
        $seat = $seats[0];
        $admin = $this->userWithRole('admin');
        $before = $seat->only([
            'id', 'room_id', 'row', 'number', 'seat_code', 'type', 'pair_code', 'pair_position',
            'x_position', 'y_position',
        ]);
        $capacity = $room->total_seats;
        $layoutCount = RoomLayout::query()->count();

        $message = 'Đã cập nhật A1 sang đang bảo trì.';
        $html = $this->from(route('admin.rooms.seat-maintenance.index', $room))
            ->actingAs($admin)
            ->followingRedirects()
            ->patch(route('admin.rooms.seat-maintenance.update', [$room, $seat]), ['status' => 'maintenance'])
            ->assertOk()
            ->getContent();

        $this->assertSame('maintenance', $seat->fresh()->status);
        $this->assertSame(1, substr_count(strip_tags($html), $message));
        $this->assertSame($before, $seat->fresh()->only(array_keys($before)));
        $this->assertSame($capacity, $room->fresh()->total_seats);
        $this->assertSame($layoutCount, RoomLayout::query()->count());
        $this->assertSame(1, ActivityLog::query()->where('action', 'seat.maintenance_updated')->count());

        $this->actingAs($admin)
            ->patch(route('admin.rooms.seat-maintenance.update', [$room, $seat]), ['status' => 'maintenance'])
            ->assertSessionHas('warning');
        $this->assertSame(1, ActivityLog::query()->where('action', 'seat.maintenance_updated')->count());

        $this->actingAs($admin)
            ->patch(route('admin.rooms.seat-maintenance.update', [$room, $seat]), ['status' => 'active'])
            ->assertSessionHas('success');
        $this->actingAs($admin)
            ->patch(route('admin.rooms.seat-maintenance.update', [$room, $seat]), ['status' => 'inactive'])
            ->assertSessionHas('success');
        $this->assertSame('inactive', $seat->fresh()->status);
    }

    public function test_structural_fields_and_unknown_status_are_rejected_without_mutation(): void
    {
        [$room, , $seats] = $this->roomWithSeats();
        $seat = $seats[0];
        $admin = $this->userWithRole('admin');
        $before = $seat->fresh()->getAttributes();

        $this->from(route('admin.rooms.seat-maintenance.index', $room))
            ->actingAs($admin)
            ->patch(route('admin.rooms.seat-maintenance.update', [$room, $seat]), [
                'status' => 'maintenance',
                'seat_code' => 'FORGED',
                'type' => 'vip',
                'x_position' => 99,
                'pair_code' => 'FORGED-PAIR',
                'room_id' => 999,
            ])
            ->assertSessionHasErrors(['seat_code', 'type', 'x_position', 'pair_code', 'room_id']);
        $this->assertSame($before, $seat->fresh()->getAttributes());
        $this->assertDatabaseCount('activity_logs', 0);

        $this->actingAs($admin)
            ->patch(route('admin.rooms.seat-maintenance.update', [$room, $seat]), ['status' => 'retired'])
            ->assertSessionHasErrors('status');
        $this->assertSame('active', $seat->fresh()->status);
    }

    public function test_couple_is_rendered_once_and_both_halves_change_atomically(): void
    {
        [$room, , $seats] = $this->roomWithSeats('PAIR', true);
        [$left, $right] = $seats;
        $admin = $this->userWithRole('admin');

        $html = $this->actingAs($admin)
            ->get(route('admin.rooms.seat-maintenance.index', $room))
            ->assertOk()
            ->getContent();
        $this->assertSame(1, substr_count($html, '>Ghế đôi A1–A2</td>'));
        $this->assertSame(1, substr_count($html, 'name="seat_ids[]"'));

        $this->actingAs($admin)
            ->patch(route('admin.rooms.seat-maintenance.update', [$room, $left]), ['status' => 'maintenance'])
            ->assertSessionHas('success');
        $this->assertSame(['maintenance'], Seat::query()->whereIn('id', [$left->id, $right->id])->pluck('status')->unique()->all());
        $this->assertSame(1, ActivityLog::query()->where('action', 'seat.maintenance_updated')->count());
        $event = ActivityLog::query()->where('action', 'seat.maintenance_updated')->firstOrFail();
        $this->assertSame([$left->id, $right->id], $event->context['seat_ids']);
        $this->assertSame(['status' => 'active'], $event->before_data);
        $this->assertSame(['status' => 'maintenance'], $event->after_data);

        Seat::query()->whereIn('id', [$left->id, $right->id])->update(['status' => 'active']);
        $this->actingAs($admin)
            ->patch(route('admin.rooms.seat-maintenance.update', [$room, $right]), ['status' => 'maintenance'])
            ->assertSessionHasErrors('seat');
        $this->assertSame(['active'], Seat::query()->whereIn('id', [$left->id, $right->id])->pluck('status')->unique()->all());
    }

    public function test_inconsistent_or_missing_couple_pair_is_refused(): void
    {
        [$room, , $seats] = $this->roomWithSeats('BROKEN', true);
        [$left, $right] = $seats;
        $admin = $this->userWithRole('admin');
        $right->update(['pair_position' => 'left']);

        $this->actingAs($admin)
            ->patch(route('admin.rooms.seat-maintenance.update', [$room, $left]), ['status' => 'maintenance'])
            ->assertSessionHasErrors('seat');
        $this->assertSame(['active'], Seat::query()->whereIn('id', [$left->id, $right->id])->pluck('status')->unique()->all());

        $right->update(['pair_code' => 'MISSING']);
        $this->actingAs($admin)
            ->patch(route('admin.rooms.seat-maintenance.update', [$room, $left]), ['status' => 'inactive'])
            ->assertSessionHasErrors('seat');
        $this->assertSame('active', $left->fresh()->status);
    }

    public function test_active_hold_and_paid_future_booking_block_unsafe_transition(): void
    {
        [$room, $layout, $seats] = $this->roomWithSeats('PROTECT');
        $admin = $this->userWithRole('admin');
        $showtime = $this->futureShowtime($room, $layout);

        $hold = Booking::query()->create([
            'showtime_id' => $showtime->id,
            'booking_code' => 'HOLD-PROTECT',
            'total_amount' => 50000,
            'payment_status' => 'unpaid',
            'booking_status' => 'pending_payment',
            'expires_at' => now()->addMinutes(10),
        ]);
        $holdSeat = BookingSeat::query()->create([
            'booking_id' => $hold->id,
            'showtime_id' => $showtime->id,
            'seat_id' => $seats[0]->id,
            'active_lock_key' => BookingSeat::ACTIVE_LOCK_KEY,
            'price' => 50000,
        ]);

        $this->actingAs($admin)
            ->patch(route('admin.rooms.seat-maintenance.update', [$room, $seats[0]]), ['status' => 'maintenance'])
            ->assertSessionHasErrors('status');
        $this->assertSame('active', $seats[0]->fresh()->status);

        $hold->update(['booking_status' => 'expired']);
        $holdSeat->update(['active_lock_key' => null]);
        $paid = Booking::query()->create([
            'showtime_id' => $showtime->id,
            'booking_code' => 'PAID-PROTECT',
            'total_amount' => 50000,
            'payment_status' => 'paid',
            'booking_status' => 'paid',
        ]);
        $paid->forceFill(['ticket_emailed_at' => now()])->save();
        $ticketIssuedAt = $paid->ticket_emailed_at;
        $payment = Payment::createForProvider('vnpay', [
            'booking_id' => $paid->id,
            'payment_method' => 'vnpay',
            'amount' => 50000,
            'status' => Payment::STATUS_SUCCESS,
            'verified_at' => now(),
            'paid_at' => now(),
        ]);
        $paymentBefore = $payment->fresh()->getAttributes();
        $layoutSnapshotId = $showtime->room_layout_id;
        BookingSeat::query()->create([
            'booking_id' => $paid->id,
            'showtime_id' => $showtime->id,
            'seat_id' => $seats[0]->id,
            'active_lock_key' => BookingSeat::ACTIVE_LOCK_KEY,
            'price' => 50000,
        ]);

        $this->actingAs($admin)
            ->patch(route('admin.rooms.seat-maintenance.update', [$room, $seats[0]]), ['status' => 'inactive'])
            ->assertSessionHasErrors('status');
        $this->assertSame('active', $seats[0]->fresh()->status);
        $this->assertSame('paid', $paid->fresh()->booking_status);
        $this->assertTrue($ticketIssuedAt->equalTo($paid->fresh()->ticket_emailed_at));
        $this->assertSame($paymentBefore, $payment->fresh()->getAttributes());
        $this->assertSame($layoutSnapshotId, $showtime->fresh()->room_layout_id);
        $this->assertDatabaseHas('booking_seats', ['booking_id' => $paid->id, 'seat_id' => $seats[0]->id]);
        $this->assertDatabaseCount('activity_logs', 0);
    }

    public function test_bulk_is_deduplicated_expands_couples_and_is_all_or_nothing(): void
    {
        [$room, $layout, $normal] = $this->roomWithSeats('BULK');
        $pair = $this->addCouple($room, $layout, 'B', 1, 'B-PAIR');
        $admin = $this->userWithRole('admin');

        $this->actingAs($admin)
            ->post(route('admin.rooms.seat-maintenance.bulk', $room), [
                'seat_ids' => [...array_fill(0, 51, (string) $normal[0]->id), $pair[0]->id],
                'status' => 'maintenance',
            ])
            ->assertSessionHas('success');
        $this->assertSame(
            ['maintenance'],
            Seat::query()->whereIn('id', [$normal[0]->id, $pair[0]->id, $pair[1]->id])->pluck('status')->unique()->all()
        );
        $this->assertSame(1, ActivityLog::query()->where('action', 'seat.maintenance_bulk_updated')->count());

        Seat::query()->whereIn('id', [$normal[0]->id, $normal[1]->id])->update(['status' => 'active']);
        $showtime = $this->futureShowtime($room, $layout);
        $booking = Booking::query()->create([
            'showtime_id' => $showtime->id,
            'booking_code' => 'BULK-PROTECT',
            'total_amount' => 50000,
            'payment_status' => 'paid',
            'booking_status' => 'paid',
        ]);
        BookingSeat::query()->create([
            'booking_id' => $booking->id,
            'showtime_id' => $showtime->id,
            'seat_id' => $normal[1]->id,
            'active_lock_key' => BookingSeat::ACTIVE_LOCK_KEY,
            'price' => 50000,
        ]);

        $this->actingAs($admin)
            ->post(route('admin.rooms.seat-maintenance.bulk', $room), [
                'seat_ids' => [$normal[0]->id, $normal[1]->id],
                'status' => 'inactive',
            ])
            ->assertSessionHasErrors('seat_ids');
        $this->assertSame('active', $normal[0]->fresh()->status);
        $this->assertSame('active', $normal[1]->fresh()->status);
        $this->assertSame(1, ActivityLog::query()->where('action', 'seat.maintenance_bulk_updated')->count());
    }

    public function test_historical_only_seat_is_excluded_and_cannot_be_reactivated(): void
    {
        $room = Room::factory()->create(['cinema_id' => app(CinemaContext::class)->id()]);
        $oldLayout = $this->layout($room, 1, 'Sơ đồ cũ');
        $oldSeat = $this->seat($room, 'Z', 1, 'retired');
        $this->cell($oldLayout, $oldSeat, 1, 1);
        $currentLayout = $this->layout($room, 2, 'Sơ đồ hiện hành');
        $currentSeat = $this->seat($room, 'A', 1);
        $this->cell($currentLayout, $currentSeat, 1, 1);
        $room->update(['total_seats' => 1]);
        $admin = $this->userWithRole('admin');

        $this->actingAs($admin)
            ->get(route('admin.rooms.seat-maintenance.index', $room))
            ->assertOk()
            ->assertSee('chỉ thuộc phiên bản sơ đồ cũ')
            ->assertSee($currentSeat->seat_code)
            ->assertDontSee($oldSeat->seat_code);
        $this->actingAs($admin)
            ->patch(route('admin.rooms.seat-maintenance.update', [$room, $oldSeat]), ['status' => 'active'])
            ->assertSessionHasErrors('seat');
        $this->assertSame('retired', $oldSeat->fresh()->status);
        $this->assertSame(2, RoomLayout::query()->count());
    }

    public function test_query_counts_remain_bounded_for_index_and_mutations(): void
    {
        [$room, $layout, $seats] = $this->roomWithSeats('QUERIES');
        $pair = $this->addCouple($room, $layout, 'B', 1, 'QUERY-PAIR');
        $admin = $this->userWithRole('admin');
        $queries = 0;
        DB::listen(function () use (&$queries): void {
            $queries++;
        });

        $start = $queries;
        $this->actingAs($admin)->get(route('admin.rooms.seat-maintenance.index', $room))->assertOk();
        $indexQueries = $queries - $start;

        $start = $queries;
        $this->actingAs($admin)->get(route('admin.rooms.seat-maintenance.index', [
            'room' => $room,
            'status' => 'active',
            'type' => 'couple',
        ]))->assertOk();
        $filteredQueries = $queries - $start;

        $start = $queries;
        $this->actingAs($admin)->patch(
            route('admin.rooms.seat-maintenance.update', [$room, $seats[0]]),
            ['status' => 'maintenance'],
        )->assertSessionHas('success');
        $singleQueries = $queries - $start;

        $start = $queries;
        $this->actingAs($admin)->patch(
            route('admin.rooms.seat-maintenance.update', [$room, $pair[0]]),
            ['status' => 'maintenance'],
        )->assertSessionHas('success');
        $coupleQueries = $queries - $start;

        $start = $queries;
        $this->actingAs($admin)->post(route('admin.rooms.seat-maintenance.bulk', $room), [
            'seat_ids' => [$seats[0]->id, $seats[1]->id],
            'status' => 'inactive',
        ])->assertSessionHas('success');
        $bulkQueries = $queries - $start;

        $start = $queries;
        $this->actingAs($admin)->get(route('admin.rooms.show', $room))->assertOk();
        $roomDetailQueries = $queries - $start;

        foreach ([
            'index' => $indexQueries,
            'filtered index' => $filteredQueries,
            'single update' => $singleQueries,
            'couple update' => $coupleQueries,
            'bulk update' => $bulkQueries,
            'room detail' => $roomDetailQueries,
        ] as $operation => $count) {
            $this->assertLessThanOrEqual(30, $count, "{$operation} issued {$count} queries");
        }
    }

    /** @return array{Room, RoomLayout, array<int, Seat>} */
    private function roomWithSeats(string $code = 'MAINT', bool $couple = false): array
    {
        $room = Room::factory()->create([
            'cinema_id' => app(CinemaContext::class)->id(),
            'code' => $code,
            'total_seats' => 2,
        ]);
        $layout = $this->layout($room, 1, 'Sơ đồ hiện hành');
        if ($couple) {
            return [$room, $layout, $this->addCouple($room, $layout, 'A', 1, 'A-PAIR')];
        }

        $seats = [$this->seat($room, 'A', 1), $this->seat($room, 'A', 2)];
        foreach ($seats as $index => $seat) {
            $this->cell($layout, $seat, $index + 1, 1);
        }

        return [$room, $layout, $seats];
    }

    private function layout(Room $room, int $version, string $name): RoomLayout
    {
        return RoomLayout::query()->create([
            'room_id' => $room->id,
            'version' => $version,
            'name' => $name,
            'rows' => 5,
            'columns' => 20,
            'screen_position' => 'top',
            'status' => 'published',
            'published_at' => now(),
        ]);
    }

    private function seat(Room $room, string $row, int $number, string $status = 'active'): Seat
    {
        return Seat::query()->create([
            'room_id' => $room->id,
            'row' => $row,
            'number' => $number,
            'seat_code' => $row.$number,
            'type' => 'normal',
            'status' => $status,
        ]);
    }

    private function cell(RoomLayout $layout, Seat $seat, int $x, int $y): void
    {
        RoomLayoutCell::query()->create([
            'room_layout_id' => $layout->id,
            'x_position' => $x,
            'y_position' => $y,
            'cell_type' => 'seat',
            'seat_id' => $seat->id,
        ]);
    }

    /** @return array{Seat, Seat} */
    private function addCouple(Room $room, RoomLayout $layout, string $row, int $number, string $pairCode): array
    {
        $left = Seat::query()->create([
            'room_id' => $room->id,
            'row' => $row,
            'number' => $number,
            'seat_code' => $row.$number,
            'type' => 'couple',
            'pair_code' => $pairCode,
            'pair_position' => 'left',
            'x_position' => 5,
            'y_position' => 2,
            'status' => 'active',
        ]);
        $right = Seat::query()->create([
            'room_id' => $room->id,
            'row' => $row,
            'number' => $number + 1,
            'seat_code' => $row.($number + 1),
            'type' => 'couple',
            'pair_code' => $pairCode,
            'pair_position' => 'right',
            'x_position' => 6,
            'y_position' => 2,
            'status' => 'active',
        ]);
        $this->cell($layout, $left, 5, 2);
        $this->cell($layout, $right, 6, 2);

        return [$left, $right];
    }

    private function futureShowtime(Room $room, RoomLayout $layout): Showtime
    {
        $movie = Movie::query()->create([
            'title' => 'Phim bảo trì '.uniqid(),
            'slug' => 'phim-bao-tri-'.uniqid(),
            'duration' => 90,
            'age_rating' => 'P',
            'status' => 'now_showing',
        ]);

        return Showtime::query()->create([
            'movie_id' => $movie->id,
            'cinema_id' => $room->cinema_id,
            'room_id' => $room->id,
            'room_layout_id' => $layout->id,
            'show_date' => now()->addDay()->toDateString(),
            'show_time' => '20:00:00',
            'price' => 50000,
            'vip_price' => 70000,
            'status' => 'active',
        ]);
    }
}
