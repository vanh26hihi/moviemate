<?php

namespace Tests\MySql;

use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\Group;
use ReflectionMethod;
use RuntimeException;
use Tests\Support\CreatesBookingFixtures;
use Tests\TestCase;

#[Group('mysql-integration')]
class PaymentUpgradeMySqlTest extends TestCase
{
    use CreatesBookingFixtures;

    private const OLD = "case when status in ('pending', 'processing') then 'ACTIVE' else null end";

    private const NEW = "case when status in ('pending', 'processing', 'unresolved', 'review') then 'ACTIVE' else null end";

    protected function setUp(): void
    {
        parent::setUp();

        $this->assertSafeDatabase();
    }

    public function test_clean_repository_chain_runs_without_manual_index_ddl(): void
    {
        $ddl = [];
        DB::listen(function (QueryExecuted $query) use (&$ddl): void {
            if (str_contains(strtolower($query->sql), 'booking_seats_showtime_fk_support')) {
                $ddl[] = $query->sql;
            }
        });

        $this->freshDatabase();

        $this->assertMigrationRan('2026_07_16_235959_ensure_booking_seat_showtime_fk_support');
        $this->assertMigrationRan('2026_07_17_000001_replace_booking_seat_unique_with_active_lock');
        $this->assertMigrationRan('2026_08_04_123000_remove_booking_seat_fk_compatibility_index');
        $this->assertMigrationRan('2026_08_04_124000_add_ticket_email_access_credentials_to_bookings');
        $this->assertMigrationRan('2026_08_04_125000_guard_phase4_rollback_data');
        $this->assertTrue(Schema::hasColumns('bookings', [
            'ticket_email_token_nonce',
            'ticket_email_token_hash',
            'ticket_email_token_expires_at',
        ]));
        $this->assertSame(
            ['showtime_id', 'seat_id'],
            $this->indexColumns('booking_seats', 'booking_seats_showtime_seat_index'),
        );
        $this->assertSame([], $this->indexColumns('booking_seats', 'booking_seats_showtime_fk_support'));
        $this->assertTrue(collect($ddl)->contains(
            fn (string $sql): bool => str_contains(strtoupper($sql), 'ADD INDEX'),
        ), 'The compatibility migration did not add its temporary index.');
        $this->assertTrue(collect($ddl)->contains(
            fn (string $sql): bool => str_contains(strtoupper($sql), 'DROP INDEX'),
        ), 'The cleanup migration did not remove its temporary index.');

        $this->evidence('clean-chain', [
            'compatibility_ddl' => count($ddl),
            'final_showtime_index' => $this->indexColumns('booking_seats', 'booking_seats_showtime_seat_index'),
        ]);
    }

    public function test_ticket_email_credential_migration_refuses_active_data_and_rehearses_down_up(): void
    {
        $this->freshDatabase();
        $bookingId = $this->bookingId();
        DB::table('bookings')->where('id', $bookingId)->update([
            'ticket_email_token_nonce' => str_repeat('n', 43),
            'ticket_email_token_hash' => hash('sha256', 'mysql-ticket-email-token'),
            'ticket_email_token_expires_at' => now()->addHour(),
        ]);
        $migration = $this->ticketEmailCredentialMigration();

        try {
            $migration->down();
            $this->fail('Rollback must refuse populated ticket email credentials.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('credential data exists', $exception->getMessage());
        }
        $this->assertTrue(Schema::hasColumn('bookings', 'ticket_email_token_hash'));

        DB::table('bookings')->where('id', $bookingId)->update([
            'ticket_email_token_nonce' => null,
            'ticket_email_token_hash' => null,
            'ticket_email_token_expires_at' => null,
        ]);
        $this->announceMutation('ticket email credential migration down/up rehearsal');
        $migration->down();
        $this->assertFalse(Schema::hasColumn('bookings', 'ticket_email_token_hash'));
        $migration->up();
        $this->assertTrue(Schema::hasColumns('bookings', [
            'ticket_email_token_nonce',
            'ticket_email_token_hash',
            'ticket_email_token_expires_at',
        ]));
    }

    public function test_historical_layout_rollback_drops_foreigns_before_supporting_indexes_and_resumes(): void
    {
        $this->freshDatabase();
        $migration = require database_path('migrations/2026_08_03_200000_create_versioned_room_layouts.php');
        $schedule = require database_path('migrations/2026_08_04_000000_add_showtime_schedule_lookup_index.php');

        $this->assertSame(
            ['room_id', 'room_layout_id'],
            $this->indexColumns('showtimes', 'showtimes_room_id_room_layout_id_index'),
        );
        $this->assertSame([], $this->indexColumns('showtimes', 'showtimes_room_id_foreign'));
        $this->announceMutation('schedule lookup down before historical 200000 rollback');
        $schedule->down();
        $this->assertSame([], $this->indexColumns('showtimes', 'showtimes_room_schedule_lookup_index'));

        $this->announceMutation('historical 200000 down');
        $migration->down();
        $this->assertFalse(Schema::hasColumn('showtimes', 'room_layout_id'));
        $this->assertTrue($this->hasForeignColumns('showtimes', ['room_id'], 'rooms'));

        $this->announceMutation('historical 200000 up');
        $migration->up();
        $this->announceMutation('schedule lookup up after historical 200000 re-upgrade');
        $schedule->up();
        $this->statement('ALTER TABLE `showtimes` DROP FOREIGN KEY `showtimes_room_layout_id_foreign`');
        $this->announceMutation('schedule lookup down before historical partial resume');
        $schedule->down();
        $this->announceMutation('historical 200000 partial down resume');
        $migration->down();
        $this->announceMutation('historical 200000 partial up resume');
        $migration->up();
        $this->announceMutation('schedule lookup up after historical partial resume');
        $schedule->up();

        $this->assertTrue($this->hasForeignColumns('showtimes', ['room_id'], 'rooms'));
        $this->assertTrue($this->hasForeignColumns('showtimes', ['room_layout_id'], 'room_layouts'));
        $this->evidence('historical-layout-rollback', ['error_1553_avoided' => true, 'partial_resume' => true]);
    }

    public function test_exact_phase_four_down_up_roundtrip_succeeds_when_rehearsal_is_empty(): void
    {
        $this->freshDatabase();
        $migrations = $this->phaseFourMigrations();

        foreach (array_reverse($migrations) as $migration) {
            $this->announceMutation('exact Phase-4 migration down');
            $migration->down();
        }
        $this->assertFalse(Schema::hasColumn('payments', 'app_trans_id'));
        $this->assertFalse(Schema::hasTable('booking_ticket_deliveries'));

        foreach ($migrations as $migration) {
            $this->announceMutation('exact Phase-4 migration up');
            $migration->up();
        }

        $this->assertTrue(Schema::hasColumn('payments', 'active_attempt_key'));
        $this->assertTrue(Schema::hasColumn('bookings', 'ticket_email_token_hash'));
        $this->assertTrue(Schema::hasTable('booking_ticket_deliveries'));
        $this->evidence('exact-phase4-roundtrip', ['empty_rollback' => true, 'reupgrade' => true]);
    }

    public function test_phase_four_guard_blocks_active_data_before_schema_changes(): void
    {
        $this->freshDatabase();
        $bookingId = $this->bookingId();
        $before = $this->showCreate('bookings');

        try {
            (require database_path('migrations/2026_08_04_125000_guard_phase4_rollback_data.php'))->down();
            $this->fail('The Phase-4 rollback guard must refuse protected business data.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('protected business data exists', $exception->getMessage());
            $this->assertStringContainsString('bookings=1', $exception->getMessage());
        }

        $this->assertSame($before, $this->showCreate('bookings'));
        $this->assertSame(1, DB::table('bookings')->where('id', $bookingId)->count());
        $this->evidence('phase4-protected-data', ['booking_id' => $bookingId, 'schema_unchanged' => true]);
    }

    public function test_real_inventory_classifies_all_four_supported_states_and_repairs_missing_indexes(): void
    {
        $this->freshDatabase();
        $migration = $this->upgradeMigration();

        $this->assertSame('FULL_NEW', $this->inventoryState($migration));
        $before = $this->showCreate('payments');
        $migration->up();
        $this->assertSame($before, $this->showCreate('payments'));

        $this->statement('ALTER TABLE `payments` DROP INDEX `payments_one_active_attempt_unique`');
        $this->assertSame('NEW_WITHOUT_INDEX', $this->inventoryState($migration));
        $migration->up();
        $this->assertSame('FULL_NEW', $this->inventoryState($migration));

        $migration->down();
        $this->assertSame('FULL_OLD', $this->inventoryState($migration));
        $this->statement('ALTER TABLE `payments` DROP INDEX `payments_one_active_attempt_unique`');
        $this->assertSame('OLD_WITHOUT_INDEX', $this->inventoryState($migration));
        $migration->up();
        $this->assertSame('FULL_NEW', $this->inventoryState($migration));

        $this->evidence('inventory-states', ['final_state' => $this->inventoryState($migration)]);
    }

    public function test_real_inventory_rejects_wrong_index_order_and_uniqueness_before_ddl(): void
    {
        $this->freshDatabase();
        $migration = $this->upgradeMigration();
        $this->statement('ALTER TABLE `payments` DROP INDEX `payments_one_active_attempt_unique`, '
            .'ADD UNIQUE INDEX `payments_one_active_attempt_unique` (`provider`, `booking_id`, `active_attempt_key`)');
        $wrongOrder = $this->showCreate('payments');
        $this->assertMigrationRejected($migration, 'wrong uniqueness, columns, order, or prefix');
        $this->assertSame($wrongOrder, $this->showCreate('payments'));

        $this->statement('ALTER TABLE `payments` DROP INDEX `payments_one_active_attempt_unique`, '
            .'ADD INDEX `payments_one_active_attempt_unique` (`booking_id`, `provider`, `active_attempt_key`)');
        $wrongUniqueness = $this->showCreate('payments');
        $this->assertMigrationRejected($migration, 'wrong uniqueness, columns, order, or prefix');
        $this->assertSame($wrongUniqueness, $this->showCreate('payments'));

        $this->evidence('wrong-indexes', [
            'ordered_columns' => $this->indexColumns('payments', 'payments_one_active_attempt_unique'),
            'non_unique' => $this->indexNonUnique('payments', 'payments_one_active_attempt_unique'),
        ]);
    }

    public function test_unknown_and_adversarial_generated_expressions_abort_unchanged(): void
    {
        $this->freshDatabase();
        $expressions = [
            'unknown' => "case when status = 'pending' then 'ACTIVE' else null end",
            'pending_x' => "case when status in ('pending_x', 'processing') then 'ACTIVE' else null end",
            'active_wrong' => "case when status in ('pending', 'processing') then 'ACTIVE_WRONG' else null end",
        ];

        foreach ($expressions as $name => $expression) {
            $this->replaceExpression($expression);
            $before = $this->showCreate('payments');
            $this->assertMigrationRejected($this->upgradeMigration(), 'unknown generated expression');
            $this->assertSame($before, $this->showCreate('payments'), $name.' changed schema during rejection.');
        }

        $this->evidence('adversarial-expressions', ['rejected' => array_keys($expressions)]);
    }

    public function test_incompatible_reconcile_until_aborts_before_ddl(): void
    {
        $this->freshDatabase();
        $this->statement('ALTER TABLE `payments` MODIFY COLUMN `reconcile_until` DATETIME NULL DEFAULT NULL');
        $before = $this->showCreate('payments');

        $this->assertMigrationRejected($this->upgradeMigration(), 'DATA_TYPE=datetime');
        $this->assertSame($before, $this->showCreate('payments'));

        $this->statement('ALTER TABLE `payments` MODIFY COLUMN `reconcile_until` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP '
            .'ON UPDATE CURRENT_TIMESTAMP');
        $onUpdate = $this->showCreate('payments');
        $this->assertMigrationRejected($this->upgradeMigration(), 'on update CURRENT_TIMESTAMP');
        $this->assertSame($onUpdate, $this->showCreate('payments'));

        $this->evidence('reconcile-rejections', $this->columnMetadata('payments', 'reconcile_until'));
    }

    public function test_atomic_upgrade_duplicate_preflight_and_safe_down_behave_on_mysql(): void
    {
        $this->freshDatabase();
        $migration = $this->upgradeMigration();
        $migration->down();
        $this->assertSame('FULL_OLD', $this->inventoryState($migration));

        $bookingId = $this->bookingId();
        $first = $this->insertPayment($bookingId, 'unresolved');
        $second = $this->insertPayment($bookingId, 'review');
        $statuses = DB::table('payments')->orderBy('id')->pluck('status', 'id')->all();
        $before = $this->showCreate('payments');

        $this->assertMigrationRejected($migration, 'duplicate blocking attempts exist');
        $this->assertSame($before, $this->showCreate('payments'));
        $this->assertSame($statuses, DB::table('payments')->orderBy('id')->pluck('status', 'id')->all());
        $this->assertStringContainsString((string) $first, implode(',', array_keys($statuses)));
        $this->assertStringContainsString((string) $second, implode(',', array_keys($statuses)));

        DB::table('payments')->whereIn('id', [$first, $second])->update(['status' => 'failed']);
        $migration->up();
        $this->assertSame('FULL_NEW', $this->inventoryState($migration));
        $migration->down();
        $this->assertSame('FULL_OLD', $this->inventoryState($migration));

        $review = $this->insertPayment($bookingId, 'review');
        $this->assertMigrationRejectedOnDown($migration, 'unresolved or review payments exist');
        $this->assertSame('review', DB::table('payments')->where('id', $review)->value('status'));

        $this->evidence('atomic-preflight', ['preserved_rows' => count($statuses), 'safe_down' => true]);
    }

    public function test_failed_combined_index_construction_leaves_recoverable_old_schema(): void
    {
        $this->freshDatabase();
        $migration = $this->upgradeMigration();
        $migration->down();
        $bookingId = $this->bookingId();
        $duplicateChecks = 0;
        $inserted = false;

        DB::listen(function (QueryExecuted $query) use (&$duplicateChecks, &$inserted, $bookingId): void {
            $sql = strtolower($query->sql);
            if (! str_contains($sql, 'from `payments`') || ! str_contains($sql, 'having count(*) > 1')) {
                return;
            }
            $duplicateChecks++;
            if ($duplicateChecks !== 2) {
                return;
            }

            $pdo = new \PDO(
                sprintf(
                    'mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4',
                    config('database.connections.mysql.host'),
                    config('database.connections.mysql.port'),
                    config('database.connections.mysql.database'),
                ),
                (string) config('database.connections.mysql.username'),
                (string) config('database.connections.mysql.password'),
                [\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION],
            );
            $statement = $pdo->prepare(
                'INSERT INTO payments (booking_id, provider, amount, status, created_at, updated_at) '
                .'VALUES (?, ?, ?, ?, NOW(), NOW())',
            );
            $statement->execute([$bookingId, 'zalopay', 50000, 'unresolved']);
            $statement->execute([$bookingId, 'zalopay', 50000, 'review']);
            $inserted = true;
        });

        try {
            $migration->up();
            $this->fail('The raced duplicate index construction must fail.');
        } catch (QueryException $exception) {
            $this->assertStringContainsString('payments_one_active_attempt_unique', $exception->getMessage());
        }

        $this->assertTrue($inserted);
        $this->assertSame('FULL_OLD', $this->inventoryState($migration));
        $this->assertSame(2, DB::table('payments')->whereIn('status', ['unresolved', 'review'])->count());
        DB::table('payments')->whereIn('status', ['unresolved', 'review'])->update(['status' => 'failed']);
        $migration->up();
        $this->assertSame('FULL_NEW', $this->inventoryState($migration));

        $this->evidence('failed-index-build', ['old_schema_recovered' => true, 'retry_succeeded' => true]);
    }

    public function test_database_invariant_covers_all_blocking_and_historical_statuses(): void
    {
        $this->freshDatabase();
        foreach (['pending', 'processing', 'unresolved', 'review'] as $status) {
            $bookingId = $this->bookingId();
            $this->insertPayment($bookingId, $status);
            try {
                $this->insertPayment($bookingId, $status);
                $this->fail("A second {$status} attempt must be rejected.");
            } catch (QueryException) {
                $this->addToAssertionCount(1);
            }
        }

        $bookingId = $this->bookingId();
        foreach (['success', 'failed', 'expired'] as $status) {
            $this->insertPayment($bookingId, $status);
            $this->insertPayment($bookingId, $status);
        }
        $this->assertSame(6, DB::table('payments')->where('booking_id', $bookingId)->count());
        $this->assertSame(6, DB::table('payments')->where('booking_id', $bookingId)->whereNull('active_attempt_key')->count());

        $this->evidence('database-invariant', ['blocking_statuses' => 4, 'historical_rows' => 6]);
    }

    public function test_compatibility_and_cleanup_guard_real_index_definitions(): void
    {
        $this->freshDatabase();
        $compatibility = $this->compatibilityMigration();
        $cleanup = $this->cleanupMigration();

        $this->statement('ALTER TABLE `booking_seats` ADD INDEX `booking_seats_showtime_fk_support` (`seat_id`, `showtime_id`)');
        $wrong = $this->showCreate('booking_seats');
        $this->assertMigrationRejected($compatibility, 'unexpected definition');
        $this->assertSame($wrong, $this->showCreate('booking_seats'));

        $this->freshDatabase();
        $this->statement('ALTER TABLE `booking_seats` ADD INDEX `booking_seats_showtime_fk_support` (`showtime_id`)');
        $this->statement('ALTER TABLE `booking_seats` DROP INDEX `booking_seats_showtime_seat_index`, '
            .'DROP INDEX `booking_seats_active_inventory_unique`');
        $cleanup->up();
        $this->assertSame(['showtime_id'], $this->indexColumns('booking_seats', 'booking_seats_showtime_fk_support'));

        $this->statement('ALTER TABLE `booking_seats` '
            .'ADD INDEX `booking_seats_showtime_seat_index` (`showtime_id`, `seat_id`), '
            .'ADD UNIQUE INDEX `booking_seats_active_inventory_unique` (`showtime_id`, `seat_id`, `active_lock_key`)');
        $cleanup->up();
        $this->assertSame([], $this->indexColumns('booking_seats', 'booking_seats_showtime_fk_support'));

        $this->evidence('compatibility-cleanup', [
            'permanent_index' => $this->indexColumns('booking_seats', 'booking_seats_showtime_seat_index'),
            'compatibility_index' => [],
        ]);
    }

    public function test_cross_july_rollback_and_reupgrade_order_keeps_foreign_key_supported(): void
    {
        $this->freshDatabase();
        $compatibility = $this->compatibilityMigration();
        $july = require database_path(
            'migrations/2026_07_17_000001_replace_booking_seat_unique_with_active_lock.php',
        );
        $integrity = require database_path(
            'migrations/2026_08_04_105000_harden_booking_seat_integrity.php',
        );

        $this->announceMutation('105000 down before July rollback');
        $integrity->down();
        $this->announceMutation('123000 down before July rollback');
        $this->cleanupMigration()->down();
        $this->announceMutation('July 17 down');
        $july->down();
        $this->announceMutation('July 16 compatibility down');
        $compatibility->down();

        $this->assertSame(
            ['showtime_id', 'seat_id'],
            $this->indexColumns('booking_seats', 'booking_seats_showtime_id_seat_id_unique'),
        );
        $this->assertSame([], $this->indexColumns('booking_seats', 'booking_seats_showtime_seat_index'));
        $this->assertSame([], $this->indexColumns('booking_seats', 'booking_seats_showtime_fk_support'));

        $this->announceMutation('July 16 compatibility up');
        $compatibility->up();
        $this->assertSame(
            ['showtime_id'],
            $this->indexColumns('booking_seats', 'booking_seats_showtime_fk_support'),
        );
        $this->announceMutation('July 17 up');
        $july->up();
        $this->announceMutation('105000 up after July re-upgrade');
        $integrity->up();
        $this->announceMutation('123000 cleanup up');
        $this->cleanupMigration()->up();

        $this->assertSame([], $this->indexColumns('booking_seats', 'booking_seats_showtime_fk_support'));
        $this->assertSame(
            ['showtime_id', 'seat_id'],
            $this->indexColumns('booking_seats', 'booking_seats_showtime_seat_index'),
        );
        $this->assertSame(
            ['showtime_id', 'seat_id', 'active_lock_key'],
            $this->indexColumns('booking_seats', 'booking_seats_active_inventory_unique'),
        );

        $this->evidence('cross-july-rollback-reupgrade', [
            'old_unique_restored_on_down' => true,
            'compatibility_added_before_july_up' => true,
            'compatibility_removed_after_safe_alternative' => true,
        ]);
    }

    public function test_existing_deployment_shape_noops_compatibility_then_completes_upgrade(): void
    {
        $this->freshDatabase();
        $this->cleanupMigration()->down();
        (require database_path('migrations/2026_08_04_122000_create_payment_review_events_table.php'))->down();
        $this->upgradeMigration()->down();
        DB::table('migrations')->whereIn('migration', [
            '2026_07_16_235959_ensure_booking_seat_showtime_fk_support',
            '2026_08_04_121000_harden_active_payment_attempt_states',
            '2026_08_04_122000_create_payment_review_events_table',
            '2026_08_04_123000_remove_booking_seat_fk_compatibility_index',
        ])->delete();

        $this->assertMigrationRan('2026_07_17_000001_replace_booking_seat_unique_with_active_lock');
        $this->assertSame(['showtime_id', 'seat_id'], $this->indexColumns(
            'booking_seats',
            'booking_seats_showtime_seat_index',
        ));
        $this->assertSame('FULL_OLD', $this->inventoryState($this->upgradeMigration()));

        $this->announceMutation('artisan migrate existing-deployment fixture');
        $this->assertSame(0, Artisan::call('migrate', ['--force' => true]), Artisan::output());
        $this->assertSame([], $this->indexColumns('booking_seats', 'booking_seats_showtime_fk_support'));
        $this->assertSame('FULL_NEW', $this->inventoryState($this->upgradeMigration()));
        $this->assertMigrationRan('2026_07_16_235959_ensure_booking_seat_showtime_fk_support');
        $this->assertMigrationRan('2026_08_04_123000_remove_booking_seat_fk_compatibility_index');

        $this->evidence('existing-deployment', ['compatibility_noop' => true, 'final_state' => 'FULL_NEW']);
    }

    private function freshDatabase(): void
    {
        $this->announceMutation('artisan migrate:fresh');
        $exit = Artisan::call('migrate:fresh', ['--force' => true]);
        $this->assertSame(0, $exit, Artisan::output());
    }

    private function assertSafeDatabase(): void
    {
        $database = (string) DB::connection()->getDatabaseName();
        $this->assertNotSame('moviemate', $database);
        $this->assertSame('moviemate_phase4_rehearsal', $database, "Unsafe MySQL integration database [{$database}].");
    }

    private function announceMutation(string $operation): void
    {
        $database = (string) DB::selectOne('SELECT DATABASE() AS database_name')->database_name;
        fwrite(STDOUT, "\nresolved_database={$database}; mutation={$operation}\n");
    }

    private function statement(string $sql): void
    {
        $this->announceMutation('integration fixture DDL');
        DB::statement($sql);
    }

    /** @param array<string, mixed> $metadata */
    private function evidence(string $scenario, array $metadata): void
    {
        fwrite(STDOUT, json_encode([
            'scenario' => $scenario,
            'database' => DB::connection()->getDatabaseName(),
            'metadata' => $metadata,
        ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES)."\n");
    }

    private function upgradeMigration(): object
    {
        return require database_path('migrations/2026_08_04_121000_harden_active_payment_attempt_states.php');
    }

    private function compatibilityMigration(): object
    {
        return require database_path('migrations/2026_07_16_235959_ensure_booking_seat_showtime_fk_support.php');
    }

    private function cleanupMigration(): object
    {
        return require database_path('migrations/2026_08_04_123000_remove_booking_seat_fk_compatibility_index.php');
    }

    private function ticketEmailCredentialMigration(): object
    {
        return require database_path(
            'migrations/2026_08_04_124000_add_ticket_email_access_credentials_to_bookings.php',
        );
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

    /** @param list<string> $columns */
    private function hasForeignColumns(string $table, array $columns, string $foreignTable): bool
    {
        return collect(Schema::getForeignKeys($table))->contains(
            fn (array $foreign): bool => ($foreign['columns'] ?? []) === $columns
                && ($foreign['foreign_table'] ?? null) === $foreignTable,
        );
    }

    private function inventoryState(object $migration): string
    {
        $inventory = (new ReflectionMethod($migration, 'inventory'))->invoke($migration);

        return (new ReflectionMethod($migration, 'classify'))->invoke($migration, $inventory);
    }

    private function assertMigrationRejected(object $migration, string $message): void
    {
        try {
            $migration->up();
            $this->fail('Migration was expected to reject the schema or data state.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString($message, $exception->getMessage());
            $this->assertStringContainsString('No DDL was executed', $exception->getMessage());
        }
    }

    private function assertMigrationRejectedOnDown(object $migration, string $message): void
    {
        try {
            $migration->down();
            $this->fail('Migration rollback was expected to reject the data state.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString($message, $exception->getMessage());
            $this->assertStringContainsString('No DDL was executed', $exception->getMessage());
        }
    }

    private function replaceExpression(string $expression): void
    {
        $metadata = $this->columnMetadata('payments', 'active_attempt_key');
        $type = strtolower((string) $metadata['COLUMN_TYPE']);
        $characterSet = (string) $metadata['CHARACTER_SET_NAME'];
        $collation = (string) $metadata['COLLATION_NAME'];
        $this->statement(
            'ALTER TABLE `payments` DROP INDEX `payments_one_active_attempt_unique`, '
            ."MODIFY COLUMN `active_attempt_key` {$type} CHARACTER SET {$characterSet} COLLATE {$collation} "
            ."GENERATED ALWAYS AS ({$expression}) VIRTUAL, "
            .'ADD UNIQUE INDEX `payments_one_active_attempt_unique` (`booking_id`, `provider`, `active_attempt_key`)',
        );
    }

    /** @return array<string, mixed> */
    private function columnMetadata(string $table, string $column): array
    {
        return (array) DB::selectOne(
            <<<'SQL'
                SELECT DATA_TYPE, COLUMN_TYPE, IS_NULLABLE, COLUMN_DEFAULT, EXTRA, GENERATION_EXPRESSION,
                       DATETIME_PRECISION, CHARACTER_SET_NAME, COLLATION_NAME
                FROM information_schema.COLUMNS
                WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?
                SQL,
            [$table, $column],
        );
    }

    /** @return list<string> */
    private function indexColumns(string $table, string $index): array
    {
        return array_map(
            fn (object $row): string => (string) $row->COLUMN_NAME,
            DB::select(
                <<<'SQL'
                    SELECT COLUMN_NAME
                    FROM information_schema.STATISTICS
                    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND INDEX_NAME = ?
                    ORDER BY SEQ_IN_INDEX
                    SQL,
                [$table, $index],
            ),
        );
    }

    private function indexNonUnique(string $table, string $index): ?int
    {
        $row = DB::selectOne(
            <<<'SQL'
                SELECT NON_UNIQUE
                FROM information_schema.STATISTICS
                WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND INDEX_NAME = ?
                ORDER BY SEQ_IN_INDEX
                LIMIT 1
                SQL,
            [$table, $index],
        );

        return $row === null ? null : (int) $row->NON_UNIQUE;
    }

    private function showCreate(string $table): string
    {
        $row = (array) DB::selectOne('SHOW CREATE TABLE `'.$table.'`');

        return (string) array_values($row)[1];
    }

    private function assertMigrationRan(string $migration): void
    {
        $this->assertTrue(DB::table('migrations')->where('migration', $migration)->exists());
    }

    private function bookingId(): int
    {
        $scenario = $this->bookingScenario(false);

        return (int) $this->bookingForScenario($scenario)->id;
    }

    private function insertPayment(int $bookingId, string $status): int
    {
        return (int) DB::table('payments')->insertGetId([
            'booking_id' => $bookingId,
            'provider' => 'zalopay',
            'payment_method' => 'zalopay',
            'amount' => 50000,
            'status' => $status,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
