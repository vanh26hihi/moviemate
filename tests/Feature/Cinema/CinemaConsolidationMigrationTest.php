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

    private const MYSQL_ROOM_12_NAME = "Pho\u{0300}ng 1";

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
        $this->assertDatabaseHas('cinema_consolidation_mappings', [
            'entity_type' => 'room',
            'entity_id' => 12,
            'original_cinema_id' => 6,
            'original_code' => 'R0012',
            'original_name' => self::MYSQL_ROOM_12_NAME,
            'original_status' => 'inactive',
        ]);
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
        $this->assertSame(35, DB::table('booking_seats')->count());
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
        $this->assertRoom(12, 6, 'R0012', self::MYSQL_ROOM_12_NAME, 'inactive');
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
            'room_type' => '2D', 'width_mm' => 8_000, 'length_mm' => 10_000, 'status' => 'active',
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
            'room_type' => '2D', 'width_mm' => 8_000, 'length_mm' => 10_000, 'status' => 'active',
            'created_at' => $now, 'updated_at' => $now,
        ]);
        DB::table('movies')->insert([
            'id' => 2, 'title' => 'Post Migration Movie', 'slug' => 'post-migration-movie',
            'duration' => 90, 'age_rating' => 'P', 'status' => 'now_showing',
            'created_at' => $now, 'updated_at' => $now,
        ]);
        $format = $this->presentationFormatFixture(2, 9);
        DB::table('showtimes')->insert([
            'id' => 950, 'movie_id' => 2, 'cinema_id' => $canonicalId, 'room_id' => 9,
            'presentation_format_id' => $format->id,
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

    public function test_existing_canonical_is_reused_without_duplication_and_restored_on_down(): void
    {
        $migration = $this->migration();
        $migration->down();
        $this->seedApprovedLegacyDataset(withHistory: false);
        $canonicalId = $this->insertCanonical('Existing partial canonical');

        $migration->up();

        $this->assertSame(1, DB::table('cinemas')->where('canonical_key', CinemaContext::CANONICAL_KEY)->count());
        $this->assertDatabaseHas('cinema_consolidation_mappings', [
            'entity_type' => 'canonical',
            'entity_id' => $canonicalId,
            'canonical_cinema_id' => $canonicalId,
            'original_code' => 'reused',
        ]);

        $migration->down();

        $this->assertDatabaseHas('cinemas', [
            'id' => $canonicalId,
            'canonical_key' => CinemaContext::CANONICAL_KEY,
            'name' => 'Existing partial canonical',
            'is_primary' => false,
            'status' => 'inactive',
        ]);
    }

    public function test_safe_room_12_legacy_fields_are_captured_and_restored_from_mapping(): void
    {
        $migration = $this->migration();
        $migration->down();
        $this->seedApprovedLegacyDataset(withHistory: false);
        DB::table('rooms')->where('id', 12)->update([
            'code' => 'LEGACY-12',
            'name' => 'Tên lưu trữ thực tế',
            'status' => 'active',
        ]);

        $migration->up();

        $canonicalId = (int) DB::table('cinemas')->where('canonical_key', CinemaContext::CANONICAL_KEY)->value('id');
        $this->assertRoom(12, $canonicalId, 'ARCH-12', 'Phòng 1 (Ngừng hoạt động)', 'inactive');
        $this->assertDatabaseHas('cinema_consolidation_mappings', [
            'entity_type' => 'room',
            'entity_id' => 12,
            'original_code' => 'LEGACY-12',
            'original_name' => 'Tên lưu trữ thực tế',
            'original_status' => 'active',
        ]);

        $migration->down();

        $this->assertRoom(12, 6, 'LEGACY-12', 'Tên lưu trữ thực tế', 'active');
    }

    public function test_matching_partial_mapping_is_reused_without_duplicate_rows(): void
    {
        $migration = $this->migration();
        $migration->down();
        $this->seedApprovedLegacyDataset(withHistory: false);
        $canonicalId = $this->insertCanonical();
        DB::table('cinema_consolidation_mappings')->insert([
            'entity_type' => 'room', 'entity_id' => 9,
            'original_cinema_id' => 6, 'canonical_cinema_id' => $canonicalId,
            'original_code' => 'P01', 'original_name' => 'Phòng 1', 'original_status' => 'active',
            'migrated_at' => now(),
        ]);

        $migration->up();

        $this->assertSame(1, DB::table('cinema_consolidation_mappings')->where('entity_type', 'room')->where('entity_id', 9)->count());
        $this->assertRoom(9, $canonicalId, 'P01', 'Phòng 1', 'active');
        $this->assertSame(1, DB::table('cinemas')->where('canonical_key', CinemaContext::CANONICAL_KEY)->count());
    }

    public function test_inconsistent_partial_mapping_fails_without_silent_corruption(): void
    {
        $migration = $this->migration();
        $migration->down();
        $this->seedApprovedLegacyDataset(withHistory: false);
        $canonicalId = $this->insertCanonical();
        DB::table('cinema_consolidation_mappings')->insert([
            'entity_type' => 'room', 'entity_id' => 9,
            'original_cinema_id' => 6, 'canonical_cinema_id' => $canonicalId,
            'original_code' => 'WRONG', 'original_name' => 'Phòng 1', 'original_status' => 'active',
            'migrated_at' => now(),
        ]);

        try {
            $migration->up();
            $this->fail('Expected an inconsistent partial mapping to abort consolidation.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('inconsistent at original_code', $exception->getMessage());
        }

        $this->assertRoom(9, 6, 'P01', 'Phòng 1', 'active');
        $this->assertSame(1, DB::table('cinema_consolidation_mappings')->count());
    }

    public function test_missing_room_12_aborts_and_rolls_back_canonical_creation(): void
    {
        $migration = $this->migration();
        $migration->down();
        $this->seedApprovedLegacyDataset(withHistory: false);
        DB::table('rooms')->where('id', 12)->delete();

        try {
            $migration->up();
            $this->fail('Expected missing Room 12 to abort consolidation.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('Required Room 12 is missing', $exception->getMessage());
        }

        $this->assertFalse(DB::table('cinemas')->where('canonical_key', CinemaContext::CANONICAL_KEY)->exists());
        $this->assertSame(0, DB::table('cinema_consolidation_mappings')->count());
    }

    public function test_pretend_path_does_not_query_fake_insert_results_or_change_data(): void
    {
        $migration = $this->migration();
        $migration->down();
        $this->seedApprovedLegacyDataset(withHistory: false);

        $queries = DB::connection()->pretend(function () use ($migration): void {
            $migration->up();
        });

        $this->assertSame([], $queries);
        $this->assertFalse(DB::table('cinemas')->where('canonical_key', CinemaContext::CANONICAL_KEY)->exists());
        $this->assertRoom(12, 6, 'R0012', self::MYSQL_ROOM_12_NAME, 'inactive');
    }

    private function migration(): Migration
    {
        return require database_path('migrations/2026_08_03_100001_consolidate_cinemas_to_fpt_polytechnic.php');
    }

    private function insertCanonical(string $name = 'MovieMate Cinema – FPT Polytechnic'): int
    {
        return DB::table('cinemas')->insertGetId([
            'canonical_key' => CinemaContext::CANONICAL_KEY,
            'name' => $name,
            'school_name' => null,
            'address' => CinemaContext::ADDRESS,
            'city' => CinemaContext::CITY,
            'country' => CinemaContext::COUNTRY,
            'phone' => null,
            'latitude' => CinemaContext::LATITUDE,
            'longitude' => CinemaContext::LONGITUDE,
            'status' => 'inactive',
            'is_primary' => false,
            'archived_at' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
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
            ['id' => 9, 'cinema_id' => 6, 'code' => 'P01', 'name' => 'Phòng 1', 'room_type' => '2D', 'width_mm' => 7_500, 'length_mm' => 10_000, 'status' => 'active', 'created_at' => $now, 'updated_at' => $now],
            ['id' => 10, 'cinema_id' => 6, 'code' => 'P02', 'name' => 'Phòng 2', 'room_type' => '2D', 'width_mm' => 8_000, 'length_mm' => 11_000, 'status' => 'active', 'created_at' => $now, 'updated_at' => $now],
            ['id' => 11, 'cinema_id' => 11, 'code' => 'P01', 'name' => 'Phòng 1', 'room_type' => '2D', 'width_mm' => 7_500, 'length_mm' => 10_000, 'status' => 'active', 'created_at' => $now, 'updated_at' => $now],
            ['id' => 12, 'cinema_id' => 6, 'code' => 'R0012', 'name' => self::MYSQL_ROOM_12_NAME, 'room_type' => '2D', 'width_mm' => null, 'length_mm' => null, 'status' => 'inactive', 'created_at' => $now, 'updated_at' => $now],
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
        $format = $this->presentationFormatFixture(1, 9);
        $this->presentationFormatFixture(1, 10);
        $this->presentationFormatFixture(1, 11);

        $showtimes = [];
        $showtimeId = 900;
        foreach ([9 => [6, 5], 10 => [6, 2], 11 => [11, 3]] as $roomId => [$cinemaId, $count]) {
            for ($index = 0; $index < $count; $index++) {
                $showtimes[] = [
                    'id' => ++$showtimeId, 'movie_id' => 1, 'cinema_id' => $cinemaId, 'room_id' => $roomId,
                    'presentation_format_id' => $format->id,
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

        $extraSeats = [
            [2001, 901, 9020],
            [2004, 906, 10020], [2005, 906, 10021],
        ];
        foreach (range(6, 18) as $index) {
            $extraSeats[] = [2000 + $index, 908, 11024 + $index];
        }
        $extraSeats[] = [2006, 908, 11060];
        foreach ($extraSeats as $offset => [$bookingId, $showtimeId, $seatId]) {
            $bookingSeats[] = [
                'id' => 4100 + $offset, 'booking_id' => $bookingId, 'showtime_id' => $showtimeId,
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
