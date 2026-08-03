<?php

namespace Tests\Feature\Cinema;

use App\Services\CinemaContext;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Tests\TestCase;

class CinemaConsolidationMigrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_consolidation_preserves_ids_counts_and_approved_room_mapping_and_rolls_back(): void
    {
        $migration = $this->migration();
        $migration->down();
        $snapshots = $this->seedApprovedLegacyDataset(withHistory: true);

        $migration->up();

        $canonical = DB::table('cinemas')->where('canonical_key', CinemaContext::CANONICAL_KEY)->sole();
        $this->assertSame('MovieMate Cinema – FPT Polytechnic', $canonical->name);
        $this->assertSame(CinemaContext::SCHOOL_NAME, $canonical->school_name);
        $this->assertSame(CinemaContext::ADDRESS, $canonical->address);
        $this->assertSame(CinemaContext::CITY, $canonical->city);
        $this->assertSame(CinemaContext::COUNTRY, $canonical->country);
        $this->assertEquals(CinemaContext::LATITUDE, $canonical->latitude);
        $this->assertEquals(CinemaContext::LONGITUDE, $canonical->longitude);
        $this->assertNull($canonical->phone);

        $this->assertRoom(9, $canonical->id, 'P01', 'Phòng 1', 'active');
        $this->assertRoom(10, $canonical->id, 'P02', 'Phòng 2', 'active');
        $this->assertRoom(11, $canonical->id, 'P03', 'Phòng 3', 'active');
        $this->assertRoom(12, $canonical->id, 'ARCH-12', 'Phòng 1 (Ngừng hoạt động)', 'inactive');
        $this->assertSame(1, DB::table('cinemas')->where('is_primary', true)->where('status', 'active')->count());
        $this->assertSame(0, DB::table('rooms')->where('cinema_id', '!=', $canonical->id)->count());
        $this->assertSame(0, DB::table('showtimes')->where('cinema_id', '!=', $canonical->id)->count());

        $this->assertSame($snapshots['seat_ids'], DB::table('seats')->orderBy('id')->pluck('id')->all());
        foreach ([9, 10, 11] as $roomId) {
            $this->assertSame(
                $snapshots['seat_ids_by_room'][$roomId],
                DB::table('seats')->where('room_id', $roomId)->orderBy('id')->pluck('id')->all()
            );
        }
        $this->assertSame($snapshots['showtime_ids'], DB::table('showtimes')->orderBy('id')->pluck('id')->all());
        $this->assertSame($snapshots['booking_ids'], DB::table('bookings')->orderBy('id')->pluck('id')->all());
        $this->assertSame($snapshots['payment_ids'], DB::table('payments')->orderBy('id')->pluck('id')->all());
        $this->assertSame($snapshots['booking_seat_ids'], DB::table('booking_seats')->orderBy('id')->pluck('id')->all());
        $this->assertSame($snapshots['booking_rows'], $this->bookingRows());
        $this->assertSame($snapshots['payment_rows'], $this->paymentRows());
        $this->assertSame(363, DB::table('seats')->count());
        $this->assertSame(10, DB::table('showtimes')->count());
        $this->assertSame(18, DB::table('bookings')->count());
        $this->assertSame(18, DB::table('payments')->count());
        $this->assertSame(18, DB::table('booking_seats')->count());
        foreach ([9 => [121, 5, 3], 10 => [121, 2, 2], 11 => [121, 3, 13], 12 => [0, 0, 0]] as $roomId => [$seatCount, $showtimeCount, $bookingCount]) {
            $this->assertSame($seatCount, DB::table('seats')->where('room_id', $roomId)->count());
            $this->assertSame($showtimeCount, DB::table('showtimes')->where('room_id', $roomId)->count());
            $this->assertSame($bookingCount, DB::table('bookings')->join('showtimes', 'showtimes.id', '=', 'bookings.showtime_id')->where('showtimes.room_id', $roomId)->count());
            $this->assertSame($bookingCount, DB::table('payments')->join('bookings', 'bookings.id', '=', 'payments.booking_id')->join('showtimes', 'showtimes.id', '=', 'bookings.showtime_id')->where('showtimes.room_id', $roomId)->count());
        }
        $this->assertSame(4, DB::table('cinema_consolidation_mappings')->where('entity_type', 'room')->count());

        $duplicateCodes = DB::table('rooms')->select('cinema_id', 'code', DB::raw('COUNT(*) as aggregate'))
            ->groupBy('cinema_id', 'code')->having('aggregate', '>', 1)->count();
        $this->assertSame(0, $duplicateCodes);

        $migration->down();

        $this->assertRoom(9, 6, 'P01', 'Phòng 1', 'active');
        $this->assertRoom(10, 6, 'P02', 'Phòng 2', 'active');
        $this->assertRoom(11, 11, 'P01', 'Phòng 1', 'active');
        $this->assertRoom(12, 6, 'R0012', 'Phòng 1', 'inactive');
        $this->assertSame(0, DB::table('cinema_consolidation_mappings')->count());
        $this->assertFalse(DB::table('cinemas')->where('canonical_key', CinemaContext::CANONICAL_KEY)->exists());
        $this->assertSame($snapshots['seat_ids'], DB::table('seats')->orderBy('id')->pluck('id')->all());
        $this->assertSame($snapshots['showtime_ids'], DB::table('showtimes')->orderBy('id')->pluck('id')->all());
        $this->assertSame($snapshots['booking_ids'], DB::table('bookings')->orderBy('id')->pluck('id')->all());
        $this->assertSame($snapshots['payment_ids'], DB::table('payments')->orderBy('id')->pluck('id')->all());
        $this->assertSame($snapshots['booking_seat_ids'], DB::table('booking_seats')->orderBy('id')->pluck('id')->all());
        $this->assertSame($snapshots['booking_rows'], $this->bookingRows());
        $this->assertSame($snapshots['payment_rows'], $this->paymentRows());
    }

    public function test_an_unapproved_collision_rolls_back_the_entire_migration(): void
    {
        $migration = $this->migration();
        $migration->down();
        $this->seedApprovedLegacyDataset(withHistory: false);
        DB::table('rooms')->insert([
            'id' => 13, 'cinema_id' => 11, 'code' => 'P02', 'name' => 'Phòng 13',
            'room_type' => '2D', 'total_seats' => 0, 'status' => 'active',
            'created_at' => now(), 'updated_at' => now(),
        ]);

        try {
            $migration->up();
            $this->fail('Expected a room collision to abort the migration.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('collision', $exception->getMessage());
        }

        $this->assertFalse(DB::table('cinemas')->where('canonical_key', CinemaContext::CANONICAL_KEY)->exists());
        $this->assertSame(0, DB::table('cinema_consolidation_mappings')->count());
        $this->assertRoom(10, 6, 'P02', 'Phòng 2', 'active');
        $this->assertRoom(11, 11, 'P01', 'Phòng 1', 'active');
        $this->assertRoom(13, 11, 'P02', 'Phòng 13', 'active');
    }

    public function test_rollback_keeps_new_data_and_retains_canonical_as_inactive_when_still_referenced(): void
    {
        $migration = $this->migration();
        $migration->down();
        $this->seedApprovedLegacyDataset(withHistory: false);
        $migration->up();
        $canonicalId = (int) DB::table('cinemas')->where('canonical_key', CinemaContext::CANONICAL_KEY)->value('id');
        $now = now();

        DB::table('rooms')->insert([
            'id' => 20, 'cinema_id' => $canonicalId, 'code' => 'P04', 'name' => 'Phòng 4',
            'room_type' => '2D', 'total_seats' => 0, 'status' => 'active',
            'created_at' => $now, 'updated_at' => $now,
        ]);
        DB::table('movies')->insert([
            'id' => 2, 'title' => 'Post Migration Movie', 'slug' => 'post-migration-movie',
            'duration' => 90, 'age_rating' => 'P', 'status' => 'now_showing',
            'created_at' => $now, 'updated_at' => $now,
        ]);
        DB::table('showtimes')->insert([
            'id' => 950, 'movie_id' => 2, 'cinema_id' => $canonicalId, 'room_id' => 9,
            'show_date' => '2026-08-20', 'show_time' => '20:00:00', 'price' => 90000,
            'status' => 'active', 'created_at' => $now, 'updated_at' => $now,
        ]);
        DB::table('orders')->insert([
            'id' => 5050, 'customer_name' => 'New Customer', 'pickup_cinema_id' => $canonicalId,
            'total_amount' => 60000, 'status' => 'paid', 'created_at' => $now, 'updated_at' => $now,
        ]);

        $migration->down();

        $this->assertRoom(9, 6, 'P01', 'Phòng 1', 'active');
        $this->assertRoom(20, $canonicalId, 'P04', 'Phòng 4', 'active');
        $this->assertDatabaseHas('showtimes', ['id' => 950, 'cinema_id' => 6, 'room_id' => 9]);
        $this->assertDatabaseHas('orders', ['id' => 5050, 'pickup_cinema_id' => $canonicalId]);
        $this->assertDatabaseHas('cinemas', [
            'id' => $canonicalId,
            'canonical_key' => null,
            'is_primary' => false,
            'status' => 'inactive',
        ]);
        $this->assertSame(0, DB::table('cinema_consolidation_mappings')->count());
    }

    private function migration(): Migration
    {
        return require database_path('migrations/2026_08_03_100001_consolidate_cinemas_to_fpt_polytechnic.php');
    }

    private function seedApprovedLegacyDataset(bool $withHistory): array
    {
        $now = now()->startOfSecond();
        DB::table('cinemas')->insert([
            [
                'id' => 6, 'name' => 'Legacy Six', 'address' => 'Address 6', 'city' => 'Hà Nội',
                'status' => 'active', 'is_primary' => false, 'created_at' => $now, 'updated_at' => $now,
            ],
            [
                'id' => 11, 'name' => 'Legacy Eleven', 'address' => 'Address 11', 'city' => 'Hà Nội',
                'status' => 'active', 'is_primary' => false, 'created_at' => $now, 'updated_at' => $now,
            ],
        ]);
        DB::table('rooms')->insert([
            ['id' => 9, 'cinema_id' => 6, 'code' => 'P01', 'name' => 'Phòng 1', 'room_type' => '2D', 'total_seats' => 121, 'status' => 'active', 'created_at' => $now, 'updated_at' => $now],
            ['id' => 10, 'cinema_id' => 6, 'code' => 'P02', 'name' => 'Phòng 2', 'room_type' => '2D', 'total_seats' => 121, 'status' => 'active', 'created_at' => $now, 'updated_at' => $now],
            ['id' => 11, 'cinema_id' => 11, 'code' => 'P01', 'name' => 'Phòng 1', 'room_type' => '2D', 'total_seats' => 121, 'status' => 'active', 'created_at' => $now, 'updated_at' => $now],
            ['id' => 12, 'cinema_id' => 6, 'code' => 'R0012', 'name' => 'Phòng 1', 'room_type' => '2D', 'total_seats' => 0, 'status' => 'inactive', 'created_at' => $now, 'updated_at' => $now],
        ]);

        if (! $withHistory) {
            return [
                'seat_ids' => [], 'seat_ids_by_room' => [], 'showtime_ids' => [],
                'booking_ids' => [], 'payment_ids' => [], 'booking_seat_ids' => [],
                'booking_rows' => [], 'payment_rows' => [],
            ];
        }

        $seats = [];
        foreach ([9 => 9000, 10 => 10000, 11 => 11000] as $roomId => $baseId) {
            for ($number = 1; $number <= 121; $number++) {
                $seats[] = [
                    'id' => $baseId + $number, 'room_id' => $roomId, 'row' => 'A', 'number' => $number,
                    'seat_code' => 'A'.$number, 'type' => 'normal', 'status' => 'active',
                    'created_at' => $now, 'updated_at' => $now,
                ];
            }
        }
        DB::table('seats')->insert($seats);
        DB::table('movies')->insert([
            'id' => 1, 'title' => 'Migration Test', 'slug' => 'migration-test',
            'duration' => 90, 'age_rating' => 'P', 'status' => 'now_showing',
            'created_at' => $now, 'updated_at' => $now,
        ]);

        $showtimes = [];
        $showtimeId = 900;
        foreach ([9 => [6, 5], 10 => [6, 2], 11 => [11, 3]] as $roomId => [$cinemaId, $count]) {
            for ($index = 0; $index < $count; $index++) {
                $showtimes[] = [
                    'id' => ++$showtimeId, 'movie_id' => 1, 'cinema_id' => $cinemaId, 'room_id' => $roomId,
                    'show_date' => '2026-08-10', 'show_time' => sprintf('%02d:00:00', 8 + $index),
                    'price' => 80000, 'status' => 'active', 'created_at' => $now, 'updated_at' => $now,
                ];
            }
        }
        DB::table('showtimes')->insert($showtimes);

        $bookings = [];
        $payments = [];
        $bookingSeats = [];
        for ($index = 1; $index <= 18; $index++) {
            $bookingId = 2000 + $index;
            $paymentId = 3000 + $index;
            [$showtimeForBooking, $seatId] = match (true) {
                $index <= 3 => [901, 9000 + $index],
                $index <= 5 => [906, 10000 + ($index - 3)],
                default => [908, 11000 + ($index - 5)],
            };
            $bookings[] = [
                'id' => $bookingId, 'showtime_id' => $showtimeForBooking, 'booking_code' => sprintf('MMT-2026-%04d', $index),
                'total_amount' => 80000, 'payment_method' => 'fake', 'payment_status' => 'paid',
                'booking_status' => 'paid', 'customer_email' => 'test'.$index.'@example.com',
                'created_at' => $now, 'updated_at' => $now,
            ];
            $payments[] = [
                'id' => $paymentId, 'booking_id' => $bookingId, 'provider' => 'test', 'payment_method' => 'fake',
                'order_code' => 'ORDER-'.$index, 'amount' => 80000, 'status' => 'success',
                'transaction_code' => 'TX-'.$index, 'paid_at' => $now, 'created_at' => $now, 'updated_at' => $now,
            ];
            $bookingSeats[] = [
                'id' => 4000 + $index, 'booking_id' => $bookingId, 'showtime_id' => $showtimeForBooking,
                'seat_id' => $seatId, 'active_lock_key' => 'ACTIVE', 'price' => 80000,
                'created_at' => $now, 'updated_at' => $now,
            ];
        }
        DB::table('bookings')->insert($bookings);
        DB::table('payments')->insert($payments);
        DB::table('booking_seats')->insert($bookingSeats);
        DB::table('orders')->insert([
            'id' => 5001, 'customer_name' => 'Food Customer', 'pickup_cinema_id' => 11,
            'total_amount' => 50000, 'status' => 'paid', 'created_at' => $now, 'updated_at' => $now,
        ]);

        return [
            'seat_ids' => DB::table('seats')->orderBy('id')->pluck('id')->all(),
            'seat_ids_by_room' => collect([9, 10, 11])->mapWithKeys(fn ($roomId) => [
                $roomId => DB::table('seats')->where('room_id', $roomId)->orderBy('id')->pluck('id')->all(),
            ])->all(),
            'showtime_ids' => DB::table('showtimes')->orderBy('id')->pluck('id')->all(),
            'booking_ids' => DB::table('bookings')->orderBy('id')->pluck('id')->all(),
            'payment_ids' => DB::table('payments')->orderBy('id')->pluck('id')->all(),
            'booking_seat_ids' => DB::table('booking_seats')->orderBy('id')->pluck('id')->all(),
            'booking_rows' => $this->bookingRows(),
            'payment_rows' => $this->paymentRows(),
        ];
    }

    private function bookingRows(): array
    {
        return DB::table('bookings')->orderBy('id')->get([
            'id', 'user_id', 'showtime_id', 'booking_code', 'total_amount', 'payment_method',
            'payment_status', 'booking_status', 'customer_email', 'created_at', 'updated_at', 'paid_at',
        ])->map(fn ($row) => (array) $row)->all();
    }

    private function paymentRows(): array
    {
        return DB::table('payments')->orderBy('id')->get([
            'id', 'booking_id', 'provider', 'payment_method', 'order_code', 'amount', 'status',
            'transaction_code', 'paid_at', 'created_at', 'updated_at',
        ])->map(fn ($row) => (array) $row)->all();
    }

    private function assertRoom(int $id, int $cinemaId, string $code, string $name, string $status): void
    {
        $this->assertDatabaseHas('rooms', compact('id', 'code', 'name', 'status') + [
            'cinema_id' => $cinemaId,
        ]);
    }
}
