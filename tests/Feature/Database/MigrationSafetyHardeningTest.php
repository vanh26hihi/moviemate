<?php

namespace Tests\Feature\Database;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
use Tests\Support\CreatesBookingFixtures;
use Tests\TestCase;

class MigrationSafetyHardeningTest extends TestCase
{
    use CreatesBookingFixtures;
    use RefreshDatabase;

    public function test_versioned_layout_rollback_roundtrips_and_restores_the_room_foreign_key(): void
    {
        $migration = $this->layoutMigration();

        $migration->down();
        $migration->down();

        $this->assertFalse(Schema::hasColumn('showtimes', 'room_layout_id'));
        $this->assertFalse(Schema::hasTable('room_layouts'));
        $this->assertTrue($this->hasForeign('showtimes', ['room_id'], 'rooms'));

        $migration->up();
        $migration->up();

        $this->assertTrue(Schema::hasColumn('showtimes', 'room_layout_id'));
        $this->assertTrue(Schema::hasTable('room_layouts'));
        $this->assertTrue($this->hasForeign('showtimes', ['room_layout_id'], 'room_layouts'));
    }

    public function test_versioned_layout_partial_down_resumes_after_the_layout_foreign_is_missing(): void
    {
        Schema::table('showtimes', function (Blueprint $table): void {
            $table->dropForeign(['room_layout_id']);
        });

        $migration = $this->layoutMigration();
        $migration->down();
        $migration->up();

        $this->assertTrue($this->hasForeign('showtimes', ['room_id'], 'rooms'));
        $this->assertTrue($this->hasForeign('showtimes', ['room_layout_id'], 'room_layouts'));
    }

    public function test_versioned_layout_rollback_refuses_referenced_history_without_changes(): void
    {
        $scenario = $this->bookingScenario(false);
        $before = [
            'layouts' => DB::table('room_layouts')->count(),
            'cells' => DB::table('room_layout_cells')->count(),
            'showtimes' => DB::table('showtimes')->count(),
        ];

        try {
            $this->layoutMigration()->down();
            $this->fail('Rollback must refuse referenced layout history.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('protected layout history exists', $exception->getMessage());
            $this->assertStringContainsString('No rows or schema objects were changed', $exception->getMessage());
        }

        $this->assertSame($before['layouts'], DB::table('room_layouts')->count());
        $this->assertSame($before['cells'], DB::table('room_layout_cells')->count());
        $this->assertSame($before['showtimes'], DB::table('showtimes')->count());
        $this->assertSame($scenario['layout']->id, $scenario['showtime']->fresh()->room_layout_id);
    }

    public function test_exact_phase_four_sequence_roundtrips_on_an_empty_schema(): void
    {
        $migrations = $this->phaseFourMigrations();

        foreach (array_reverse($migrations) as $migration) {
            $migration->down();
        }

        $this->assertFalse(Schema::hasColumn('bookings', 'guest_access_token_hash'));
        $this->assertFalse(Schema::hasColumn('payments', 'app_trans_id'));
        $this->assertFalse(Schema::hasTable('booking_ticket_deliveries'));
        $this->assertFalse(Schema::hasTable('payment_review_events'));

        foreach ($migrations as $migration) {
            $migration->up();
        }

        $this->assertTrue(Schema::hasColumns('bookings', [
            'guest_access_token_hash',
            'checkout_request_fingerprint_hash',
            'ticket_email_token_hash',
        ]));
        $this->assertTrue(Schema::hasColumns('payments', [
            'app_trans_id',
            'reconcile_until',
            'active_attempt_key',
        ]));
        $this->assertTrue(Schema::hasTable('booking_ticket_deliveries'));
        $this->assertTrue(Schema::hasTable('payment_review_events'));
    }

    public function test_phase_four_guard_refuses_business_data_before_any_batch_ddl(): void
    {
        $booking = $this->bookingForScenario($this->bookingScenario(false));
        $schemaBefore = Schema::getColumns('bookings');

        try {
            $this->phaseFourGuard()->down();
            $this->fail('The Phase-4 guard must refuse protected business data.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('bookings=1', $exception->getMessage());
            $this->assertStringContainsString('no rows or schema objects were changed', strtolower($exception->getMessage()));
        }

        $this->assertSame(1, DB::table('bookings')->where('id', $booking->id)->count());
        $this->assertSame($schemaBefore, Schema::getColumns('bookings'));
    }

    /** @return list<object> */
    private function phaseFourMigrations(): array
    {
        return collect([
            '2026_08_04_100000_harden_booking_foundations.php',
            '2026_08_04_105000_harden_booking_seat_integrity.php',
            '2026_08_04_110000_extend_payments_for_zalopay.php',
            '2026_08_04_115000_add_payment_reconciliation_and_ticket_outbox.php',
            '2026_08_04_120000_add_checkout_pricing_and_food_snapshots.php',
            '2026_08_04_121000_harden_active_payment_attempt_states.php',
            '2026_08_04_122000_create_payment_review_events_table.php',
            '2026_08_04_123000_remove_booking_seat_fk_compatibility_index.php',
            '2026_08_04_124000_add_ticket_email_access_credentials_to_bookings.php',
            '2026_08_04_125000_guard_phase4_rollback_data.php',
        ])->map(fn (string $file): object => require database_path('migrations/'.$file))->all();
    }

    private function phaseFourGuard(): object
    {
        return require database_path('migrations/2026_08_04_125000_guard_phase4_rollback_data.php');
    }

    private function layoutMigration(): object
    {
        return require database_path('migrations/2026_08_03_200000_create_versioned_room_layouts.php');
    }

    /** @param list<string> $columns */
    private function hasForeign(string $table, array $columns, string $foreignTable): bool
    {
        return collect(Schema::getForeignKeys($table))->contains(
            fn (array $foreign): bool => ($foreign['columns'] ?? []) === $columns
                && ($foreign['foreign_table'] ?? null) === $foreignTable,
        );
    }
}
