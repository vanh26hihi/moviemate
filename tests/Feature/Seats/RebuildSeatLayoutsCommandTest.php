<?php

namespace Tests\Feature\Seats;

use App\Models\Cinema;
use App\Models\Room;
use App\Models\Seat;
use App\Models\User;
use App\Services\CinemaContext;
use App\Services\RoomLayoutService;
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
                'total_seats' => 0,
                'status' => 'active',
            ]);
        }
        Room::query()->create([
            'cinema_id' => $this->cinema->id,
            'code' => 'ARCH-12',
            'name' => 'Phòng lưu trữ',
            'room_type' => '2D',
            'total_seats' => 0,
            'status' => 'inactive',
        ]);
    }

    public function test_without_force_and_dry_run_never_write_database_and_show_counts(): void
    {
        $room = Room::query()->where('code', 'P01')->firstOrFail();
        Seat::query()->create(['room_id' => $room->id, 'row' => 'A', 'number' => 1, 'seat_code' => 'A1', 'type' => 'normal', 'status' => 'active']);

        $this->artisan('moviemate:rebuild-seat-layouts')
            ->expectsOutputToContain('Chưa có --force')
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

    public function test_force_builds_three_distinct_published_layouts_and_is_idempotent(): void
    {
        $this->artisan('moviemate:rebuild-seat-layouts', ['--force' => true])->assertSuccessful();

        $this->assertDatabaseCount('room_layouts', 3);
        $this->assertDatabaseCount('seats', 332);
        $this->assertSame(132, Room::query()->where('code', 'P01')->sole()->seats()->count());
        $this->assertSame(96, Room::query()->where('code', 'P02')->sole()->seats()->count());
        $this->assertSame(104, Room::query()->where('code', 'P03')->sole()->seats()->count());
        $this->assertSame(3, DB::table('room_layouts')->where('status', 'published')->count());
        $this->assertSame(0, DB::table('room_layouts')->where('status', 'draft')->count());
        $this->assertSame(2, DB::table('seats')->where('status', 'maintenance')->count());

        $dimensions = DB::table('room_layouts')->join('rooms', 'rooms.id', '=', 'room_layouts.room_id')
            ->orderBy('rooms.code')->get(['rooms.code', 'room_layouts.rows', 'room_layouts.columns'])
            ->map(fn ($row) => [$row->code, $row->rows, $row->columns])->all();
        $this->assertSame([['P01', 11, 13], ['P02', 8, 14], ['P03', 9, 13]], $dimensions);

        $this->artisan('moviemate:rebuild-seat-layouts', ['--force' => true])->assertSuccessful();
        $this->assertDatabaseCount('room_layouts', 3);
        $this->assertDatabaseCount('seats', 332);
    }

    public function test_generated_couple_groups_are_complete_and_codes_are_unique_per_room(): void
    {
        $this->artisan('moviemate:rebuild-seat-layouts', ['--force' => true])->assertSuccessful();

        $invalidPairs = DB::table('seats')->where('type', 'couple')
            ->select('room_id', 'pair_code', DB::raw('count(*) as aggregate'))
            ->groupBy('room_id', 'pair_code')->having('aggregate', '!=', 2)->count();
        $this->assertSame(0, $invalidPairs);
        $this->assertSame(0, DB::table('seats')->where('type', 'couple')->whereNull('pair_code')->count());
        $this->assertSame(0, DB::table('seats')->select('room_id', 'seat_code', DB::raw('count(*) as aggregate'))
            ->groupBy('room_id', 'seat_code')->having('aggregate', '>', 1)->count());
    }

    public function test_room_12_and_preserved_entities_are_untouched(): void
    {
        $archive = Room::query()->where('code', 'ARCH-12')->firstOrFail();
        Seat::query()->create(['room_id' => $archive->id, 'row' => 'Z', 'number' => 1, 'seat_code' => 'Z1', 'type' => 'normal', 'status' => 'active']);
        $user = User::factory()->create();
        DB::table('genres')->insert(['name' => 'Preserved', 'slug' => 'preserved', 'created_at' => now(), 'updated_at' => now()]);

        $this->artisan('moviemate:rebuild-seat-layouts', ['--force' => true])->assertSuccessful();

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

        Room::query()->create(['cinema_id' => $this->cinema->id, 'code' => 'P03', 'name' => 'Phòng 3', 'room_type' => '2D', 'total_seats' => 0, 'status' => 'inactive']);
        $this->artisan('moviemate:rebuild-seat-layouts', ['--force' => true])->assertFailed();
        $this->assertDatabaseCount('room_layouts', 0);
    }

    public function test_force_deletes_only_scoped_booking_payment_showtime_and_seat_dependencies(): void
    {
        $movieId = DB::table('movies')->insertGetId([
            'title' => 'Legacy', 'slug' => 'legacy', 'duration' => 90, 'age_rating' => 'P',
            'status' => 'now_showing', 'created_at' => now(), 'updated_at' => now(),
        ]);
        $room = Room::query()->where('code', 'P01')->firstOrFail();
        $seat = Seat::query()->create(['room_id' => $room->id, 'row' => 'A', 'number' => 1, 'seat_code' => 'A1', 'type' => 'normal', 'status' => 'active']);
        $showtimeId = DB::table('showtimes')->insertGetId([
            'movie_id' => $movieId, 'cinema_id' => $this->cinema->id, 'room_id' => $room->id,
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

        $this->artisan('moviemate:rebuild-seat-layouts', ['--force' => true])->assertSuccessful();
        $this->assertDatabaseMissing('showtimes', ['id' => $showtimeId]);
        $this->assertDatabaseMissing('bookings', ['id' => $bookingId]);
        $this->assertDatabaseMissing('payments', ['booking_id' => $bookingId]);
        $this->assertDatabaseMissing('booking_seats', ['booking_id' => $bookingId]);
        $this->assertDatabaseHas('movies', ['id' => $movieId]);
        $this->assertDatabaseHas('rooms', ['id' => $room->id]);
    }

    public function test_failure_during_rebuild_rolls_back_every_delete(): void
    {
        $room = Room::query()->where('code', 'P01')->firstOrFail();
        $seat = Seat::query()->create(['room_id' => $room->id, 'row' => 'A', 'number' => 1, 'seat_code' => 'A1', 'type' => 'normal', 'status' => 'active']);
        $mock = \Mockery::mock(RoomLayoutService::class);
        $mock->shouldReceive('rebuildDefaultLayouts')->once()->andThrow(new \RuntimeException('Injected rebuild failure'));
        $this->app->instance(RoomLayoutService::class, $mock);

        $this->artisan('moviemate:rebuild-seat-layouts', ['--force' => true])
            ->expectsOutputToContain('Injected rebuild failure')->assertFailed();

        $this->assertDatabaseHas('seats', ['id' => $seat->id, 'seat_code' => 'A1']);
        $this->assertDatabaseCount('room_layouts', 0);
        $this->assertDatabaseCount('seat_types', 0);
    }
}
