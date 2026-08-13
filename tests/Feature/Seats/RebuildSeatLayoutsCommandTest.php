<?php

namespace Tests\Feature\Seats;

use App\Models\Cinema;
use App\Models\Room;
use App\Models\Seat;
use App\Models\User;
use App\Services\CinemaContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class RebuildSeatLayoutsCommandTest extends TestCase
{
    use RefreshDatabase;

    private Cinema $cinema;

    protected function setUp(): void
    {
        parent::setUp();
        $this->cinema = Cinema::query()->where('canonical_key', CinemaContext::CANONICAL_KEY)->firstOrFail();
        foreach (['P01', 'P02', 'P03'] as $index => $code) {
            Room::query()->create([
                'cinema_id' => $this->cinema->id,
                'code' => $code,
                'name' => 'Phòng '.($index + 1),
                'room_type' => '2D',
                'width_mm' => 8_000,
                'length_mm' => 10_000,
                'status' => 'active',
            ]);
        }
        Room::query()->create([
            'cinema_id' => $this->cinema->id,
            'code' => 'ARCH-12',
            'name' => 'Phòng lưu trữ',
            'room_type' => '2D',
            'width_mm' => 8_000,
            'length_mm' => 10_000,
            'status' => 'inactive',
        ]);
    }

    public function test_without_force_and_dry_run_never_write_database_and_show_counts(): void
    {
        $room = Room::query()->where('code', 'P01')->firstOrFail();
        Seat::query()->create(['room_id' => $room->id, 'row' => 'A', 'number' => 1, 'seat_code' => 'A1', 'type' => 'normal', 'status' => 'active']);

        $this->artisan('moviemate:rebuild-seat-layouts')
            ->expectsOutputToContain('Chế độ chỉ đọc')
            ->assertSuccessful();
        $this->assertDatabaseCount('seats', 1);
        $this->assertDatabaseCount('room_layouts', 0);

        $this->artisan('moviemate:rebuild-seat-layouts', ['--dry-run' => true])
            ->expectsOutputToContain('DRY-RUN')
            ->expectsOutputToContain('332')
            ->assertSuccessful();
        $this->assertDatabaseCount('seats', 1);
        $this->assertDatabaseCount('room_layouts', 0);
    }

    public function test_force_mode_is_retired_and_never_creates_or_deletes_seats(): void
    {
        $room = Room::query()->where('code', 'P01')->firstOrFail();
        $seat = Seat::query()->create(['room_id' => $room->id, 'row' => 'A', 'number' => 1, 'seat_code' => 'A1', 'type' => 'normal', 'status' => 'active']);

        $this->artisan('moviemate:rebuild-seat-layouts', ['--force' => true])
            ->expectsOutputToContain('ngừng hoạt động từ R7')->assertFailed();

        $this->assertDatabaseHas('seats', ['id' => $seat->id, 'seat_code' => 'A1']);
        $this->assertDatabaseCount('room_layouts', 0);
    }

    public function test_initialize_empty_builds_once_and_refuses_to_overwrite_created_history(): void
    {
        $this->artisan('moviemate:rebuild-seat-layouts', ['--initialize-empty' => true])->assertSuccessful();
        $seatIds = Seat::query()->orderBy('id')->pluck('id')->all();
        $this->assertCount(332, $seatIds);
        $this->assertDatabaseCount('room_layouts', 3);

        $this->artisan('moviemate:rebuild-seat-layouts', ['--initialize-empty' => true])
            ->expectsOutputToContain('đã có ghế, sơ đồ hoặc lịch sử')->assertFailed();
        $this->assertSame($seatIds, Seat::query()->orderBy('id')->pluck('id')->all());
        $this->assertDatabaseCount('room_layouts', 3);
    }

    public function test_room_12_and_preserved_entities_are_untouched(): void
    {
        $archive = Room::query()->where('code', 'ARCH-12')->firstOrFail();
        Seat::query()->create(['room_id' => $archive->id, 'row' => 'Z', 'number' => 1, 'seat_code' => 'Z1', 'type' => 'normal', 'status' => 'active']);
        $user = User::factory()->create();
        DB::table('genres')->insert(['name' => 'Preserved', 'slug' => 'preserved', 'created_at' => now(), 'updated_at' => now()]);

        $this->artisan('moviemate:rebuild-seat-layouts', ['--force' => true])->assertFailed();

        $this->assertDatabaseHas('rooms', ['id' => $archive->id, 'status' => 'inactive']);
        $this->assertDatabaseHas('seats', ['room_id' => $archive->id, 'seat_code' => 'Z1']);
        $this->assertDatabaseHas('users', ['id' => $user->id]);
        $this->assertDatabaseHas('genres', ['slug' => 'preserved']);
        $this->assertDatabaseHas('cinemas', ['id' => $this->cinema->id]);
    }

    public function test_missing_canonical_fails_without_writes(): void
    {
        DB::table('cinemas')->where('id', $this->cinema->id)->update(['canonical_key' => null]);
        $this->artisan('moviemate:rebuild-seat-layouts', ['--force' => true])
            ->expectsOutputToContain('Rebuild bị hủy')->assertFailed();
        $this->assertDatabaseCount('room_layouts', 0);
    }

    public function test_multiple_active_primary_cinemas_fail(): void
    {
        Cinema::factory()->create(['status' => 'active', 'is_primary' => true, 'archived_at' => null]);
        $this->artisan('moviemate:rebuild-seat-layouts', ['--force' => true])->assertFailed();
        $this->assertDatabaseCount('room_layouts', 0);
    }

    public function test_missing_or_inactive_required_room_fails(): void
    {
        Room::query()->where('code', 'P03')->delete();
        $this->artisan('moviemate:rebuild-seat-layouts', ['--force' => true])->assertFailed();

        Room::query()->create(['cinema_id' => $this->cinema->id, 'code' => 'P03', 'name' => 'Phòng 3', 'room_type' => '2D', 'status' => 'inactive']);
        $this->artisan('moviemate:rebuild-seat-layouts', ['--force' => true])->assertFailed();
        $this->assertDatabaseCount('room_layouts', 0);
    }

    public function test_force_preserves_booking_payment_showtime_and_seat_dependencies(): void
    {
        $movieId = DB::table('movies')->insertGetId([
            'title' => 'Legacy', 'slug' => 'legacy', 'duration' => 90, 'age_rating' => 'P',
            'status' => 'now_showing', 'created_at' => now(), 'updated_at' => now(),
        ]);
        $room = Room::query()->where('code', 'P01')->firstOrFail();
        $format = $this->presentationFormatFixture($movieId, $room);
        $seat = Seat::query()->create(['room_id' => $room->id, 'row' => 'A', 'number' => 1, 'seat_code' => 'A1', 'type' => 'normal', 'status' => 'active']);
        $layout = $this->publishedRoomLayoutFixture($room);
        $showtimeId = DB::table('showtimes')->insertGetId([
            'movie_id' => $movieId, 'cinema_id' => $this->cinema->id, 'room_id' => $room->id,
            'room_layout_id' => $layout->id,
            'presentation_format_id' => $format->id,
            'show_date' => now()->toDateString(), 'show_time' => '10:00:00', 'price' => 50000,
            'status' => 'active', 'created_at' => now(), 'updated_at' => now(),
        ]);
        $bookingId = DB::table('bookings')->insertGetId([
            'showtime_id' => $showtimeId, 'booking_code' => 'LEGACY-1', 'total_amount' => 50000,
            'payment_status' => 'paid', 'booking_status' => 'paid', 'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('booking_seats')->insert([
            'booking_id' => $bookingId, 'showtime_id' => $showtimeId, 'seat_id' => $seat->id,
            'active_lock_key' => 'ACTIVE', 'price' => 50000, 'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('payments')->insert([
            'booking_id' => $bookingId, 'payment_method' => 'fake', 'amount' => 50000,
            'status' => 'success', 'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->artisan('moviemate:rebuild-seat-layouts', ['--force' => true])->assertFailed();
        $this->assertDatabaseHas('showtimes', ['id' => $showtimeId]);
        $this->assertDatabaseHas('bookings', ['id' => $bookingId]);
        $this->assertDatabaseHas('payments', ['booking_id' => $bookingId]);
        $this->assertDatabaseHas('booking_seats', ['booking_id' => $bookingId]);
        $this->assertDatabaseHas('movies', ['id' => $movieId]);
        $this->assertDatabaseHas('rooms', ['id' => $room->id]);
    }

    public function test_retired_force_does_not_resolve_or_call_layout_rebuild_service(): void
    {
        $room = Room::query()->where('code', 'P01')->firstOrFail();
        $seat = Seat::query()->create(['room_id' => $room->id, 'row' => 'A', 'number' => 1, 'seat_code' => 'A1', 'type' => 'normal', 'status' => 'active']);
        $this->artisan('moviemate:rebuild-seat-layouts', ['--force' => true])
            ->expectsOutputToContain('ngừng hoạt động từ R7')->assertFailed();

        $this->assertDatabaseHas('seats', ['id' => $seat->id, 'seat_code' => 'A1']);
        $this->assertDatabaseCount('room_layouts', 0);
        $this->assertDatabaseCount('seat_types', 0);
    }
}
