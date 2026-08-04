<?php

namespace Tests\Feature\Bookings;

use App\Models\BookingSeat;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
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

    public function test_cancelled_legacy_booking_releases_its_lock(): void
    {
        $this->assertLegacyStatusMapsToLock('cancelled', BookingSeat::ACTIVE_LOCK_KEY, null);
    }

    public function test_expired_legacy_booking_releases_its_lock(): void
    {
        $this->assertLegacyStatusMapsToLock('expired', BookingSeat::ACTIVE_LOCK_KEY, null);
    }

    public function test_pending_payment_legacy_booking_keeps_an_active_lock(): void
    {
        $this->assertLegacyStatusMapsToLock('pending_payment', null, BookingSeat::ACTIVE_LOCK_KEY);
    }

    public function test_paid_legacy_booking_keeps_an_active_lock(): void
    {
        $this->assertLegacyStatusMapsToLock('paid', null, BookingSeat::ACTIVE_LOCK_KEY);
    }

    public function test_used_legacy_booking_keeps_an_active_lock(): void
    {
        $this->assertLegacyStatusMapsToLock('used', null, BookingSeat::ACTIVE_LOCK_KEY);
    }

    public function test_unknown_booking_status_aborts_before_any_hardening_ddl(): void
    {
        $scenario = $this->bookingScenario(false);
        $migration = $this->migration();
        $migration->down();
        $this->bookingForScenario($scenario, ['booking_status' => 'legacy_unknown']);

        try {
            $migration->up();
            $this->fail('An unknown booking status must abort the migration.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('unsupported booking_status', $exception->getMessage());
        }

        $this->assertFalse(Schema::hasColumn('bookings', 'checkout_request_fingerprint_hash'));
        $this->assertTrue(collect(Schema::getColumns('booking_seats'))->keyBy('name')['showtime_id']['nullable']);
    }

    public function test_duplicate_active_locks_after_status_normalization_abort_before_ddl(): void
    {
        $scenario = $this->bookingScenario(false);
        $migration = $this->migration();
        $migration->down();
        $first = $this->bookingForScenario($scenario, ['booking_status' => 'pending_payment']);
        $second = $this->bookingForScenario($scenario, ['booking_status' => 'paid']);

        $this->legacySeat($scenario, $first->id, BookingSeat::ACTIVE_LOCK_KEY);
        $this->legacySeat($scenario, $second->id, null);

        try {
            $migration->up();
            $this->fail('Prospective duplicate ACTIVE locks must abort the migration.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('Duplicate ACTIVE locks after status normalization', $exception->getMessage());
        }

        $this->assertFalse(Schema::hasColumn('bookings', 'checkout_request_fingerprint_hash'));
        $this->assertTrue(collect(Schema::getColumns('booking_seats'))->keyBy('name')['showtime_id']['nullable']);
        $this->assertDatabaseHas('booking_seats', ['booking_id' => $second->id, 'active_lock_key' => null]);
    }

    public function test_orphan_booking_seat_aborts_before_ddl(): void
    {
        $scenario = $this->bookingScenario(false);
        $migration = $this->migration();
        $migration->down();

        Schema::table('booking_seats', function ($table): void {
            $table->dropForeign(['booking_id']);
        });
        DB::table('booking_seats')->insert([
            'booking_id' => 999999,
            'showtime_id' => null,
            'seat_id' => $scenario['seats'][0]->id,
            'active_lock_key' => BookingSeat::ACTIVE_LOCK_KEY,
            'price' => 50_000,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        try {
            $migration->up();
            $this->fail('An orphan booking seat must abort the migration.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('references missing booking', $exception->getMessage());
        }

        $this->assertFalse(Schema::hasColumn('bookings', 'checkout_request_fingerprint_hash'));
    }

    public function test_partial_hardening_state_is_reported_without_duplicate_ddl(): void
    {
        $migration = $this->migration();
        $migration->down();
        Schema::table('bookings', function ($table): void {
            $table->char('checkout_request_fingerprint_hash', 64)->nullable();
        });

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('partial DDL state detected');

        $migration->up();
    }

    public function test_down_refuses_rebook_history_and_preserves_every_row(): void
    {
        $scenario = $this->bookingScenario(false);
        $released = $this->bookingForScenario($scenario, ['booking_status' => 'expired']);
        $rebooked = $this->bookingForScenario($scenario, ['booking_status' => 'pending_payment']);

        $this->legacySeat($scenario, $released->id, null);
        $this->legacySeat($scenario, $rebooked->id, BookingSeat::ACTIVE_LOCK_KEY);
        $migration = $this->migration();

        try {
            $migration->down();
            $this->fail('Rollback must refuse released/rebook history.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('history rows', $exception->getMessage());
        }

        $this->assertDatabaseCount('booking_seats', 2);
        $this->assertDatabaseHas('booking_seats', ['booking_id' => $released->id, 'active_lock_key' => null]);
        $this->assertDatabaseHas('booking_seats', [
            'booking_id' => $rebooked->id,
            'active_lock_key' => BookingSeat::ACTIVE_LOCK_KEY,
        ]);
        $this->assertTrue(Schema::hasColumn('bookings', 'checkout_request_fingerprint_hash'));
        $this->assertFalse(collect(Schema::getColumns('booking_seats'))->keyBy('name')['showtime_id']['nullable']);
    }

    private function assertLegacyStatusMapsToLock(
        string $status,
        ?string $legacyLock,
        ?string $expectedLock,
    ): void {
        $scenario = $this->bookingScenario(false);
        $migration = $this->migration();
        $migration->down();
        $booking = $this->bookingForScenario($scenario, ['booking_status' => $status]);
        $seat = $this->legacySeat($scenario, $booking->id, $legacyLock);

        $migration->up();

        $this->assertSame($expectedLock, $seat->fresh()->active_lock_key);
    }

    private function legacySeat(array $scenario, int $bookingId, ?string $activeLock): BookingSeat
    {
        return BookingSeat::query()->create([
            'booking_id' => $bookingId,
            'showtime_id' => $scenario['showtime']->id,
            'seat_id' => $scenario['seats'][0]->id,
            'active_lock_key' => $activeLock,
            'price' => 50_000,
        ]);
    }

    private function migration(): Migration
    {
        return require database_path('migrations/2026_08_04_105000_harden_booking_seat_integrity.php');
    }
}
