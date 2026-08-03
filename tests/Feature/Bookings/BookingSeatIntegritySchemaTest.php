<?php

namespace Tests\Feature\Bookings;

use App\Models\BookingSeat;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
use Tests\Support\CreatesBookingFixtures;
use Tests\TestCase;

class BookingSeatIntegritySchemaTest extends TestCase
{
    use CreatesBookingFixtures;
    use RefreshDatabase;

    public function test_schema_binds_each_booking_seat_to_its_parent_showtime(): void
    {
        $bookingColumns = collect(Schema::getColumns('bookings'))->keyBy('name');
        $seatColumns = collect(Schema::getColumns('booking_seats'))->keyBy('name');

        $this->assertTrue($bookingColumns->has('checkout_request_fingerprint_hash'));
        $this->assertTrue($bookingColumns->has('guest_access_expires_at'));
        $this->assertFalse($seatColumns['showtime_id']['nullable']);
        $this->assertTrue(collect(Schema::getIndexes('bookings'))->contains(
            fn (array $index) => $index['unique']
                && $index['columns'] === ['id', 'showtime_id']
        ));
        $this->assertTrue(collect(Schema::getForeignKeys('booking_seats'))->contains(
            fn (array $foreignKey) => $foreignKey['columns'] === ['booking_id', 'showtime_id']
                && $foreignKey['foreign_table'] === 'bookings'
                && $foreignKey['foreign_columns'] === ['id', 'showtime_id']
        ));
    }

    public function test_database_rejects_a_null_booking_seat_showtime(): void
    {
        $scenario = $this->bookingScenario(false);
        $booking = $this->bookingForScenario($scenario);

        $this->expectException(QueryException::class);
        BookingSeat::query()->create([
            'booking_id' => $booking->id,
            'showtime_id' => null,
            'seat_id' => $scenario['seats'][0]->id,
            'active_lock_key' => BookingSeat::ACTIVE_LOCK_KEY,
            'price' => 50000,
        ]);
    }

    public function test_database_rejects_a_showtime_that_differs_from_the_parent_booking(): void
    {
        $first = $this->bookingScenario(false);
        $second = $this->bookingScenario(false);
        $booking = $this->bookingForScenario($first);

        $this->expectException(QueryException::class);
        BookingSeat::query()->create([
            'booking_id' => $booking->id,
            'showtime_id' => $second['showtime']->id,
            'seat_id' => $second['seats'][0]->id,
            'active_lock_key' => BookingSeat::ACTIVE_LOCK_KEY,
            'price' => 50000,
        ]);
    }

    public function test_database_rejects_unknown_active_lock_values(): void
    {
        $scenario = $this->bookingScenario(false);
        $booking = $this->bookingForScenario($scenario);

        $this->expectException(QueryException::class);
        BookingSeat::query()->create([
            'booking_id' => $booking->id,
            'showtime_id' => $scenario['showtime']->id,
            'seat_id' => $scenario['seats'][0]->id,
            'active_lock_key' => 'BYPASS',
            'price' => 50000,
        ]);
    }

    public function test_migration_backfills_legacy_null_showtimes_before_making_them_required(): void
    {
        $scenario = $this->bookingScenario(false);
        $booking = $this->bookingForScenario($scenario);
        $migration = require database_path('migrations/2026_08_04_105000_harden_booking_seat_integrity.php');
        $migration->down();

        $seat = BookingSeat::query()->create([
            'booking_id' => $booking->id,
            'showtime_id' => null,
            'seat_id' => $scenario['seats'][0]->id,
            'active_lock_key' => BookingSeat::ACTIVE_LOCK_KEY,
            'price' => 50000,
        ]);

        $migration->up();

        $this->assertSame($scenario['showtime']->id, $seat->fresh()->showtime_id);
        $this->assertFalse(collect(Schema::getColumns('booking_seats'))->keyBy('name')['showtime_id']['nullable']);
    }

    public function test_migration_refuses_legacy_rows_with_mismatched_showtimes(): void
    {
        $first = $this->bookingScenario(false);
        $second = $this->bookingScenario(false);
        $booking = $this->bookingForScenario($first);
        $migration = require database_path('migrations/2026_08_04_105000_harden_booking_seat_integrity.php');
        $migration->down();

        BookingSeat::query()->create([
            'booking_id' => $booking->id,
            'showtime_id' => $second['showtime']->id,
            'seat_id' => $second['seats'][0]->id,
            'active_lock_key' => BookingSeat::ACTIVE_LOCK_KEY,
            'price' => 50000,
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('belongs to showtime');
        $migration->up();
    }
}
