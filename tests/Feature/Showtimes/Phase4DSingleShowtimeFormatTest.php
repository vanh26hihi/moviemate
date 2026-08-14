<?php

namespace Tests\Feature\Showtimes;

use App\Exceptions\ShowtimeScheduleException;
use App\Models\Booking;
use App\Models\Movie;
use App\Models\PresentationFormat;
use App\Models\Room;
use App\Models\Showtime;
use App\Models\User;
use App\Services\MoviePresentationFormatService;
use App\Services\PresentationFormatManagementService;
use App\Services\RoomPresentationCapabilityService;
use App\Services\ShowtimeScheduleService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class Phase4DSingleShowtimeFormatTest extends ShowtimeTestCase
{
    protected bool $prepareSingleShowtimeFormats = true;

    public function test_single_create_requires_and_persists_an_active_compatible_format(): void
    {
        $movie = $this->movie(90);
        $room = $this->rooms['P01'];
        $threeD = $this->format('3D');
        $movie->supportedPresentationFormats()->attach($threeD);
        $room->presentationCapabilities()->attach($threeD);
        $admin = $this->userWithRole('admin');

        $this->actingAs($admin)->post(route('admin.showtimes.store'), [
            ...$this->payload($movie, $room),
            'presentation_format_id' => $threeD->id,
        ])->assertRedirect(route('admin.showtimes.index'))->assertSessionHasNoErrors();

        $this->assertDatabaseHas('showtimes', [
            'movie_id' => $movie->id,
            'room_id' => $room->id,
            'presentation_format_id' => $threeD->id,
        ]);

        $missing = $this->payload($movie, $this->rooms['P02'], ['show_time' => '20:00']);
        unset($missing['presentation_format_id']);
        $this->actingAs($admin)->post(route('admin.showtimes.store'), $missing)
            ->assertSessionHasErrors(['presentation_format_id' => 'Vui lòng chọn định dạng trình chiếu.']);
        $this->assertDatabaseCount('showtimes', 1);
    }

    public function test_format_failures_are_stable_and_write_nothing(): void
    {
        $movie = $this->movie(90);
        $room = $this->rooms['P01'];
        $threeD = $this->format('3D');

        $this->assertScheduleFailure('MOVIE_FORMAT_UNSUPPORTED', $movie, $room, $threeD);

        $movie->supportedPresentationFormats()->attach($threeD);
        $this->assertScheduleFailure('ROOM_FORMAT_UNSUPPORTED', $movie, $room, $threeD);

        $room->presentationCapabilities()->attach($threeD);
        $threeD->update(['is_active' => false]);
        $this->assertScheduleFailure('PRESENTATION_FORMAT_INACTIVE', $movie, $room, $threeD);
        $this->assertDatabaseCount('showtimes', 0);
    }

    public function test_room_type_never_overrides_explicit_capabilities(): void
    {
        $movie = $this->movie(90);
        $threeD = $this->format('3D');
        $movie->supportedPresentationFormats()->attach($threeD);

        $imax = $this->rooms['P01'];
        $imax->update(['room_type' => 'IMAX']);
        $this->assertScheduleFailure('ROOM_FORMAT_UNSUPPORTED', $movie, $imax, $threeD);

        $standard = $this->rooms['P02'];
        $standard->update(['room_type' => 'STANDARD']);
        $standard->presentationCapabilities()->attach($threeD);
        $showtime = app(ShowtimeScheduleService::class)->schedule([
            ...$this->payload($movie, $standard),
            'presentation_format_id' => $threeD->id,
        ]);

        $this->assertSame($threeD->id, $showtime->presentation_format_id);
    }

    public function test_update_revalidates_full_tuple_and_format_is_booking_protected(): void
    {
        $movieA = $this->movie(90);
        $movieB = $this->movie(90);
        $roomA = $this->rooms['P01'];
        $roomB = $this->rooms['P02'];
        $threeD = $this->format('3D');
        $movieA->supportedPresentationFormats()->attach($threeD);
        $roomA->presentationCapabilities()->attach($threeD);
        $showtime = $this->existing($movieA, $roomA);

        app(ShowtimeScheduleService::class)->reschedule($showtime, [
            ...$this->payload($movieA, $roomA),
            'presentation_format_id' => $threeD->id,
        ]);
        $this->assertSame($threeD->id, $showtime->fresh()->presentation_format_id);

        $this->assertRescheduleFailure('MOVIE_FORMAT_UNSUPPORTED', $showtime->fresh(), [
            ...$this->payload($movieB, $roomA),
            'presentation_format_id' => $threeD->id,
        ]);
        $this->assertRescheduleFailure('ROOM_FORMAT_UNSUPPORTED', $showtime->fresh(), [
            ...$this->payload($movieA, $roomB),
            'presentation_format_id' => $threeD->id,
        ]);

        $this->booking($showtime->fresh());
        $this->assertRescheduleFailure('SHOWTIME_HAS_BOOKING_HISTORY', $showtime->fresh(), [
            ...$this->payload($movieA, $roomA),
            'presentation_format_id' => $this->presentationFormat->id,
        ]);
        app(ShowtimeScheduleService::class)->reschedule($showtime->fresh(), [
            ...$this->payload($movieA, $roomA),
            'presentation_format_id' => $threeD->id,
        ]);
        $this->assertSame($threeD->id, $showtime->fresh()->presentation_format_id);
    }

    public function test_authoritative_candidate_validation_no_longer_accepts_a_missing_format(): void
    {
        $movie = $this->movie(90);
        $room = $this->rooms['P01'];
        $result = app(ShowtimeScheduleService::class)->validateCandidate(
            $movie,
            $room,
            '2030-06-10',
            '19:00',
        );

        $this->assertFalse($result->isValid());
        $this->assertSame('PRESENTATION_FORMAT_REQUIRED', $result->failureCode());
        $this->assertDatabaseCount('showtimes', 0);
    }

    public function test_preview_returns_format_metadata_and_compatibility_precedes_window_failures(): void
    {
        $movie = $this->movie(90);
        $room = $this->rooms['P01'];
        $threeD = $this->format('3D');
        $admin = $this->userWithRole('admin');

        $this->actingAs($admin)->postJson(route('admin.showtimes.preview'), [
            ...$this->payload($movie, $room, ['show_date' => '2020-01-01']),
            'presentation_format_id' => $threeD->id,
        ])->assertOk()->assertJson([
            'valid' => false,
            'code' => 'MOVIE_FORMAT_UNSUPPORTED',
            'presentation_format' => ['id' => $threeD->id, 'code' => '3D', 'name' => '3D'],
        ]);

        $movie->supportedPresentationFormats()->attach($threeD);
        $this->actingAs($admin)->postJson(route('admin.showtimes.preview'), [
            ...$this->payload($movie, $room),
            'presentation_format_id' => $threeD->id,
        ])->assertOk()->assertJson(['valid' => false, 'code' => 'ROOM_FORMAT_UNSUPPORTED']);

        $room->presentationCapabilities()->attach($threeD);
        DB::flushQueryLog();
        DB::enableQueryLog();
        $this->actingAs($admin)->postJson(route('admin.showtimes.preview'), [
            ...$this->payload($movie, $room, ['show_time' => '23:30']),
            'presentation_format_id' => $threeD->id,
        ])->assertOk()->assertJson([
            'valid' => true,
            'presentation_format' => ['id' => $threeD->id, 'code' => '3D', 'name' => '3D'],
            'window' => [
                'start_display' => '10/06/2030 23:30',
                'end_display' => '11/06/2030 01:00',
                'room_ready_display' => '11/06/2030 01:15',
            ],
        ]);
        $previewQueryCount = count(DB::getQueryLog());
        DB::disableQueryLog();
        $this->assertLessThanOrEqual(25, $previewQueryCount);

        $threeD->update(['is_active' => false]);
        $this->actingAs($admin)->postJson(route('admin.showtimes.preview'), [
            ...$this->payload($movie, $room),
            'presentation_format_id' => $threeD->id,
        ])->assertOk()->assertJson(['valid' => false, 'code' => 'PRESENTATION_FORMAT_INACTIVE']);
    }

    public function test_green_preview_does_not_survive_official_movie_support_removal(): void
    {
        [$movie, $room, $format, $payload, $admin] = $this->compatibleCandidate();
        $this->actingAs($admin)->postJson(route('admin.showtimes.preview'), $payload)
            ->assertOk()->assertJson(['valid' => true]);

        app(MoviePresentationFormatService::class)->update(
            $movie,
            [],
            [],
            [$this->presentationFormat->id],
        );

        $this->actingAs($admin)->post(route('admin.showtimes.store'), $payload)
            ->assertSessionHasErrors(['presentation_format_id' => 'Phim không hỗ trợ định dạng trình chiếu đã chọn.']);
        $this->assertDatabaseCount('showtimes', 0);
    }

    public function test_green_preview_does_not_survive_official_room_capability_removal(): void
    {
        [$movie, $room, $format, $payload, $admin] = $this->compatibleCandidate();
        $this->actingAs($admin)->postJson(route('admin.showtimes.preview'), $payload)
            ->assertOk()->assertJson(['valid' => true]);

        DB::transaction(function () use ($room): void {
            $locked = Room::query()->whereKey($room->id)->lockForUpdate()->firstOrFail();
            app(RoomPresentationCapabilityService::class)->syncLocked($locked, [$this->presentationFormat->id]);
        });

        $this->actingAs($admin)->post(route('admin.showtimes.store'), $payload)
            ->assertSessionHasErrors(['presentation_format_id' => 'Phòng chiếu không hỗ trợ định dạng trình chiếu đã chọn.']);
        $this->assertDatabaseCount('showtimes', 0);
    }

    public function test_green_preview_does_not_survive_official_format_archive(): void
    {
        [$movie, $room, $format, $payload, $admin] = $this->compatibleCandidate();
        $this->actingAs($admin)->postJson(route('admin.showtimes.preview'), $payload)
            ->assertOk()->assertJson(['valid' => true]);

        app(PresentationFormatManagementService::class)->archive($format, $admin);

        $this->actingAs($admin)->post(route('admin.showtimes.store'), $payload)
            ->assertSessionHasErrors(['presentation_format_id' => 'Định dạng trình chiếu đã chọn hiện không còn hoạt động.']);
        $this->assertDatabaseCount('showtimes', 0);
    }

    public function test_final_save_uses_room_movie_format_order_and_official_writers_cannot_invalidate_commit(): void
    {
        [$movie, $room, $format, $payload, $admin] = $this->compatibleCandidate();
        $queries = [];
        DB::listen(function ($query) use (&$queries): void {
            $queries[] = $query->sql;
        });

        $this->actingAs($admin)->post(route('admin.showtimes.store'), $payload)
            ->assertRedirect(route('admin.showtimes.index'))->assertSessionHasNoErrors();
        $this->assertLessThanOrEqual(34, count($queries));

        $roomLock = collect($queries)->search(fn (string $sql): bool => str_contains($sql, 'from "rooms"') && str_contains($sql, 'where "id"'));
        $movieLock = collect($queries)->search(fn (string $sql): bool => str_contains($sql, 'from "movies"') && str_contains($sql, 'where "movies"."id"'));
        $formatLock = collect($queries)->search(fn (string $sql): bool => str_contains($sql, 'from "presentation_formats"') && str_contains($sql, 'where "presentation_formats"."id"'));
        $movieCompatibility = collect($queries)->search(fn (string $sql): bool => str_contains($sql, 'from "presentation_formats" inner join "movie_presentation_formats"'));
        $roomCompatibility = collect($queries)->search(fn (string $sql): bool => str_contains($sql, 'from "presentation_formats" inner join "room_presentation_capabilities"'));

        foreach ([$roomLock, $movieLock, $formatLock, $movieCompatibility, $roomCompatibility] as $position) {
            $this->assertIsInt($position);
        }
        $this->assertLessThan($movieLock, $roomLock);
        $this->assertLessThan($formatLock, $movieLock);
        $this->assertLessThan($movieCompatibility, $formatLock);
        $this->assertLessThan($roomCompatibility, $formatLock);

        try {
            app(MoviePresentationFormatService::class)->update($movie, [], [], [$this->presentationFormat->id]);
            $this->fail('Official Movie support removal must not invalidate a committed future Showtime.');
        } catch (ValidationException) {
            $this->assertTrue(true);
        }
        try {
            DB::transaction(function () use ($room): void {
                $locked = Room::query()->whereKey($room->id)->lockForUpdate()->firstOrFail();
                app(RoomPresentationCapabilityService::class)->syncLocked($locked, [$this->presentationFormat->id]);
            });
            $this->fail('Official Room capability removal must not invalidate a committed future Showtime.');
        } catch (ValidationException) {
            $this->assertTrue(true);
        }
        try {
            app(PresentationFormatManagementService::class)->archive($format, $admin);
            $this->fail('Official Format archive must not invalidate a committed future Showtime.');
        } catch (ValidationException) {
            $this->assertTrue(true);
        }
        $this->assertDatabaseHas('showtimes', ['presentation_format_id' => $format->id]);
    }

    public function test_format_alone_does_not_change_existing_pricing(): void
    {
        $movie = $this->movie(90);
        $room = $this->rooms['P01'];
        $threeD = $this->format('3D');
        $movie->supportedPresentationFormats()->attach($threeD);
        $room->presentationCapabilities()->attach($threeD);
        $schedule = app(ShowtimeScheduleService::class);

        $twoD = $schedule->schedule($this->payload($movie, $room));
        $twoD->forceFill(['status' => 'cancelled'])->save();
        $threeDShowtime = $schedule->schedule([
            ...$this->payload($movie, $room),
            'presentation_format_id' => $threeD->id,
        ]);

        $this->assertSame((string) $twoD->price, (string) $threeDShowtime->price);
        $this->assertSame((string) $twoD->vip_price, (string) $threeDShowtime->vip_price);
    }

    private function format(string $code): PresentationFormat
    {
        return PresentationFormat::query()->create([
            'code' => $code,
            'name' => $code,
            'is_active' => true,
            'sort_order' => 20,
        ]);
    }

    private function booking(Showtime $showtime): Booking
    {
        return Booking::query()->create([
            'customer_email' => 'phase4d@example.test',
            'showtime_id' => $showtime->id,
            'booking_code' => 'P4D-'.str()->upper(str()->random(12)),
            'total_amount' => 80_000,
            'seat_subtotal' => 80_000,
            'food_subtotal' => 0,
            'gross_amount' => 80_000,
            'promotion_discount_amount' => 0,
            'currency' => 'VND',
            'payment_status' => 'failed',
            'booking_status' => 'expired',
        ]);
    }

    /** @return array{0:Movie,1:Room,2:PresentationFormat,3:array<string,mixed>,4:User} */
    private function compatibleCandidate(): array
    {
        $movie = $this->movie(90);
        $room = $this->rooms['P01'];
        $format = $this->format('3D');
        $movie->supportedPresentationFormats()->attach($format);
        $room->presentationCapabilities()->attach($format);
        $payload = [
            ...$this->payload($movie, $room),
            'presentation_format_id' => $format->id,
        ];

        return [$movie, $room, $format, $payload, $this->userWithRole('admin')];
    }

    private function assertScheduleFailure(string $code, $movie, Room $room, PresentationFormat $format): void
    {
        try {
            app(ShowtimeScheduleService::class)->schedule([
                ...$this->payload($movie, $room),
                'presentation_format_id' => $format->id,
            ]);
            $this->fail("Expected scheduling failure {$code}.");
        } catch (ShowtimeScheduleException $exception) {
            $this->assertSame($code, $exception->failureCode);
        }
    }

    /** @param array<string, mixed> $payload */
    private function assertRescheduleFailure(string $code, Showtime $showtime, array $payload): void
    {
        try {
            app(ShowtimeScheduleService::class)->reschedule($showtime, $payload);
            $this->fail("Expected rescheduling failure {$code}.");
        } catch (ShowtimeScheduleException $exception) {
            $this->assertSame($code, $exception->failureCode);
        }
    }
}
