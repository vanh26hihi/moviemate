<?php

namespace Tests\Feature\Showtimes;

use App\Models\ActivityLog;
use App\Models\Booking;
use App\Models\BookingSeat;
use App\Models\Cinema;
use App\Models\Payment;
use App\Models\Room;
use App\Models\RoomLayout;
use App\Models\Seat;
use App\Models\Showtime;
use App\Services\CinemaAccessService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

class ShowtimePreviewTest extends ShowtimeTestCase
{
    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();
        parent::tearDown();
    }

    public function test_preview_requires_complete_authorized_admin_scheduling_input(): void
    {
        $payload = $this->payload($this->movie(90), $this->rooms['P01']);

        $this->postJson(route('admin.showtimes.preview'), $payload)->assertUnauthorized();
        $this->actingAs($this->userWithRole('user'))->postJson(route('admin.showtimes.preview'), $payload)->assertForbidden();
        $this->actingAs($this->userWithRole('staff'))->postJson(route('admin.showtimes.preview'), $payload)->assertForbidden();
        $this->actingAs($this->userWithRole('manager'))->postJson(route('admin.showtimes.preview'), [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['movie_id', 'room_id', 'show_date', 'show_time']);
    }

    public function test_valid_preview_returns_authoritative_window_and_repeated_calls_write_nothing(): void
    {
        $movie = $this->movie(120);
        $room = $this->rooms['P01'];
        $admin = $this->userWithRole('admin');
        $before = $this->operationalCounts();

        for ($request = 0; $request < 2; $request++) {
            $this->actingAs($admin)->postJson(route('admin.showtimes.preview'), $this->payload($movie, $room, [
                'show_date' => '2030-06-10',
                'show_time' => '23:30',
            ]))->assertOk()->assertJson([
                'valid' => true,
                'code' => null,
                'timezone' => 'Asia/Ho_Chi_Minh',
                'window' => [
                    'start_display' => '10/06/2030 23:30',
                    'end_display' => '11/06/2030 01:30',
                    'cleaning_display' => '11/06/2030 01:30 – 11/06/2030 01:45',
                    'room_ready_display' => '11/06/2030 01:45',
                    'runtime_minutes' => 120,
                    'cleaning_buffer_minutes' => 15,
                ],
            ]);
        }

        $this->assertSame($before, $this->operationalCounts());
    }

    public function test_past_exact_now_and_next_minute_preview_match_final_mutation(): void
    {
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2030-06-10 20:33:20', 'Asia/Ho_Chi_Minh'));
        $movie = $this->movie(90);
        $room = $this->rooms['P01'];
        $admin = $this->userWithRole('admin');

        foreach (['20:32', '20:33'] as $time) {
            $payload = $this->payload($movie, $room, ['show_time' => $time]);
            $this->actingAs($admin)->postJson(route('admin.showtimes.preview'), $payload)
                ->assertOk()->assertJson(['valid' => false, 'code' => 'PAST_START']);
            $this->actingAs($admin)->post(route('admin.showtimes.store'), $payload)
                ->assertSessionHasErrors('show_time');
        }

        $future = $this->payload($movie, $room, ['show_time' => '20:34']);
        $this->actingAs($admin)->postJson(route('admin.showtimes.preview'), $future)
            ->assertOk()->assertJson(['valid' => true]);
        $this->actingAs($admin)->post(route('admin.showtimes.store'), $future)
            ->assertRedirect(route('admin.showtimes.index'))->assertSessionHasNoErrors();
    }

    public function test_conflict_preview_exposes_only_operational_context_and_preserves_room_ready_boundary(): void
    {
        $existingMovie = $this->movie(120, ['title' => 'Phim đang chiếm phòng']);
        $candidateMovie = $this->movie(30);
        $room = $this->rooms['P01'];
        $this->existing($existingMovie, $room);
        $admin = $this->userWithRole('admin');

        $this->actingAs($admin)->postJson(route('admin.showtimes.preview'), $this->payload($candidateMovie, $room, ['show_time' => '20:14']))
            ->assertOk()
            ->assertJson([
                'valid' => false,
                'code' => 'ROOM_CONFLICT',
                'conflict' => [
                    'movie' => 'Phim đang chiếm phòng',
                    'room_code' => 'P01',
                    'start_display' => '10/06/2030 18:00',
                    'end_display' => '10/06/2030 20:00',
                    'room_ready_display' => '10/06/2030 20:15',
                ],
            ])
            ->assertJsonMissing(['customer_name' => true])
            ->assertJsonMissing(['booking_code' => true]);

        $this->actingAs($admin)->postJson(route('admin.showtimes.preview'), $this->payload($candidateMovie, $room, ['show_time' => '20:15']))
            ->assertOk()->assertJson(['valid' => true]);
    }

    public function test_operating_window_closed_day_inactive_room_and_missing_layout_return_stable_failures(): void
    {
        $movie = $this->movie(90);
        $room = $this->rooms['P01'];
        $admin = $this->userWithRole('admin');
        $hours = $this->cinema->operatingHours()->create([
            'day_of_week' => 1,
            'opens_at' => '09:00',
            'latest_show_start_at' => '23:00',
            'is_closed' => false,
        ]);

        foreach (['08:59', '23:01'] as $time) {
            $this->actingAs($admin)->postJson(route('admin.showtimes.preview'), $this->payload($movie, $room, ['show_time' => $time]))
                ->assertOk()->assertJson([
                    'valid' => false,
                    'code' => 'OUTSIDE_START_WINDOW',
                    'operating_window' => ['opens_at' => '09:00', 'latest_show_start_at' => '23:00'],
                ]);
        }

        $hours->update(['is_closed' => true]);
        $this->actingAs($admin)->postJson(route('admin.showtimes.preview'), $this->payload($movie, $room))
            ->assertOk()->assertJson(['valid' => false, 'code' => 'CINEMA_CLOSED']);

        $room->update(['status' => 'inactive']);
        $this->actingAs($admin)->postJson(route('admin.showtimes.preview'), $this->payload($movie, $room))
            ->assertOk()->assertJson(['valid' => false, 'code' => 'ROOM_UNAVAILABLE']);

        $noLayout = Room::query()->create([
            'cinema_id' => $this->cinema->id,
            'code' => 'NO-LAYOUT',
            'name' => 'No layout',
            'room_type' => '2D',
            'total_seats' => 0,
            'status' => 'active',
        ]);
        $hours->update(['is_closed' => false]);
        $this->actingAs($admin)->postJson(route('admin.showtimes.preview'), $this->payload($movie, $noLayout))
            ->assertOk()->assertJson(['valid' => false, 'code' => 'LAYOUT_UNAVAILABLE']);
    }

    public function test_update_preview_excludes_itself_and_rejects_history_or_non_upcoming_sources(): void
    {
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2030-06-10 17:00:00', 'Asia/Ho_Chi_Minh'));
        $movie = $this->movie(90);
        $room = $this->rooms['P01'];
        $showtime = $this->existing($movie, $room);
        $admin = $this->userWithRole('admin');
        $payload = $this->payload($movie, $room, ['showtime_id' => $showtime->id]);

        $this->actingAs($admin)->postJson(route('admin.showtimes.preview'), $payload)
            ->assertOk()->assertJson(['valid' => true]);
        $this->actingAs($admin)->postJson(route('admin.showtimes.preview'), [...$payload, 'show_time' => '19:00'])
            ->assertOk()->assertJson(['valid' => true]);

        $booking = Booking::query()->create([
            'showtime_id' => $showtime->id,
            'booking_code' => 'PREVIEW-HISTORY-01',
            'total_amount' => 80_000,
            'seat_subtotal' => 80_000,
            'food_subtotal' => 0,
            'gross_amount' => 80_000,
            'promotion_discount_amount' => 0,
            'currency' => 'VND',
            'payment_status' => 'failed',
            'booking_status' => 'expired',
        ]);
        $this->actingAs($admin)->postJson(route('admin.showtimes.preview'), $payload)
            ->assertOk()->assertJson(['valid' => false, 'code' => 'SHOWTIME_HAS_BOOKING_HISTORY']);
        $booking->delete();

        foreach (['cancelled', 'finished'] as $status) {
            $showtime->forceFill(['status' => $status])->save();
            $this->actingAs($admin)->postJson(route('admin.showtimes.preview'), $payload)
                ->assertOk()->assertJson(['valid' => false, 'code' => 'SHOWTIME_NOT_MUTABLE']);
        }
        $showtime->forceFill(['status' => 'active'])->save();
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2030-06-10 18:30:00', 'Asia/Ho_Chi_Minh'));
        $this->actingAs($admin)->postJson(route('admin.showtimes.preview'), $payload)
            ->assertOk()->assertJson(['valid' => false, 'code' => 'SHOWTIME_NOT_MUTABLE']);
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2030-06-10 20:00:00', 'Asia/Ho_Chi_Minh'));
        $this->actingAs($admin)->postJson(route('admin.showtimes.preview'), $payload)
            ->assertOk()->assertJson(['valid' => false, 'code' => 'SHOWTIME_NOT_MUTABLE']);
    }

    public function test_manager_cannot_preview_another_cinema_and_target_timezone_controls_preview(): void
    {
        $otherCinema = Cinema::query()->create([
            'canonical_key' => 'preview-other',
            'code' => 'PREVIEW-OTHER',
            'name' => 'Other cinema',
            'address' => 'Other',
            'city' => 'HCM',
            'country' => 'VN',
            'timezone' => 'Pacific/Honolulu',
            'status' => 'active',
            'is_primary' => false,
        ]);
        $otherRoom = Room::query()->create([
            'cinema_id' => $otherCinema->id,
            'code' => 'OTHER-01',
            'name' => 'Other room',
            'room_type' => '2D',
            'total_seats' => 0,
            'status' => 'active',
        ]);
        $movie = $this->movie(90);

        $this->actingAs($this->userWithRole('manager'))->postJson(
            route('admin.showtimes.preview'),
            $this->payload($movie, $otherRoom),
        )->assertNotFound();

        RoomLayout::query()->create([
            'room_id' => $otherRoom->id,
            'version' => 1,
            'name' => 'Other published layout',
            'rows' => 1,
            'columns' => 1,
            'status' => 'published',
            'published_at' => now(),
        ]);
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2030-06-10 20:33:20', 'Pacific/Honolulu'));
        $this->withSession([CinemaAccessService::SESSION_KEY => 'all'])
            ->actingAs($this->userWithRole('admin'))->postJson(
                route('admin.showtimes.preview'),
                $this->payload($movie, $otherRoom, ['show_time' => '20:33']),
            )->assertOk()->assertJson([
                'valid' => false,
                'code' => 'PAST_START',
                'timezone' => 'Pacific/Honolulu',
            ]);
    }

    public function test_valid_preview_is_not_a_reservation_and_final_save_rechecks_new_conflict(): void
    {
        $candidateMovie = $this->movie(90);
        $otherMovie = $this->movie(90);
        $room = $this->rooms['P01'];
        $admin = $this->userWithRole('admin');
        $payload = $this->payload($candidateMovie, $room);

        $this->actingAs($admin)->postJson(route('admin.showtimes.preview'), $payload)
            ->assertOk()->assertJson(['valid' => true]);
        $this->assertDatabaseCount('showtimes', 0);

        $this->existing($otherMovie, $room);
        $this->actingAs($admin)->post(route('admin.showtimes.store'), $payload)
            ->assertSessionHasErrors('show_time');
        $this->assertDatabaseCount('showtimes', 1);
    }

    public function test_preview_query_count_is_bounded_for_one_candidate(): void
    {
        DB::flushQueryLog();
        DB::enableQueryLog();
        $this->actingAs($this->userWithRole('admin'))->postJson(
            route('admin.showtimes.preview'),
            $this->payload($this->movie(90), $this->rooms['P01']),
        )->assertOk();
        $queries = count(DB::getQueryLog());
        DB::disableQueryLog();

        $this->assertLessThanOrEqual(25, $queries, "Preview query count exceeded budget: {$queries}");
    }

    public function test_create_and_edit_render_preview_contract_and_frontend_guards_stale_responses(): void
    {
        $movie = $this->movie(90);
        $room = $this->rooms['P01'];
        $showtime = $this->existing($movie, $room);
        $admin = $this->userWithRole('admin');

        $this->actingAs($admin)->get(route('admin.showtimes.create'))
            ->assertOk()
            ->assertSee('data-showtime-schedule-preview', false)
            ->assertSee('data-endpoint="'.route('admin.showtimes.preview').'"', false)
            ->assertSee('data-showtime-save', false)
            ->assertSee('Chọn đủ phim, phòng, ngày và giờ bắt đầu để kiểm tra khung giờ.');
        $this->actingAs($admin)->get(route('admin.showtimes.edit', $showtime))
            ->assertOk()
            ->assertSee('data-showtime-id="'.$showtime->id.'"', false);

        $javascript = file_get_contents(resource_path('js/showtime-schedule-preview.js'));
        $this->assertIsString($javascript);
        $this->assertStringContainsString('const DEBOUNCE_MS = 300', $javascript);
        $this->assertStringContainsString('new AbortController()', $javascript);
        $this->assertStringContainsString('requestSequence !== sequence', $javascript);
        $this->assertStringContainsString('controller?.abort()', $javascript);
        $this->assertStringContainsString('if (!isComplete())', $javascript);
        $this->assertStringNotContainsString('new Date(', $javascript);
    }

    /** @return array<string, int> */
    private function operationalCounts(): array
    {
        return [
            'showtimes' => Showtime::query()->count(),
            'bookings' => Booking::query()->count(),
            'booking_seats' => BookingSeat::query()->count(),
            'seats' => Seat::query()->count(),
            'payments' => Payment::query()->count(),
            'activity_logs' => ActivityLog::query()->count(),
        ];
    }
}
