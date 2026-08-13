<?php

namespace Tests\Feature\Showtimes;

use App\Exceptions\ShowtimeScheduleException;
use App\Models\AdmissionTicket;
use App\Models\Booking;
use App\Models\BookingSeat;
use App\Models\Payment;
use App\Models\Seat;
use App\Models\Showtime;
use App\Services\ShowtimeScheduleService;
use Carbon\CarbonImmutable;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use PDOException;

class Phase3BShowtimeCorrectnessTest extends ShowtimeTestCase
{
    protected bool $prepareSingleShowtimeFormats = true;

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();
        parent::tearDown();
    }

    public function test_validator_enforces_strict_cinema_local_future_minute_boundary(): void
    {
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2030-06-10 20:33:20', 'Asia/Ho_Chi_Minh'));
        $service = app(ShowtimeScheduleService::class);
        $movie = $this->movie(90);
        $room = $this->rooms['P01'];

        $past = $service->validateCandidate($movie, $room, '2030-06-10', '20:32', presentationFormatId: $this->presentationFormat->id);
        $sameMinute = $service->validateCandidate($movie, $room, '2030-06-10', '20:33', presentationFormatId: $this->presentationFormat->id);
        $nextMinute = $service->validateCandidate($movie, $room, '2030-06-10', '20:34', presentationFormatId: $this->presentationFormat->id);

        $this->assertFalse($past->isValid());
        $this->assertSame('PAST_START', $past->failureCode());
        $this->assertFalse($past->isFuture);
        $this->assertFalse($sameMinute->isValid());
        $this->assertSame('PAST_START', $sameMinute->failureCode());
        $this->assertTrue($nextMinute->isValid());
        $this->assertSame('Asia/Ho_Chi_Minh', $nextMinute->timezone);
        $this->assertSame('2030-06-10 20:34', $nextMinute->window?->start->format('Y-m-d H:i'));
    }

    public function test_target_cinema_timezone_is_authoritative(): void
    {
        config()->set('app.timezone', 'UTC');
        config()->set('cinema.timezone', 'UTC');
        $this->cinema->update(['timezone' => 'Pacific/Honolulu']);
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2030-06-10 20:33:20', 'Pacific/Honolulu'));

        $result = app(ShowtimeScheduleService::class)->validateCandidate(
            $this->movie(90),
            $this->rooms['P01']->fresh(),
            '2030-06-10',
            '20:33',
            presentationFormatId: $this->presentationFormat->id,
        );

        $this->assertSame('Pacific/Honolulu', $result->timezone);
        $this->assertSame('PAST_START', $result->failureCode());
    }

    public function test_validator_returns_stable_operating_hours_closed_day_and_conflict_results(): void
    {
        $service = app(ShowtimeScheduleService::class);
        $movie = $this->movie(90);
        $room = $this->rooms['P01'];
        $hours = $this->cinema->operatingHours()->create([
            'day_of_week' => 1,
            'opens_at' => '09:00',
            'latest_show_start_at' => '23:00',
            'is_closed' => false,
        ]);

        $beforeOpen = $service->validateCandidate($movie, $room->fresh(), '2030-06-10', '08:59', presentationFormatId: $this->presentationFormat->id);
        $atOpen = $service->validateCandidate($movie, $room->fresh(), '2030-06-10', '09:00', presentationFormatId: $this->presentationFormat->id);
        $atLatest = $service->validateCandidate($movie, $room->fresh(), '2030-06-10', '23:00', presentationFormatId: $this->presentationFormat->id);
        $afterLatest = $service->validateCandidate($movie, $room->fresh(), '2030-06-10', '23:01', presentationFormatId: $this->presentationFormat->id);

        $this->assertSame('OUTSIDE_START_WINDOW', $beforeOpen->failureCode());
        $this->assertFalse($beforeOpen->isWithinOperatingHours);
        $this->assertTrue($atOpen->isValid());
        $this->assertTrue($atLatest->isValid());
        $this->assertSame('2030-06-11 00:45', $atLatest->window?->operationalEnd->format('Y-m-d H:i'));
        $this->assertSame('OUTSIDE_START_WINDOW', $afterLatest->failureCode());

        $hours->update(['is_closed' => true]);
        $closed = $service->validateCandidate($movie, $room->fresh(), '2030-06-10', '18:00', presentationFormatId: $this->presentationFormat->id);
        $this->assertSame('CINEMA_CLOSED', $closed->failureCode());

        $hours->update(['is_closed' => false]);
        $this->existing($movie, $room, ['show_time' => '18:00:00']);
        $conflict = $service->validateCandidate($movie, $room->fresh(), '2030-06-10', '18:30', presentationFormatId: $this->presentationFormat->id);
        $this->assertSame('ROOM_CONFLICT', $conflict->failureCode());
        $this->assertFalse($conflict->isConflictFree);
    }

    public function test_http_create_rejects_past_exact_now_cancelled_and_finished_without_inserting(): void
    {
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2030-06-10 20:33:00', 'Asia/Ho_Chi_Minh'));
        $movie = $this->movie(90);
        $room = $this->rooms['P01'];
        $admin = $this->userWithRole('admin');

        foreach (['20:32', '20:33'] as $time) {
            $this->actingAs($admin)->post(route('admin.showtimes.store'), $this->payload($movie, $room, ['show_time' => $time]))
                ->assertSessionHasErrors('show_time');
        }
        foreach (['cancelled', 'finished'] as $status) {
            $this->actingAs($admin)->post(route('admin.showtimes.store'), $this->payload($movie, $room, [
                'show_time' => '20:34',
                'status' => $status,
            ]))->assertSessionHasErrors('status');
        }

        $this->assertDatabaseCount('showtimes', 0);
        $this->actingAs($admin)->post(route('admin.showtimes.store'), $this->payload($movie, $room, ['show_time' => '20:34']))
            ->assertRedirect(route('admin.showtimes.index'))
            ->assertSessionHasNoErrors();
        $this->assertDatabaseHas('showtimes', ['status' => 'active', 'show_time' => '20:34:00']);
    }

    public function test_only_persisted_active_upcoming_showtime_can_reschedule(): void
    {
        $service = app(ShowtimeScheduleService::class);
        $movie = $this->movie(90);
        $room = $this->rooms['P01'];
        $payload = $this->payload($movie, $room, ['show_time' => '21:00']);

        foreach ([
            ['active', '2030-06-10 18:30:00'],
            ['active', '2030-06-10 20:00:00'],
            ['cancelled', '2030-06-10 17:00:00'],
            ['finished', '2030-06-10 17:00:00'],
        ] as [$status, $now]) {
            $showtime = $this->existing($movie, $room, [
                'show_date' => '2030-06-10',
                'show_time' => '18:00:00',
                'status' => $status,
            ]);
            CarbonImmutable::setTestNow(CarbonImmutable::parse($now, 'Asia/Ho_Chi_Minh'));

            try {
                $service->reschedule($showtime, $payload);
                $this->fail("{$status} / {$now} must not be mutable.");
            } catch (ShowtimeScheduleException $exception) {
                $this->assertSame('SHOWTIME_NOT_MUTABLE', $exception->failureCode);
            }
            $showtime->delete();
        }
    }

    public function test_update_to_past_rolls_back_and_valid_future_update_succeeds(): void
    {
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2030-06-10 17:00:00', 'Asia/Ho_Chi_Minh'));
        $movie = $this->movie(90);
        $room = $this->rooms['P01'];
        $showtime = $this->existing($movie, $room, ['show_time' => '18:00:00']);
        $admin = $this->userWithRole('admin');

        $this->actingAs($admin)->put(route('admin.showtimes.update', $showtime), $this->payload($movie, $room, ['show_time' => '16:59']))
            ->assertSessionHasErrors('show_time');
        $this->assertSame('18:00:00', $showtime->fresh()->show_time);

        $this->actingAs($admin)->put(route('admin.showtimes.update', $showtime), $this->payload($movie, $room, ['show_time' => '19:00']))
            ->assertRedirect(route('admin.showtimes.index'))
            ->assertSessionHasNoErrors();
        $this->assertSame('19:00:00', $showtime->fresh()->show_time);
    }

    public function test_any_expired_or_cancelled_booking_history_locks_structural_fields_but_allows_no_op(): void
    {
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2030-06-10 12:00:00', 'Asia/Ho_Chi_Minh'));
        foreach (['expired', 'cancelled'] as $status) {
            $movie = $this->movie(90);
            $room = $this->rooms['P01'];
            $showtime = $this->existing($movie, $room, ['show_date' => '2030-06-1'.($status === 'expired' ? '0' : '1')]);
            $booking = $this->booking($showtime, ['booking_status' => $status, 'payment_status' => 'failed']);
            $service = app(ShowtimeScheduleService::class);

            $service->reschedule($showtime, $this->payload($movie, $room, [
                'show_date' => $showtime->show_date->format('Y-m-d'),
                'show_time' => '18:00',
            ]));

            try {
                $service->reschedule($showtime, $this->payload($movie, $room, [
                    'show_date' => $showtime->show_date->format('Y-m-d'),
                    'show_time' => '19:00',
                ]));
                $this->fail('Historical booking must lock structural scheduling fields.');
            } catch (ShowtimeScheduleException $exception) {
                $this->assertSame('SHOWTIME_HAS_BOOKING_HISTORY', $exception->failureCode);
            }
            $this->assertSame($showtime->id, $booking->fresh()->showtime_id);
            $this->assertSame('18:00:00', $showtime->fresh()->show_time);
        }
    }

    public function test_active_hold_and_retained_payment_states_are_untouched_when_reschedule_is_rejected(): void
    {
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2030-06-10 12:00:00', 'Asia/Ho_Chi_Minh'));
        foreach ([Payment::STATUS_PROCESSING, Payment::STATUS_UNRESOLVED, Payment::STATUS_REVIEW] as $index => $paymentStatus) {
            $movie = $this->movie(90);
            $room = $this->rooms['P01'];
            $date = '2030-06-'.str_pad((string) (12 + $index), 2, '0', STR_PAD_LEFT);
            $showtime = $this->existing($movie, $room, ['show_date' => $date]);
            $booking = $this->booking($showtime);
            $seat = Seat::query()->where('room_id', $room->id)->orderBy('id')->firstOrFail();
            $bookingSeat = BookingSeat::query()->create([
                'booking_id' => $booking->id,
                'showtime_id' => $showtime->id,
                'seat_id' => $seat->id,
                'active_lock_key' => BookingSeat::ACTIVE_LOCK_KEY,
                'price' => 80_000,
            ]);
            $payment = Payment::createForProvider('vnpay', [
                'booking_id' => $booking->id,
                'payment_method' => 'vnpay',
                'amount' => 80_000,
                'status' => $paymentStatus,
            ]);

            $this->assertHistoryRejects($showtime, $movie, $room, $date);
            $this->assertSame(BookingSeat::ACTIVE_LOCK_KEY, $bookingSeat->fresh()->active_lock_key);
            $this->assertSame($paymentStatus, $payment->fresh()->status);
            $this->assertSame('pending_payment', $booking->fresh()->booking_status);
        }
    }

    public function test_paid_and_printed_ticket_history_is_untouched_when_reschedule_is_rejected(): void
    {
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2030-06-10 12:00:00', 'Asia/Ho_Chi_Minh'));
        $movie = $this->movie(90);
        $room = $this->rooms['P01'];
        $showtime = $this->existing($movie, $room);
        $booking = $this->booking($showtime, ['booking_status' => 'paid', 'payment_status' => 'paid']);
        $seat = Seat::query()->where('room_id', $room->id)->orderBy('id')->firstOrFail();
        $bookingSeat = BookingSeat::query()->create([
            'booking_id' => $booking->id,
            'showtime_id' => $showtime->id,
            'seat_id' => $seat->id,
            'active_lock_key' => null,
            'price' => 80_000,
        ]);
        $payment = Payment::createForProvider('vnpay', [
            'booking_id' => $booking->id,
            'payment_method' => 'vnpay',
            'amount' => 80_000,
            'status' => Payment::STATUS_SUCCESS,
            'verified_at' => now(),
            'paid_at' => now(),
        ]);
        $ticket = AdmissionTicket::query()->where('booking_seat_id', $bookingSeat->id)->firstOrFail();
        $ticket->forceFill(['print_count' => 1, 'last_printed_at' => now()])->save();

        $this->assertHistoryRejects($showtime, $movie, $room, '2030-06-10');
        $this->assertSame('paid', $booking->fresh()->booking_status);
        $this->assertSame(Payment::STATUS_SUCCESS, $payment->fresh()->status);
        $this->assertSame(1, $ticket->fresh()->print_count);
        $this->assertSame($showtime->id, $bookingSeat->fresh()->showtime_id);
    }

    public function test_generic_update_cannot_cancel_or_finish_and_cancelled_slot_remains_reusable(): void
    {
        $movie = $this->movie(90);
        $room = $this->rooms['P01'];
        $showtime = $this->existing($movie, $room);
        $admin = $this->userWithRole('admin');

        foreach (['cancelled', 'finished'] as $status) {
            $this->actingAs($admin)->put(route('admin.showtimes.update', $showtime), $this->payload($movie, $room, ['status' => $status]))
                ->assertSessionHasErrors('status');
        }
        $this->assertSame('active', $showtime->fresh()->status);

        $showtime->forceFill(['status' => 'cancelled'])->save();
        $this->actingAs($admin)->post(route('admin.showtimes.store'), $this->payload($movie, $room))
            ->assertRedirect(route('admin.showtimes.index'))
            ->assertSessionHasNoErrors();
        $this->assertDatabaseCount('showtimes', 2);
        $this->assertSame('cancelled', $showtime->fresh()->status);
    }

    public function test_booked_showtime_ui_explains_the_lock_and_uses_the_room_cinema_timezone_label(): void
    {
        $this->cinema->update(['timezone' => 'Pacific/Honolulu']);
        $movie = $this->movie(90);
        $room = $this->rooms['P01']->fresh();
        $showtime = $this->existing($movie, $room);
        $this->booking($showtime, ['booking_status' => 'expired']);
        $admin = $this->userWithRole('admin');

        $this->actingAs($admin)->get(route('admin.showtimes.edit', $showtime))
            ->assertOk()
            ->assertSee('Suất chiếu đã phát sinh đơn đặt vé nên không thể thay đổi phim, phòng, ngày hoặc giờ chiếu.')
            ->assertDontSee('name="movie_id"', false);

        $this->actingAs($admin)->get(route('admin.showtimes.index'))
            ->assertOk()
            ->assertSee('Suất chiếu đã có lịch sử đặt vé')
            ->assertDontSee('data-showtime-edit-action', false);

        $this->actingAs($admin)->get(route('admin.showtimes.create'))
            ->assertOk()
            ->assertSee('data-timezone="Pacific/Honolulu"', false);
    }

    public function test_final_transaction_rechecks_conflicts_and_history_after_locks(): void
    {
        $service = app(ShowtimeScheduleService::class);
        $movie = $this->movie(90);
        $room = $this->rooms['P01'];
        $transactionLevel = 0;

        $service->schedule($this->payload($movie, $room), function () use (&$transactionLevel): void {
            $transactionLevel = DB::transactionLevel();
        });
        try {
            $service->schedule($this->payload($movie, $room));
            $this->fail('Only one same-room writer may commit the interval.');
        } catch (ShowtimeScheduleException $exception) {
            $this->assertSame('ROOM_CONFLICT', $exception->failureCode);
        }

        $this->assertGreaterThan(0, $transactionLevel);
        $this->assertDatabaseCount('showtimes', 1);
    }

    public function test_booking_history_query_runs_after_room_and_showtime_locks(): void
    {
        $service = app(ShowtimeScheduleService::class);
        $movie = $this->movie(90);
        $room = $this->rooms['P01'];
        $showtime = $this->existing($movie, $room);
        $this->booking($showtime, ['booking_status' => 'expired']);
        $queries = [];
        DB::listen(function ($query) use (&$queries): void {
            $queries[] = $query->sql;
        });

        try {
            $service->reschedule($showtime, $this->payload($movie, $room, ['show_time' => '19:00']));
            $this->fail('Booking history must reject structural reschedule.');
        } catch (ShowtimeScheduleException $exception) {
            $this->assertSame('SHOWTIME_HAS_BOOKING_HISTORY', $exception->failureCode);
        }

        $roomLock = collect($queries)->search(fn (string $sql) => str_contains($sql, 'from "rooms"') && str_contains($sql, 'where "id" in'));
        $showtimeLock = collect($queries)->search(fn (string $sql) => str_contains($sql, 'from "showtimes"') && str_contains($sql, 'where "showtimes"."id"'));
        $historyExists = collect($queries)->search(fn (string $sql) => str_contains($sql, 'from "bookings"') && str_contains($sql, 'exists'));

        $this->assertIsInt($roomLock);
        $this->assertIsInt($showtimeLock);
        $this->assertIsInt($historyExists);
        $this->assertLessThan($showtimeLock, $roomLock);
        $this->assertLessThan($historyExists, $showtimeLock);
        $this->assertSame('18:00:00', $showtime->fresh()->show_time);
    }

    public function test_deadlock_retry_replays_the_complete_mutation_transaction(): void
    {
        $attempts = 0;
        $movie = $this->movie(90);
        $room = $this->rooms['P01'];

        // RefreshDatabase wraps each test in an outer transaction. End that test-only
        // wrapper so this assertion exercises the production top-level retry boundary.
        DB::commit();
        try {
            app(ShowtimeScheduleService::class)->schedule(
                $this->payload($movie, $room),
                function () use (&$attempts): void {
                    $attempts++;
                    if ($attempts === 1) {
                        throw new QueryException(
                            'sqlite',
                            'forced scheduling deadlock',
                            [],
                            new PDOException('Deadlock found when trying to get lock'),
                        );
                    }
                },
            );

            $this->assertSame(2, $attempts);
            $this->assertDatabaseCount('showtimes', 1);
        } finally {
            DB::table('showtimes')->delete();
            $integrityMigration = require database_path('migrations/2026_08_14_200000_harden_room_layout_history_integrity.php');
            foreach (['room_layout_cells_prevent_immutable_insert', 'room_layout_cells_prevent_immutable_update', 'room_layout_cells_prevent_immutable_delete'] as $trigger) {
                DB::unprepared("DROP TRIGGER IF EXISTS {$trigger}");
            }
            DB::table('room_layout_cells')->delete();
            DB::table('room_layouts')->delete();
            DB::table('seats')->delete();
            DB::table('cinema_pricing_rules')->delete();
            DB::table('rooms')->delete();
            DB::table('movies')->delete();
            DB::table('presentation_formats')->delete();
            $integrityMigration->up();
            DB::beginTransaction();
        }
    }

    private function booking(Showtime $showtime, array $overrides = []): Booking
    {
        return Booking::query()->create([
            'customer_email' => 'phase3b@example.test',
            'showtime_id' => $showtime->id,
            'booking_code' => 'P3B-'.str()->upper(str()->random(12)),
            'total_amount' => 80_000,
            'seat_subtotal' => 80_000,
            'food_subtotal' => 0,
            'gross_amount' => 80_000,
            'promotion_discount_amount' => 0,
            'currency' => 'VND',
            'payment_status' => 'unpaid',
            'booking_status' => 'pending_payment',
            'expires_at' => now()->addMinutes(15),
            ...$overrides,
        ]);
    }

    private function assertHistoryRejects(Showtime $showtime, $movie, $room, string $date): void
    {
        try {
            app(ShowtimeScheduleService::class)->reschedule($showtime, $this->payload($movie, $room, [
                'show_date' => $date,
                'show_time' => '19:00',
            ]));
            $this->fail('Booking history must reject structural reschedule.');
        } catch (ShowtimeScheduleException $exception) {
            $this->assertSame('SHOWTIME_HAS_BOOKING_HISTORY', $exception->failureCode);
        }

        $this->assertSame('18:00:00', $showtime->fresh()->show_time);
    }
}
