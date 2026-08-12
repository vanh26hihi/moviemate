<?php

namespace Tests\Feature\Showtimes;

use App\Models\ActivityLog;
use App\Models\AdmissionTicket;
use App\Models\Booking;
use App\Models\BookingSeat;
use App\Models\BookingTicketPrint;
use App\Models\Cinema;
use App\Models\CinemaPricingRule;
use App\Models\Payment;
use App\Models\Room;
use App\Models\RoomLayout;
use App\Models\Seat;
use App\Models\SeatIncident;
use App\Models\Showtime;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

class ShowtimeScheduleCopyTest extends ShowtimeTestCase
{
    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();
        parent::tearDown();
    }

    public function test_room_day_generation_preserves_movie_room_wall_clock_and_deterministic_time_order_without_writes(): void
    {
        $late = $this->movie(90);
        $early = $this->movie(90);
        $room = $this->rooms['P01'];
        $this->existing($late, $room, ['show_date' => '2025-08-12', 'show_time' => '12:00:00']);
        $this->existing($early, $room, ['show_date' => '2025-08-12', 'show_time' => '09:00:00']);
        $before = $this->operationalCounts();

        $response = $this->actingAs($this->userWithRole('manager'))->postJson(
            route('admin.showtimes.copy.generate'),
            $this->copyPayload('room', '2025-08-12', '2030-08-19', $room),
        )->assertOk()->assertJson([
            'scope' => 'room',
            'cinema_id' => $this->cinema->id,
            'source_date' => '2025-08-12',
            'target_date' => '2030-08-19',
            'generated_count' => 2,
        ]);

        $this->assertSame([
            ['row_key' => 'copy-1', 'movie_id' => $early->id, 'room_id' => $room->id, 'show_date' => '2030-08-19', 'show_time' => '09:00'],
            ['row_key' => 'copy-2', 'movie_id' => $late->id, 'room_id' => $room->id, 'show_date' => '2030-08-19', 'show_time' => '12:00'],
        ], $response->json('rows'));
        $this->assertSame($before, $this->operationalCounts());
    }

    public function test_cinema_day_generation_preserves_same_rooms_and_orders_room_code_then_time(): void
    {
        $movie = $this->movie(60);
        $this->existing($movie, $this->rooms['P02'], ['show_date' => '2030-08-12', 'show_time' => '09:00:00']);
        $this->existing($movie, $this->rooms['P01'], ['show_date' => '2030-08-12', 'show_time' => '12:00:00']);
        $this->existing($movie, $this->rooms['P01'], ['show_date' => '2030-08-12', 'show_time' => '09:00:00']);

        $response = $this->actingAs($this->userWithRole('admin'))->postJson(
            route('admin.showtimes.copy.generate'),
            $this->copyPayload('cinema', '2030-08-12', '2030-08-19'),
        )->assertOk()->assertJson(['generated_count' => 3]);

        $this->assertSame(['P01', 'P01', 'P02'], collect($response->json('rows'))->map(
            fn (array $row): string => $this->rooms->firstWhere('id', $row['room_id'])->code,
        )->all());
        $this->assertSame(['09:00', '12:00', '09:00'], collect($response->json('rows'))->pluck('show_time')->all());
    }

    public function test_historical_and_future_active_sources_are_both_valid_templates(): void
    {
        $movie = $this->movie();
        $room = $this->rooms['P01'];
        $this->existing($movie, $room, ['show_date' => '2025-01-01', 'show_time' => '18:00:00']);
        $this->existing($movie, $room, ['show_date' => '2031-01-01', 'show_time' => '19:00:00']);
        $manager = $this->userWithRole('manager');

        $this->actingAs($manager)->postJson(route('admin.showtimes.copy.generate'), $this->copyPayload('room', '2025-01-01', '2030-01-01', $room))
            ->assertOk()->assertJson(['generated_count' => 1]);
        $this->actingAs($manager)->postJson(route('admin.showtimes.copy.generate'), $this->copyPayload('room', '2031-01-01', '2032-01-01', $room))
            ->assertOk()->assertJson(['generated_count' => 1]);
    }

    public function test_same_source_target_empty_and_cancelled_finished_only_sources_are_rejected(): void
    {
        $movie = $this->movie();
        $room = $this->rooms['P01'];
        $this->existing($movie, $room, ['show_date' => '2025-08-12', 'status' => 'cancelled']);
        $this->existing($movie, $room, ['show_date' => '2025-08-12', 'show_time' => '20:30:00', 'status' => 'finished']);
        $manager = $this->userWithRole('manager');

        $this->actingAs($manager)->postJson(route('admin.showtimes.copy.generate'), $this->copyPayload('room', '2025-08-12', '2025-08-12', $room))
            ->assertUnprocessable()->assertJsonValidationErrors('target_date');
        $this->actingAs($manager)->postJson(route('admin.showtimes.copy.generate'), $this->copyPayload('room', '2025-08-12', '2030-08-19', $room))
            ->assertUnprocessable()->assertJsonValidationErrors('source_date');
        $this->assertDatabaseCount('showtimes', 2);
    }

    public function test_copy_request_rejects_invalid_scope_dates_missing_room_and_room_on_cinema_scope(): void
    {
        $manager = $this->userWithRole('manager');

        $this->actingAs($manager)->postJson(route('admin.showtimes.copy.generate'), [
            'scope' => 'week',
            'source_date' => '12/08/2025',
            'target_date' => '19/08/2030',
        ])->assertUnprocessable()->assertJsonValidationErrors(['scope', 'cinema_id', 'source_date', 'target_date']);
        $this->actingAs($manager)->postJson(route('admin.showtimes.copy.generate'), [
            ...$this->copyPayload('room', '2025-08-12', '2030-08-19'),
            'room_id' => null,
        ])->assertUnprocessable()->assertJsonValidationErrors('room_id');
        $this->actingAs($manager)->postJson(route('admin.showtimes.copy.generate'), [
            ...$this->copyPayload('cinema', '2025-08-12', '2030-08-19'),
            'room_id' => $this->rooms['P01']->id,
        ])->assertUnprocessable()->assertJsonValidationErrors('room_id');
    }

    public function test_mixed_status_generation_includes_only_active_source_and_cross_midnight_belongs_to_start_date(): void
    {
        $movie = $this->movie(120);
        $room = $this->rooms['P01'];
        $active = $this->existing($movie, $room, ['show_date' => '2025-08-12', 'show_time' => '23:30:00']);
        $this->existing($movie, $room, ['show_date' => '2025-08-12', 'show_time' => '18:00:00', 'status' => 'cancelled']);
        $manager = $this->userWithRole('manager');

        $this->actingAs($manager)->postJson(route('admin.showtimes.copy.generate'), $this->copyPayload('room', '2025-08-12', '2030-08-19', $room))
            ->assertOk()->assertJson([
                'generated_count' => 1,
                'rows' => [[
                    'movie_id' => $active->movie_id,
                    'show_date' => '2030-08-19',
                    'show_time' => '23:30',
                ]],
            ]);
        $this->actingAs($manager)->postJson(route('admin.showtimes.copy.generate'), $this->copyPayload('room', '2025-08-13', '2030-08-20', $room))
            ->assertUnprocessable()->assertJsonValidationErrors('source_date');
    }

    public function test_generation_authorization_blocks_staff_customer_foreign_branch_and_room_cinema_mismatch(): void
    {
        $movie = $this->movie();
        $this->existing($movie, $this->rooms['P01'], ['show_date' => '2025-08-12']);
        $payload = $this->copyPayload('cinema', '2025-08-12', '2030-08-19');

        $this->postJson(route('admin.showtimes.copy.generate'), $payload)->assertUnauthorized();
        $this->actingAs($this->userWithRole('staff'))->postJson(route('admin.showtimes.copy.generate'), $payload)->assertForbidden();
        $this->actingAs($this->userWithRole('user'))->postJson(route('admin.showtimes.copy.generate'), $payload)->assertForbidden();

        $otherCinema = Cinema::factory()->create(['status' => 'active', 'archived_at' => null]);
        $otherRoom = Room::factory()->create(['cinema_id' => $otherCinema->id]);
        $manager = $this->userWithRole('manager');
        $this->actingAs($manager)->postJson(route('admin.showtimes.copy.generate'), [
            ...$payload,
            'cinema_id' => $otherCinema->id,
        ])->assertNotFound();
        $this->actingAs($manager)->postJson(route('admin.showtimes.copy.generate'), [
            ...$this->copyPayload('room', '2025-08-12', '2030-08-19', $otherRoom),
            'cinema_id' => $this->cinema->id,
        ])->assertNotFound();
    }

    public function test_global_admin_can_generate_for_one_selected_active_cinema(): void
    {
        $otherCinema = Cinema::factory()->create(['status' => 'active', 'archived_at' => null]);
        $otherRoom = Room::factory()->create(['cinema_id' => $otherCinema->id, 'code' => 'Z01']);
        $layout = RoomLayout::query()->create([
            'room_id' => $otherRoom->id, 'version' => 1, 'rows' => 1, 'columns' => 1,
            'screen_position' => 'top', 'status' => 'published', 'published_at' => now(),
        ]);
        Showtime::query()->create([
            'movie_id' => $this->movie()->id,
            'cinema_id' => $otherCinema->id,
            'room_id' => $otherRoom->id,
            'room_layout_id' => $layout->id,
            'show_date' => '2025-08-12',
            'show_time' => '18:00:00',
            'price' => 1,
            'status' => 'active',
        ]);

        $this->actingAs($this->userWithRole('admin'))->postJson(route('admin.showtimes.copy.generate'), [
            ...$this->copyPayload('cinema', '2025-08-12', '2030-08-19'),
            'cinema_id' => $otherCinema->id,
        ])->assertOk()->assertJson([
            'cinema_id' => $otherCinema->id,
            'generated_count' => 1,
            'rows' => [['room_id' => $otherRoom->id]],
        ]);
    }

    public function test_generation_query_count_is_bounded_and_does_not_n_plus_one_movie_or_room(): void
    {
        $movie = $this->movie(30);
        foreach ($this->rooms as $room) {
            foreach (['09:00:00', '12:00:00', '15:00:00'] as $time) {
                $this->existing($movie, $room, ['show_date' => '2025-08-12', 'show_time' => $time]);
            }
        }
        $admin = $this->userWithRole('admin');
        DB::flushQueryLog();
        DB::enableQueryLog();
        $this->actingAs($admin)->postJson(route('admin.showtimes.copy.generate'), $this->copyPayload('cinema', '2025-08-12', '2030-08-19'))
            ->assertOk()->assertJson(['generated_count' => 9]);
        $queryCount = count(DB::getQueryLog());
        DB::disableQueryLog();

        $this->assertLessThanOrEqual(15, $queryCount, "Copy generation query count exceeded budget: {$queryCount}");
    }

    public function test_generated_rows_use_existing_bulk_contract_and_target_preview_rejects_past_persisted_and_new_internal_conflicts(): void
    {
        $movie = $this->movie(60);
        $room = $this->rooms['P01'];
        $this->existing($movie, $room, ['show_date' => '2025-08-12', 'show_time' => '18:00:00']);
        $this->existing($movie, $room, ['show_date' => '2025-08-12', 'show_time' => '19:15:00']);
        $admin = $this->userWithRole('admin');

        CarbonImmutable::setTestNow(CarbonImmutable::parse('2030-08-19 18:30:00', 'Asia/Ho_Chi_Minh'));
        $pastRows = $this->generateRows($admin, $this->copyPayload('room', '2025-08-12', '2030-08-19', $room));
        $this->actingAs($admin)->postJson(route('admin.showtimes.bulk.preview'), ['rows' => $pastRows])
            ->assertOk()->assertJsonPath('rows.0.code', 'PAST_START');

        CarbonImmutable::setTestNow(CarbonImmutable::parse('2029-08-19 12:00:00', 'Asia/Ho_Chi_Minh'));
        $competing = $this->existing($movie, $room, ['show_date' => '2030-08-19', 'show_time' => '18:30:00']);
        $this->actingAs($admin)->postJson(route('admin.showtimes.bulk.preview'), ['rows' => $pastRows])
            ->assertOk()->assertJsonPath('rows.0.code', 'ROOM_CONFLICT');
        $competing->delete();

        $movie->update(['duration' => 90]);
        $this->actingAs($admin)->postJson(route('admin.showtimes.bulk.preview'), ['rows' => $pastRows])
            ->assertOk()
            ->assertJsonPath('rows.0.code', 'BATCH_ROOM_CONFLICT')
            ->assertJsonPath('rows.1.code', 'BATCH_ROOM_CONFLICT');
    }

    public function test_target_publish_rederives_latest_layout_current_pricing_and_current_cleaning(): void
    {
        $movie = $this->movie(60);
        $room = $this->rooms['P01'];
        $oldLayout = $room->latestPublishedLayout()->firstOrFail();
        $source = $this->existing($movie, $room, [
            'show_date' => '2025-08-12',
            'show_time' => '18:00:00',
            'room_layout_id' => $oldLayout->id,
            'price' => 12_345,
            'vip_price' => 23_456,
            'pricing_version' => 'historical-source',
        ]);
        $latestLayout = RoomLayout::query()->create([
            'room_id' => $room->id,
            'version' => (int) $oldLayout->version + 1,
            'name' => 'Sơ đồ hiện hành',
            'rows' => 1,
            'columns' => 1,
            'screen_position' => 'top',
            'status' => 'published',
            'published_at' => now(),
        ]);
        $room->update(['cleaning_buffer_minutes' => 30]);
        CinemaPricingRule::query()->where('cinema_id', $this->cinema->id)->where('rule_type', 'base')
            ->update(['amount_vnd' => 95_000]);
        $admin = $this->userWithRole('admin');
        $rows = $this->generateRows($admin, $this->copyPayload('room', '2025-08-12', '2030-08-19', $room));

        $this->assertSame([
            'row_key', 'movie_id', 'room_id', 'show_date', 'show_time',
        ], array_keys($rows[0]));
        $this->actingAs($admin)->postJson(route('admin.showtimes.bulk.preview'), ['rows' => $rows])
            ->assertOk()
            ->assertJson(['valid' => true])
            ->assertJsonPath('rows.0.window.cleaning_display', '19/08/2030 19:00 – 19/08/2030 19:30')
            ->assertJsonPath('rows.0.window.room_ready_display', '19/08/2030 19:30');
        $this->actingAs($admin)->postJson(route('admin.showtimes.bulk.store'), ['rows' => $rows])
            ->assertCreated()->assertJson(['valid' => true, 'created_count' => 1]);

        $target = Showtime::query()->whereDate('show_date', '2030-08-19')->sole();
        $this->assertSame($latestLayout->id, $target->room_layout_id);
        $this->assertSame(95_000, (int) $target->price);
        $this->assertSame(125_000, (int) $target->vip_price);
        $this->assertNotSame($source->pricing_version, $target->pricing_version);
    }

    public function test_stale_state_after_copy_rejects_whole_batch_and_edited_intent_requires_a_new_preview(): void
    {
        $movie = $this->movie(60);
        $room = $this->rooms['P01'];
        $this->existing($movie, $room, ['show_date' => '2025-08-12', 'show_time' => '18:00:00']);
        $admin = $this->userWithRole('admin');
        $rows = $this->generateRows($admin, $this->copyPayload('room', '2025-08-12', '2030-08-19', $room));

        $this->actingAs($admin)->postJson(route('admin.showtimes.bulk.preview'), ['rows' => $rows])
            ->assertOk()->assertJson(['valid' => true]);
        $competing = $this->existing($movie, $room, ['show_date' => '2030-08-19', 'show_time' => '18:30:00']);
        $this->actingAs($admin)->postJson(route('admin.showtimes.bulk.store'), ['rows' => $rows])
            ->assertUnprocessable()->assertJsonPath('rows.0.code', 'ROOM_CONFLICT');
        $this->assertDatabaseCount('showtimes', 2);

        $competing->delete();
        $editedRows = [[...$rows[0], 'show_time' => '20:00']];
        $this->actingAs($admin)->postJson(route('admin.showtimes.bulk.preview'), ['rows' => $editedRows])
            ->assertOk()->assertJson(['valid' => true]);
        $this->actingAs($admin)->postJson(route('admin.showtimes.bulk.store'), ['rows' => $editedRows])
            ->assertCreated();
        $this->assertDatabaseHas('showtimes', [
            'room_id' => $room->id,
            'show_date' => '2030-08-19 00:00:00',
            'show_time' => '20:00:00',
        ]);
        $this->assertDatabaseMissing('showtimes', [
            'room_id' => $room->id,
            'show_date' => '2030-08-19 00:00:00',
            'show_time' => '18:00:00',
        ]);
    }

    public function test_paid_printed_and_incident_affected_source_is_unchanged_and_never_copied(): void
    {
        $movie = $this->movie(60);
        $room = $this->rooms['P01'];
        $source = $this->existing($movie, $room, ['show_date' => '2025-08-12', 'show_time' => '18:00:00']);
        $seat = Seat::query()->where('room_id', $room->id)->firstOrFail();
        $booking = Booking::query()->create([
            'customer_name' => 'Khách đã thanh toán',
            'customer_email' => 'paid@example.test',
            'showtime_id' => $source->id,
            'booking_code' => 'COPY-PAID-001',
            'total_amount' => 80_000,
            'seat_subtotal' => 80_000,
            'food_subtotal' => 0,
            'gross_amount' => 80_000,
            'promotion_discount_amount' => 0,
            'currency' => 'VND',
            'payment_status' => 'paid',
            'booking_status' => 'paid',
            'paid_at' => now(),
        ]);
        $bookingSeat = BookingSeat::query()->create([
            'booking_id' => $booking->id,
            'showtime_id' => $source->id,
            'seat_id' => $seat->id,
            'active_lock_key' => BookingSeat::ACTIVE_LOCK_KEY,
            'price' => 80_000,
        ]);
        $ticket = AdmissionTicket::query()->where('booking_seat_id', $bookingSeat->id)->firstOrFail();
        $printer = $this->userWithRole('staff');
        BookingTicketPrint::query()->create([
            'admission_ticket_id' => $ticket->id,
            'booking_id' => $booking->id,
            'status' => BookingTicketPrint::STATUS_PRINTED,
            'attempts_count' => 1,
            'printed_by_user_id' => $printer->id,
            'printed_at' => now(),
        ]);
        Payment::createForProvider('vnpay', [
            'booking_id' => $booking->id,
            'amount' => 80_000,
            'currency' => 'VND',
            'status' => Payment::STATUS_SUCCESS,
            'transaction_id' => 'COPY-PAID-TXN',
            'verified_at' => now(),
        ]);
        $incident = SeatIncident::query()->create([
            'cinema_id' => $this->cinema->id,
            'room_id' => $room->id,
            'reported_by_user_id' => $printer->id,
            'status' => SeatIncident::STATUS_RESOLVED,
            'reason' => SeatIncident::REASON_BROKEN,
            'note' => 'Lịch sử sự cố của suất nguồn',
            'resolved_at' => now(),
        ]);
        DB::table('seat_incident_impacts')->insert([
            'seat_incident_id' => $incident->id,
            'booking_seat_id' => $bookingSeat->id,
            'detected_classification' => 'paid',
            'resolution_status' => 'resolved',
            'detected_at' => now(),
            'resolved_at' => now(),
            'resolution_reason' => 'seat_relocated',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $before = [
            ...$this->operationalCounts(),
            'booking_seats' => BookingSeat::query()->count(),
            'admission_tickets' => AdmissionTicket::query()->count(),
            'ticket_prints' => BookingTicketPrint::query()->count(),
            'incident_impacts' => DB::table('seat_incident_impacts')->count(),
        ];

        $rows = $this->generateRows(
            $this->userWithRole('manager'),
            $this->copyPayload('room', '2025-08-12', '2030-08-19', $room),
        );

        $this->assertSame($before, [
            ...$this->operationalCounts(),
            'booking_seats' => BookingSeat::query()->count(),
            'admission_tickets' => AdmissionTicket::query()->count(),
            'ticket_prints' => BookingTicketPrint::query()->count(),
            'incident_impacts' => DB::table('seat_incident_impacts')->count(),
        ]);
        $this->assertSame(['row_key', 'movie_id', 'room_id', 'show_date', 'show_time'], array_keys($rows[0]));
        $this->assertSame($source->movie_id, $rows[0]['movie_id']);
    }

    /** @return array<string, mixed> */
    private function copyPayload(string $scope, string $sourceDate, string $targetDate, ?Room $room = null): array
    {
        return [
            'scope' => $scope,
            'cinema_id' => $this->cinema->id,
            'room_id' => $room?->id,
            'source_date' => $sourceDate,
            'target_date' => $targetDate,
        ];
    }

    /** @return list<array{row_key: string, movie_id: int, room_id: int, show_date: string, show_time: string}> */
    private function generateRows($user, array $payload): array
    {
        return $this->actingAs($user)->postJson(route('admin.showtimes.copy.generate'), $payload)
            ->assertOk()
            ->json('rows');
    }

    /** @return array<string, int> */
    private function operationalCounts(): array
    {
        return [
            'showtimes' => Showtime::query()->count(),
            'bookings' => Booking::query()->count(),
            'payments' => Payment::query()->count(),
            'seat_incidents' => SeatIncident::query()->count(),
            'activity_logs' => ActivityLog::query()->count(),
        ];
    }
}
