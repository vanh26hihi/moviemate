<?php

namespace Tests\Feature\Seats;

use App\Models\Booking;
use App\Models\BookingDiscountCode;
use App\Models\BookingSeat;
use App\Models\Cinema;
use App\Models\DiscountCode;
use App\Models\Movie;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Room;
use App\Models\RoomLayout;
use App\Models\RoomLayoutCell;
use App\Models\Seat;
use App\Models\SeatIncident;
use App\Models\SeatIncidentImpact;
use App\Models\SeatIncidentSeat;
use App\Models\Showtime;
use App\Services\BookingCheckoutService;
use App\Services\BookingTokenService;
use App\Services\CinemaContext;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

final class SeatIncidentFoundationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRbac();
    }

    public function test_incident_schema_enforces_history_and_uniqueness(): void
    {
        [$room, , $seats] = $this->roomWithSeats();
        $actor = $this->userWithRole('manager');
        $incident = SeatIncident::query()->create([
            'cinema_id' => $room->cinema_id, 'room_id' => $room->id,
            'reported_by_user_id' => $actor->id, 'status' => 'open', 'reason' => 'seat_broken',
        ]);
        SeatIncidentSeat::query()->create([
            'seat_incident_id' => $incident->id, 'seat_id' => $seats[0]->id, 'active_lock_key' => 'ACTIVE',
        ]);

        $this->expectException(QueryException::class);
        SeatIncidentSeat::query()->create([
            'seat_incident_id' => $incident->id, 'seat_id' => $seats[0]->id, 'active_lock_key' => 'ACTIVE',
        ]);
    }

    public function test_schema_has_required_indexes_and_safe_foreign_keys(): void
    {
        $seatIndexes = collect(Schema::getIndexes('seat_incident_seats'))->pluck('name');
        $impactIndexes = collect(Schema::getIndexes('seat_incident_impacts'))->pluck('name');
        $this->assertContains('seat_incident_active_seat_unique', $seatIndexes);
        $this->assertContains('seat_incident_seat_unique', $seatIndexes);
        $this->assertContains('seat_incident_impact_unique', $impactIndexes);
        $this->assertContains('seat_incident_impacts_resolution_index', $impactIndexes);

        foreach (['seat_incident_seats', 'seat_incident_impacts'] as $table) {
            foreach (Schema::getForeignKeys($table) as $foreign) {
                $this->assertNotSame('cascade', strtolower((string) ($foreign['on_delete'] ?? '')));
            }
        }
    }

    public function test_database_rejects_second_active_incident_and_duplicate_impact_while_actor_is_nullable_history(): void
    {
        [$room, $layout, $seats] = $this->roomWithSeats('CONSTRAINT');
        $actor = $this->userWithRole('manager');
        $showtime = $this->showtime($room, $layout, now()->addDay()->toDateString(), '20:00:00');
        $booking = $this->booking($showtime, 'CONSTRAINT-BOOKING');
        $bookingSeat = $this->bookingSeat($booking, $seats[0]);
        $first = SeatIncident::query()->create(['cinema_id' => $room->cinema_id, 'room_id' => $room->id, 'reported_by_user_id' => $actor->id, 'status' => 'open', 'reason' => 'seat_broken']);
        SeatIncidentSeat::query()->create(['seat_incident_id' => $first->id, 'seat_id' => $seats[0]->id, 'active_lock_key' => 'ACTIVE']);
        SeatIncidentImpact::query()->create(['seat_incident_id' => $first->id, 'booking_seat_id' => $bookingSeat->id, 'detected_classification' => 'ordinary_hold', 'resolution_status' => 'unresolved', 'detected_at' => now()]);
        $second = SeatIncident::query()->create(['cinema_id' => $room->cinema_id, 'room_id' => $room->id, 'reported_by_user_id' => $actor->id, 'status' => 'open', 'reason' => 'safety_issue']);

        try {
            SeatIncidentSeat::query()->create(['seat_incident_id' => $second->id, 'seat_id' => $seats[0]->id, 'active_lock_key' => 'ACTIVE']);
            $this->fail('A second active incident seat lock was accepted.');
        } catch (QueryException) {
            $this->addToAssertionCount(1);
        }
        try {
            SeatIncidentImpact::query()->create(['seat_incident_id' => $first->id, 'booking_seat_id' => $bookingSeat->id, 'detected_classification' => 'ordinary_hold', 'resolution_status' => 'unresolved', 'detected_at' => now()]);
            $this->fail('A duplicate incident impact was accepted.');
        } catch (QueryException) {
            $this->addToAssertionCount(1);
        }

        $actor->delete();
        $this->assertNull($first->fresh()->reported_by_user_id);
        $this->assertDatabaseHas('seat_incident_impacts', ['id' => 1, 'booking_seat_id' => $bookingSeat->id]);
    }

    public function test_no_impact_flow_changes_only_physical_status_without_incident(): void
    {
        [$room, $layout, $seats] = $this->roomWithSeats();
        $layoutBefore = $layout->fresh()->getRawOriginal();

        $this->actingAs($this->userWithRole('manager'))
            ->patch(route('admin.rooms.seat-maintenance.update', [$room, $seats[0]]), [
                'status' => 'maintenance', 'reason' => 'seat_broken',
            ])->assertSessionHas('success');

        $this->assertSame('maintenance', $seats[0]->fresh()->status);
        $this->assertDatabaseCount('seat_incidents', 0);
        $this->assertSame($layoutBefore, $layout->fresh()->getRawOriginal());
    }

    public function test_incident_reason_validation_requires_note_for_other_without_mutation(): void
    {
        [$room, $layout, $seats] = $this->roomWithSeats('REASON');
        $showtime = $this->showtime($room, $layout, now()->addDay()->toDateString(), '20:00:00');
        $this->bookingSeat($this->booking($showtime, 'REASON-HOLD'), $seats[0]);

        $this->actingAs($this->userWithRole('manager'))->patch(
            route('admin.rooms.seat-maintenance.update', [$room, $seats[0]]),
            ['status' => 'maintenance', 'reason' => 'other', 'note' => ''],
        )->assertSessionHasErrors('note');
        $this->assertSame('active', $seats[0]->fresh()->status);
        $this->assertDatabaseCount('seat_incidents', 0);
    }

    public function test_manager_first_maintenance_makes_later_checkout_reject_the_seat(): void
    {
        [$room, $layout, $seats] = $this->roomWithSeats('MANAGER-FIRST');
        $showtime = $this->showtime($room, $layout, now()->addDay()->toDateString(), '20:00:00');
        $this->actingAs($this->userWithRole('manager'))->patch(
            route('admin.rooms.seat-maintenance.update', [$room, $seats[0]]),
            ['status' => 'maintenance', 'reason' => 'seat_broken'],
        )->assertSessionHas('success');

        $this->expectException(ValidationException::class);
        app(BookingCheckoutService::class)->createPendingBooking(
            $showtime->id,
            [$seats[0]->id],
            null,
            'blocked@example.test',
            app(BookingTokenService::class)->issueCheckoutToken(),
        );
    }

    public function test_ordinary_hold_cancels_whole_booking_once_and_releases_food_and_promotion(): void
    {
        [$room, $layout, $seats] = $this->roomWithSeats('HOLD', 3);
        $showtime = $this->showtime($room, $layout, now()->addDay()->toDateString(), '20:00:00');
        $booking = $this->booking($showtime, 'HOLD-ALL');
        $bookingSeats = collect($seats)->map(fn (Seat $seat) => $this->bookingSeat($booking, $seat));
        $order = Order::query()->create([
            'booking_id' => $booking->id, 'customer_name' => 'Khách', 'pickup_cinema_id' => $room->cinema_id,
            'subtotal' => 30000, 'total_amount' => 30000, 'status' => 'pending',
        ]);
        $discount = DiscountCode::query()->create([
            'code' => 'INCIDENT20', 'name' => 'Incident', 'discount_type' => 'fixed', 'discount_value' => 20000,
            'minimum_order_amount' => 0, 'starts_at' => now()->subDay(), 'ends_at' => now()->addDay(),
            'is_active' => true, 'registered_users_only' => false, 'first_order_only' => false,
            'can_combine' => false, 'priority' => 1,
        ]);
        $reservation = BookingDiscountCode::query()->create([
            'booking_id' => $booking->id, 'discount_code_id' => $discount->id,
            'code_snapshot' => $discount->code, 'name_snapshot' => $discount->name,
            'discount_type_snapshot' => 'fixed', 'discount_value_snapshot' => 20000,
            'discount_amount' => 20000, 'subtotal_before' => 150000, 'subtotal_after' => 130000,
            'status' => 'reserved', 'reserved_at' => now(),
        ]);

        $this->actingAs($this->userWithRole('manager'))
            ->patch(route('admin.rooms.seat-maintenance.update', [$room, $seats[1]]), [
                'status' => 'maintenance', 'reason' => 'seat_broken',
            ])->assertRedirect();

        $this->assertSame('cancelled', $booking->fresh()->booking_status);
        $this->assertSame([null], $bookingSeats->map(fn (BookingSeat $seat) => $seat->fresh()->active_lock_key)->unique()->all());
        $this->assertSame(3, $booking->bookingSeats()->count());
        $this->assertSame('cancelled', $order->fresh()->status);
        $this->assertSame('released', $reservation->fresh()->status);
        $this->assertSame(1, DB::table('activity_logs')->where('action', 'booking.cancelled_by_seat_incident')->count());
        $this->assertDatabaseHas('seat_incident_impacts', [
            'booking_seat_id' => $bookingSeats[1]->id, 'detected_classification' => 'ordinary_hold',
            'resolution_status' => 'resolved',
        ]);
        $this->assertDatabaseHas('seat_incidents', ['status' => 'resolved']);
    }

    public function test_couple_records_both_positions_but_cancels_shared_booking_once(): void
    {
        [$room, $layout, $seats] = $this->roomWithSeats('COUPLE', 0, true);
        $showtime = $this->showtime($room, $layout, now()->addDay()->toDateString(), '20:00:00');
        $booking = $this->booking($showtime, 'COUPLE-HOLD');
        foreach ($seats as $seat) {
            $this->bookingSeat($booking, $seat);
        }

        $this->actingAs($this->userWithRole('manager'))
            ->patch(route('admin.rooms.seat-maintenance.update', [$room, $seats[0]]), [
                'status' => 'maintenance', 'reason' => 'safety_issue',
            ])->assertRedirect();

        $this->assertSame(['maintenance'], Seat::query()->whereIn('id', collect($seats)->pluck('id'))->pluck('status')->unique()->all());
        $this->assertDatabaseCount('seat_incident_seats', 2);
        $this->assertDatabaseCount('seat_incident_impacts', 2);
        $this->assertSame(1, DB::table('activity_logs')->where('action', 'booking.cancelled_by_seat_incident')->count());
    }

    public function test_retained_states_and_paid_booking_remain_owned_and_unresolved(): void
    {
        [$room, $layout, $seats] = $this->roomWithSeats('MIXED', 4);
        foreach ([Payment::STATUS_PROCESSING, Payment::STATUS_UNRESOLVED, Payment::STATUS_REVIEW] as $index => $status) {
            $showtime = $this->showtime($room, $layout, now()->addDays($index + 1)->toDateString(), '20:00:00');
            $booking = $this->booking($showtime, 'RETAIN-'.$index);
            $this->bookingSeat($booking, $seats[0]);
            Payment::createForProvider('vnpay', [
                'booking_id' => $booking->id, 'payment_method' => 'vnpay', 'amount' => 50000, 'status' => $status,
            ]);
        }
        $paidShowtime = $this->showtime($room, $layout, now()->addDays(4)->toDateString(), '20:00:00');
        $paid = $this->booking($paidShowtime, 'PAID-IMPACT', 'paid', 'paid');
        $paidSeat = $this->bookingSeat($paid, $seats[0]);
        $payment = Payment::createForProvider('vnpay', [
            'booking_id' => $paid->id, 'payment_method' => 'vnpay', 'amount' => 50000,
            'status' => 'success', 'verified_at' => now(), 'paid_at' => now(),
        ]);
        $paymentBefore = $payment->fresh()->getRawOriginal();
        $paidSeat->admissionTicket->forceFill(['print_count' => 2, 'last_printed_at' => now()])->save();
        $ticketBefore = $paidSeat->admissionTicket->fresh()->getRawOriginal();

        $this->actingAs($this->userWithRole('manager'))
            ->patch(route('admin.rooms.seat-maintenance.update', [$room, $seats[0]]), [
                'status' => 'maintenance', 'reason' => 'seat_broken',
            ])->assertRedirect();

        $this->assertDatabaseCount('seat_incident_impacts', 4);
        $this->assertSame(3, SeatIncidentImpact::query()->where('detected_classification', 'retained_payment')->count());
        $this->assertSame(1, SeatIncidentImpact::query()->where('detected_classification', 'paid')->count());
        $this->assertSame(4, SeatIncidentImpact::query()->where('resolution_status', 'unresolved')->count());
        $this->assertSame(4, BookingSeat::query()->where('active_lock_key', 'ACTIVE')->count());
        $this->assertSame($paymentBefore, $payment->fresh()->getRawOriginal());
        $this->assertSame($ticketBefore, $paidSeat->admissionTicket->fresh()->getRawOriginal());
        $incident = SeatIncident::query()->firstOrFail();
        $this->actingAs($this->userWithRole('admin'))->get(route('admin.rooms.seat-incidents.show', [$room, $incident]))
            ->assertOk()->assertSee('Đã in lại (2)');
    }

    public function test_read_time_classification_converges_from_retained_to_paid(): void
    {
        [$room, $layout, $seats] = $this->roomWithSeats('RACE');
        $showtime = $this->showtime($room, $layout, now()->addDay()->toDateString(), '20:00:00');
        $booking = $this->booking($showtime, 'RACE-PAY');
        $this->bookingSeat($booking, $seats[0]);
        $payment = Payment::createForProvider('vnpay', [
            'booking_id' => $booking->id, 'payment_method' => 'vnpay', 'amount' => 50000, 'status' => 'processing',
        ]);
        $manager = $this->userWithRole('manager');
        $this->actingAs($manager)->patch(route('admin.rooms.seat-maintenance.update', [$room, $seats[0]]), [
            'status' => 'maintenance', 'reason' => 'seat_broken',
        ]);
        $incident = SeatIncident::query()->firstOrFail();

        $booking->forceFill(['booking_status' => 'paid', 'payment_status' => 'paid', 'paid_at' => now()])->save();
        $payment->forceFill(['status' => 'success', 'verified_at' => now(), 'paid_at' => now()])->save();

        $this->actingAs($manager)->get(route('admin.rooms.seat-incidents.show', [$room, $incident]))
            ->assertOk()->assertSee('Đã thanh toán — chờ xử lý');
        $this->assertSame('ACTIVE', $booking->bookingSeats()->firstOrFail()->active_lock_key);
    }

    public function test_incident_detail_reads_never_printed_printed_and_reprinted_ticket_states(): void
    {
        [$room, $layout, $seats] = $this->roomWithSeats('PRINT-STATE');
        foreach ([0, 1, 2] as $index => $printCount) {
            $showtime = $this->showtime($room, $layout, now()->addDays($index + 1)->toDateString(), '20:00:00');
            $booking = $this->booking($showtime, 'PRINT-'.$printCount, 'paid', 'paid');
            $bookingSeat = $this->bookingSeat($booking, $seats[0]);
            Payment::createForProvider('vnpay', [
                'booking_id' => $booking->id, 'payment_method' => 'vnpay', 'amount' => 50000,
                'status' => 'success', 'verified_at' => now(), 'paid_at' => now(),
            ]);
            $bookingSeat->admissionTicket->forceFill([
                'print_count' => $printCount,
                'last_printed_at' => $printCount > 0 ? now() : null,
            ])->save();
        }
        $manager = $this->userWithRole('manager');
        $this->actingAs($manager)->patch(route('admin.rooms.seat-maintenance.update', [$room, $seats[0]]), [
            'status' => 'maintenance', 'reason' => 'seat_broken',
        ])->assertRedirect();

        $html = $this->actingAs($manager)->get(route('admin.rooms.seat-incidents.show', [$room, SeatIncident::query()->firstOrFail()]))
            ->assertOk()->getContent();
        $this->assertSame(1, substr_count($html, '>Chưa in<'));
        $this->assertSame(1, substr_count($html, '>Đã in<'));
        $this->assertSame(1, substr_count($html, '>Đã in lại (2)<'));
        $this->assertDatabaseCount('booking_ticket_print_events', 0);
    }

    public function test_old_layout_and_cross_midnight_upcoming_are_detected_but_playing_is_not(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-08-11 18:00:00', 'Asia/Ho_Chi_Minh'));
        [$room, $oldLayout, $seats] = $this->roomWithSeats('TIME');
        $future = $this->showtime($room, $oldLayout, '2026-08-11', '23:00:00');
        $futureBooking = $this->booking($future, 'OLD-CROSS');
        $futureSeat = $this->bookingSeat($futureBooking, $seats[0]);
        $newLayout = $this->layout($room, 2);
        foreach ($seats as $index => $seat) {
            $this->cell($newLayout, $seat, $index + 1, 1);
        }
        $newLayout->update(['status' => 'published', 'published_at' => now()]);

        $this->actingAs($this->userWithRole('manager'))->patch(
            route('admin.rooms.seat-maintenance.update', [$room, $seats[0]]),
            ['status' => 'maintenance', 'reason' => 'seat_broken'],
        );
        $this->assertDatabaseHas('seat_incident_impacts', ['booking_seat_id' => $futureSeat->id]);
        $this->assertSame('published', $oldLayout->fresh()->status);

        [$playingRoom, $playingLayout, $playingSeats] = $this->roomWithSeats('PLAY');
        $playing = $this->showtime($playingRoom, $playingLayout, '2026-08-11', '17:30:00');
        $playingBooking = $this->booking($playing, 'PLAYING');
        $playingSeat = $this->bookingSeat($playingBooking, $playingSeats[0]);
        $this->actingAs($this->userWithRole('admin'))->patch(
            route('admin.rooms.seat-maintenance.update', [$playingRoom, $playingSeats[0]]),
            ['status' => 'maintenance', 'reason' => 'safety_issue'],
        );
        $this->assertSame('ACTIVE', $playingSeat->fresh()->active_lock_key);
        $this->assertSame(1, SeatIncident::query()->count());
        Carbon::setTestNow();
    }

    public function test_past_booking_payment_ticket_and_print_history_are_untouched(): void
    {
        [$room, $layout, $seats] = $this->roomWithSeats('HISTORY');
        $past = $this->showtime($room, $layout, now()->subDay()->toDateString(), '10:00:00');
        $booking = $this->booking($past, 'PAST-HISTORY', 'paid', 'paid');
        $bookingSeat = $this->bookingSeat($booking, $seats[0]);
        $payment = Payment::createForProvider('vnpay', ['booking_id' => $booking->id, 'payment_method' => 'vnpay', 'amount' => 50000, 'status' => 'success', 'verified_at' => now(), 'paid_at' => now()]);
        $ticket = $bookingSeat->admissionTicket;
        $ticket->forceFill(['print_count' => 2, 'last_printed_at' => now()])->save();
        $snapshots = [$booking->fresh()->getRawOriginal(), $bookingSeat->fresh()->getRawOriginal(), $payment->fresh()->getRawOriginal(), $ticket->fresh()->getRawOriginal()];

        $this->actingAs($this->userWithRole('manager'))->patch(
            route('admin.rooms.seat-maintenance.update', [$room, $seats[0]]),
            ['status' => 'maintenance', 'reason' => 'seat_broken'],
        )->assertSessionHas('success');

        $this->assertDatabaseCount('seat_incidents', 0);
        $this->assertSame($snapshots[0], $booking->fresh()->getRawOriginal());
        $this->assertSame($snapshots[1], $bookingSeat->fresh()->getRawOriginal());
        $this->assertSame($snapshots[2], $payment->fresh()->getRawOriginal());
        $this->assertSame($snapshots[3], $ticket->fresh()->getRawOriginal());
    }

    public function test_bulk_rejects_impacted_unit_and_detail_is_branch_scoped_without_resolution_actions(): void
    {
        [$room, $layout, $seats] = $this->roomWithSeats('BULK-INC');
        $showtime = $this->showtime($room, $layout, now()->addDay()->toDateString(), '20:00:00');
        $booking = $this->booking($showtime, 'BULK-IMPACT');
        $this->bookingSeat($booking, $seats[0]);
        $manager = $this->userWithRole('manager');

        $this->actingAs($manager)->post(route('admin.rooms.seat-maintenance.bulk', $room), [
            'seat_ids' => [$seats[0]->id, $seats[1]->id], 'status' => 'maintenance',
        ])->assertSessionHasErrors('seat_ids');
        $this->assertSame(['active'], Seat::query()->whereIn('id', collect($seats)->pluck('id'))->pluck('status')->unique()->all());

        $this->actingAs($manager)->patch(route('admin.rooms.seat-maintenance.update', [$room, $seats[0]]), [
            'status' => 'maintenance', 'reason' => 'seat_broken',
        ]);
        $incident = SeatIncident::query()->firstOrFail();
        $this->actingAs($manager)->get(route('admin.rooms.seat-maintenance.index', $room))
            ->assertOk()->assertSee(route('admin.rooms.seat-incidents.show', [$room, $incident]));
        $this->actingAs($manager)->get(route('admin.rooms.seat-incidents.show', [$room, $incident]))
            ->assertOk()->assertDontSee('Hoàn tiền')->assertDontSee('Đổi ghế');

        $foreignCinema = Cinema::factory()->create(['is_primary' => false]);
        $foreignRoom = Room::factory()->create(['cinema_id' => $foreignCinema->id]);
        $foreignIncident = SeatIncident::query()->create(['cinema_id' => $foreignCinema->id, 'room_id' => $foreignRoom->id, 'status' => 'open', 'reason' => 'seat_broken']);
        $this->actingAs($manager)->get(route('admin.rooms.seat-incidents.show', [$foreignRoom, $foreignIncident]))
            ->assertNotFound();
    }

    public function test_preview_list_and_detail_query_counts_are_bounded(): void
    {
        [$room, $layout, $seats] = $this->roomWithSeats('QUERY');
        foreach (range(1, 8) as $day) {
            $showtime = $this->showtime($room, $layout, now()->addDays($day)->toDateString(), '20:00:00');
            $booking = $this->booking($showtime, 'QUERY-'.$day);
            $this->bookingSeat($booking, $seats[0]);
            Payment::createForProvider('vnpay', [
                'booking_id' => $booking->id, 'payment_method' => 'vnpay', 'amount' => 50000,
                'status' => Payment::STATUS_REVIEW,
            ]);
        }
        $manager = $this->userWithRole('manager');
        $queries = 0;
        DB::listen(function () use (&$queries): void {
            $queries++;
        });

        $start = $queries;
        $this->actingAs($manager)->get(route('admin.rooms.seat-maintenance.index', $room))->assertOk();
        $previewQueries = $queries - $start;

        $this->actingAs($manager)->patch(route('admin.rooms.seat-maintenance.update', [$room, $seats[0]]), [
            'status' => 'maintenance', 'reason' => 'seat_broken',
        ])->assertRedirect();
        $incident = SeatIncident::query()->firstOrFail();
        $start = $queries;
        $this->actingAs($manager)->get(route('admin.rooms.seat-incidents.show', [$room, $incident]))->assertOk();
        $detailQueries = $queries - $start;

        $this->assertLessThanOrEqual(35, $previewQueries, "Impact preview issued {$previewQueries} queries.");
        $this->assertLessThanOrEqual(25, $detailQueries, "Incident detail issued {$detailQueries} queries.");
    }

    /** @return array{Room, RoomLayout, array<int, Seat>} */
    private function roomWithSeats(string $code = 'INC', int $count = 2, bool $withCouple = false): array
    {
        $room = Room::factory()->create(['cinema_id' => app(CinemaContext::class)->id(), 'code' => $code]);
        $layout = $this->layout($room, 1);
        $seats = [];
        foreach ($count > 0 ? range(1, $count) : [] as $number) {
            $seat = Seat::query()->create(['room_id' => $room->id, 'row' => 'A', 'number' => $number, 'seat_code' => 'A'.$number, 'type' => 'normal', 'status' => 'active']);
            $this->cell($layout, $seat, $number, 1);
            $seats[] = $seat;
        }
        if ($withCouple) {
            $seats = $this->couple($room, $layout);
        }
        $layout->update(['status' => 'published', 'published_at' => now()]);

        return [$room, $layout, $seats];
    }

    private function layout(Room $room, int $version): RoomLayout
    {
        return RoomLayout::query()->create(['room_id' => $room->id, 'version' => $version, 'name' => 'Layout '.$version, 'rows' => 5, 'columns' => 20, 'screen_position' => 'top', 'status' => 'draft']);
    }

    private function cell(RoomLayout $layout, Seat $seat, int $x, int $y): void
    {
        RoomLayoutCell::query()->create(['room_layout_id' => $layout->id, 'x_position' => $x, 'y_position' => $y, 'cell_type' => 'seat', 'seat_id' => $seat->id]);
    }

    /** @return array{Seat, Seat} */
    private function couple(Room $room, RoomLayout $layout): array
    {
        $seats = [];
        foreach ([['left', 1], ['right', 2]] as [$position, $number]) {
            $seat = Seat::query()->create(['room_id' => $room->id, 'row' => 'C', 'number' => $number, 'seat_code' => 'C'.$number, 'type' => 'couple', 'pair_code' => 'C-PAIR', 'pair_position' => $position, 'x_position' => $number, 'y_position' => 2, 'status' => 'active']);
            $this->cell($layout, $seat, $number, 2);
            $seats[] = $seat;
        }

        return $seats;
    }

    private function showtime(Room $room, RoomLayout $layout, string $date, string $time): Showtime
    {
        $movie = Movie::query()->create(['title' => 'Incident '.uniqid(), 'slug' => 'incident-'.uniqid(), 'duration' => 120, 'age_rating' => 'P', 'status' => 'now_showing']);

        return Showtime::query()->create(['movie_id' => $movie->id, 'cinema_id' => $room->cinema_id, 'room_id' => $room->id, 'room_layout_id' => $layout->id, 'presentation_format_id' => $this->presentationFormatFixture($movie, $room)->id, 'show_date' => $date, 'show_time' => $time, 'status' => 'active']);
    }

    private function booking(Showtime $showtime, string $code, string $bookingStatus = 'pending_payment', string $paymentStatus = 'unpaid'): Booking
    {
        return Booking::query()->create(['showtime_id' => $showtime->id, 'booking_code' => $code, 'customer_name' => 'Khách '.$code, 'customer_email' => strtolower($code).'@example.test', 'total_amount' => 50000, 'seat_subtotal' => 50000, 'food_subtotal' => 0, 'gross_amount' => 50000, 'promotion_discount_amount' => 0, 'payment_status' => $paymentStatus, 'booking_status' => $bookingStatus, 'expires_at' => now()->addMinutes(15)]);
    }

    private function bookingSeat(Booking $booking, Seat $seat): BookingSeat
    {
        return BookingSeat::query()->create(['booking_id' => $booking->id, 'showtime_id' => $booking->showtime_id, 'seat_id' => $seat->id, 'active_lock_key' => 'ACTIVE', 'price' => 50000, 'final_unit_amount' => 50000]);
    }
}
