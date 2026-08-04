<?php

namespace Tests\Feature\Payments;

use App\Models\Payment;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Symfony\Component\Process\Process;

class PaymentUpgradeMigrationTest extends PaymentTestCase
{
    private const OLD = "case when status in ('pending', 'processing') then 'ACTIVE' else null end";

    private const NEW = "case when status in ('pending', 'processing', 'unresolved', 'review') then 'ACTIVE' else null end";

    public function test_immutable_115000_matches_commit_053b132_exactly(): void
    {
        $path = 'database/migrations/2026_08_04_115000_add_payment_reconciliation_and_ticket_outbox.php';
        $process = new Process(['git', 'show', '053b132:'.$path], base_path());
        $process->mustRun();

        $this->assertSame($process->getOutput(), file_get_contents(base_path($path)));
    }

    public function test_fresh_sequence_creates_old_expression_then_upgrades_to_hardened_expression(): void
    {
        $upgrade = $this->upgradeMigration();
        $old = $this->oldMigration();

        $upgrade->down();
        $old->down();
        $old->up();
        $this->assertExpression(self::OLD);

        $upgrade->up();
        $this->assertExpression(self::NEW);
        $this->assertCorrectIndex();
    }

    public function test_full_old_upgrades_to_hardened_expression(): void
    {
        $migration = $this->upgradeMigration();
        $migration->down();
        $this->assertExpression(self::OLD);

        $migration->up();

        $this->assertExpression(self::NEW);
        $this->assertCorrectIndex();
    }

    public function test_full_new_is_verified_without_schema_rewrite(): void
    {
        $before = $this->paymentsTableSql();
        $indexBefore = Schema::getIndexes('payments');

        $this->upgradeMigration()->up();

        $this->assertSame($before, $this->paymentsTableSql());
        $this->assertSame($indexBefore, Schema::getIndexes('payments'));
        $this->assertExpression(self::NEW);
    }

    public function test_old_without_index_is_repaired(): void
    {
        $migration = $this->upgradeMigration();
        $migration->down();
        $this->dropActiveIndexIfPresent();

        $migration->up();

        $this->assertExpression(self::NEW);
        $this->assertCorrectIndex();
    }

    public function test_new_without_index_is_repaired(): void
    {
        $this->dropActiveIndexIfPresent();

        $this->upgradeMigration()->up();

        $this->assertExpression(self::NEW);
        $this->assertCorrectIndex();
    }

    public function test_unknown_expression_aborts_before_ddl_and_preserves_rows(): void
    {
        $payment = $this->pendingPayment();
        $this->replaceExpression("case when status = 'pending' then 'ACTIVE' else null end");
        $statuses = DB::table('payments')->pluck('status', 'id')->all();
        $schema = $this->paymentsTableSql();

        try {
            $this->upgradeMigration()->up();
            $this->fail('Unknown generated expressions must be rejected.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('unknown generated expression', $exception->getMessage());
            $this->assertStringContainsString('No DDL was executed', $exception->getMessage());
        }

        $this->assertSame($schema, $this->paymentsTableSql());
        $this->assertSame($statuses, DB::table('payments')->pluck('status', 'id')->all());
        $this->assertSame(Payment::STATUS_PENDING, $payment->fresh()->status);
    }

    public function test_wrong_index_columns_and_order_abort_before_ddl(): void
    {
        $this->dropActiveIndexIfPresent();
        DB::statement(
            'CREATE UNIQUE INDEX "payments_one_active_attempt_unique" '
            .'ON "payments" ("provider", "booking_id", "active_attempt_key")',
        );
        $schema = $this->paymentsTableSql();

        try {
            $this->upgradeMigration()->up();
            $this->fail('Wrong index order must be rejected.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('wrong uniqueness, columns, order, or prefix', $exception->getMessage());
            $this->assertStringContainsString('#1 provider unique', $exception->getMessage());
        }

        $this->assertSame($schema, $this->paymentsTableSql());
    }

    public function test_duplicate_unresolved_and_review_group_aborts_with_actionable_safe_details(): void
    {
        $migration = $this->upgradeMigration();
        $migration->down();
        $booking = $this->payableBooking();
        $first = $this->pendingPayment($booking, ['status' => Payment::STATUS_UNRESOLVED]);
        $second = $this->pendingPayment($booking, ['status' => Payment::STATUS_REVIEW]);
        $statuses = DB::table('payments')->orderBy('id')->pluck('status', 'id')->all();

        try {
            $migration->up();
            $this->fail('Duplicate blocking attempts must be rejected.');
        } catch (RuntimeException $exception) {
            $message = $exception->getMessage();
            $this->assertStringContainsString("booking_id={$booking->id}", $message);
            $this->assertStringContainsString('provider=zalopay', $message);
            $this->assertStringContainsString("{$first->id}:unresolved|{$second->id}:review", $message);
            $this->assertStringNotContainsString('test-key', $message);
            $this->assertStringNotContainsString('callback', $message);
        }

        $this->assertSame($statuses, DB::table('payments')->orderBy('id')->pluck('status', 'id')->all());
        $this->assertExpression(self::OLD);
    }

    #[DataProvider('blockingStatuses')]
    public function test_database_enforces_one_direct_blocking_attempt(string $status): void
    {
        $booking = $this->payableBooking();
        $first = $this->pendingPayment($booking, ['status' => $status]);
        $this->assertSame('ACTIVE', $first->fresh()->active_attempt_key);

        $this->expectException(QueryException::class);
        $this->pendingPayment($booking, ['status' => $status]);
    }

    /** @return array<string, array{string}> */
    public static function blockingStatuses(): array
    {
        return [
            'pending' => [Payment::STATUS_PENDING],
            'processing' => [Payment::STATUS_PROCESSING],
            'unresolved' => [Payment::STATUS_UNRESOLVED],
            'review' => [Payment::STATUS_REVIEW],
        ];
    }

    public function test_final_statuses_preserve_multiple_null_key_history_rows(): void
    {
        $booking = $this->payableBooking();
        $rows = collect([
            $this->pendingPayment($booking, ['status' => Payment::STATUS_SUCCESS, 'zp_trans_id' => '81001']),
            $this->pendingPayment($booking, ['status' => Payment::STATUS_FAILED]),
            $this->pendingPayment($booking, ['status' => Payment::STATUS_EXPIRED]),
            $this->pendingPayment($booking, ['status' => Payment::STATUS_SUCCESS, 'zp_trans_id' => '81002']),
        ]);

        $this->assertCount(4, $rows);
        $this->assertSame(4, DB::table('payments')->whereNull('active_attempt_key')->count());
        $rows->each(fn (Payment $payment) => $this->assertNull($payment->fresh()->active_attempt_key));
    }

    #[DataProvider('rollbackBlockingStatuses')]
    public function test_down_refuses_unresolved_or_review_rows(string $status): void
    {
        $payment = $this->pendingPayment(overrides: ['status' => $status]);

        try {
            $this->upgradeMigration()->down();
            $this->fail('Rollback must reject review-state data.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('unresolved or review payments exist', $exception->getMessage());
            $this->assertStringContainsString((string) $payment->id, $exception->getMessage());
        }

        $this->assertExpression(self::NEW);
        $this->assertSame($status, $payment->fresh()->status);
    }

    /** @return array<string, array{string}> */
    public static function rollbackBlockingStatuses(): array
    {
        return [
            'unresolved' => [Payment::STATUS_UNRESOLVED],
            'review' => [Payment::STATUS_REVIEW],
        ];
    }

    public function test_safe_down_restores_old_expression_without_dropping_protection(): void
    {
        $payment = $this->pendingPayment(overrides: ['status' => Payment::STATUS_FAILED]);

        $this->upgradeMigration()->down();

        $this->assertExpression(self::OLD);
        $this->assertCorrectIndex();
        $this->assertSame(Payment::STATUS_FAILED, $payment->fresh()->status);
    }

    public function test_mysql_inventory_and_atomic_alter_are_declared_explicitly(): void
    {
        $source = file_get_contents(database_path(
            'migrations/2026_08_04_121000_harden_active_payment_attempt_states.php',
        ));

        $this->assertStringContainsString('information_schema.TABLES', $source);
        $this->assertStringContainsString('information_schema.COLUMNS', $source);
        $this->assertStringContainsString('information_schema.STATISTICS', $source);
        $this->assertStringContainsString('MODIFY COLUMN `active_attempt_key`', $source);
        $this->assertStringContainsString("DROP INDEX `'.self::INDEX.'`", $source);
        $this->assertStringContainsString('ADD UNIQUE INDEX', $source);
        $this->assertStringContainsString("('pending', 'processing', 'unresolved', 'review')", $source);
    }

    public function test_mysql_charset_qualified_generated_expression_is_classified_as_old(): void
    {
        $migration = $this->upgradeMigration();
        $classify = new \ReflectionMethod($migration, 'classifyExpression');
        $mysqlExpression = "(case when (`status` in (_utf8mb4'pending',_utf8mb4'processing')) "
            ."then _utf8mb4'ACTIVE' else NULL end)";

        $this->assertSame('OLD', $classify->invoke($migration, $mysqlExpression));
    }

    public function test_provider_cannot_be_changed_by_ordinary_mass_assignment(): void
    {
        $payment = $this->pendingPayment();

        $payment->fill(['provider' => 'vnpay', 'description' => 'updated safely'])->save();

        $this->assertSame('zalopay', $payment->fresh()->provider);
        $this->assertSame('updated safely', $payment->fresh()->description);
        $this->assertNotContains('provider', $payment->getFillable());
    }

    public function test_explicit_provider_is_trimmed_lowercased_and_cannot_be_overridden(): void
    {
        $booking = $this->payableBooking();
        $payment = Payment::createForProvider(' ZaloPay ', [
            'booking_id' => $booking->id,
            'provider' => 'vnpay',
            'payment_method' => 'zalopay',
            'amount' => 50000,
            'status' => Payment::STATUS_PENDING,
        ]);

        $this->assertSame('zalopay', $payment->provider);
        $this->assertSame('zalopay', $payment->fresh()->provider);
    }

    #[DataProvider('invalidProviders')]
    public function test_invalid_provider_is_rejected_before_insert(string $provider): void
    {
        $count = Payment::query()->count();

        try {
            Payment::createForProvider($provider, [
                'booking_id' => PHP_INT_MAX,
                'payment_method' => 'invalid',
                'amount' => 1,
                'status' => Payment::STATUS_PENDING,
            ]);
            $this->fail('Invalid providers must be rejected.');
        } catch (\InvalidArgumentException $exception) {
            $this->assertSame('Unsupported payment provider.', $exception->getMessage());
        }

        $this->assertSame($count, Payment::query()->count());
    }

    /** @return array<string, array{string}> */
    public static function invalidProviders(): array
    {
        return [
            'empty' => [''],
            'whitespace' => ['   '],
            'unsupported' => ['stripe'],
        ];
    }

    private function oldMigration(): object
    {
        return require database_path('migrations/2026_08_04_115000_add_payment_reconciliation_and_ticket_outbox.php');
    }

    private function upgradeMigration(): object
    {
        return require database_path('migrations/2026_08_04_121000_harden_active_payment_attempt_states.php');
    }

    private function replaceExpression(string $expression): void
    {
        $this->dropActiveIndexIfPresent();
        DB::statement('ALTER TABLE "payments" DROP COLUMN "active_attempt_key"');
        DB::statement(
            'ALTER TABLE "payments" ADD COLUMN "active_attempt_key" VARCHAR(16) '
            ."GENERATED ALWAYS AS ({$expression}) VIRTUAL",
        );
        DB::statement(
            'CREATE UNIQUE INDEX "payments_one_active_attempt_unique" '
            .'ON "payments" ("booking_id", "provider", "active_attempt_key")',
        );
    }

    private function dropActiveIndexIfPresent(): void
    {
        if (collect(Schema::getIndexes('payments'))->contains(
            fn (array $index): bool => $index['name'] === 'payments_one_active_attempt_unique',
        )) {
            DB::statement('DROP INDEX "payments_one_active_attempt_unique"');
        }
    }

    private function assertExpression(string $expression): void
    {
        $this->assertStringContainsString($this->normalize($expression), $this->normalize($this->paymentsTableSql()));
    }

    private function assertCorrectIndex(): void
    {
        $index = collect(Schema::getIndexes('payments'))->firstWhere('name', 'payments_one_active_attempt_unique');

        $this->assertNotNull($index);
        $this->assertTrue($index['unique']);
        $this->assertSame(['booking_id', 'provider', 'active_attempt_key'], $index['columns']);
    }

    private function paymentsTableSql(): string
    {
        return DB::selectOne("SELECT sql FROM sqlite_master WHERE type = 'table' AND name = 'payments'")->sql;
    }

    private function normalize(string $value): string
    {
        return str_replace(
            ["\r", "\n", "\t", ' ', '`', '"', "'", '(', ')'],
            '',
            strtolower($value),
        );
    }
}
