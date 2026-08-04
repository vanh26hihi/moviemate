<?php

namespace Tests\Feature\Payments;

use App\Models\Payment;
use Illuminate\Database\QueryException;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

class PaymentAttemptSchemaTest extends PaymentTestCase
{
    public function test_payment_attempt_audit_schema_and_indexes_are_present(): void
    {
        $this->assertTrue(Schema::hasColumns('payments', [
            'booking_id', 'provider', 'app_id', 'app_trans_id', 'app_user', 'app_time_ms',
            'amount', 'currency', 'status', 'active_attempt_key', 'description', 'expires_at',
            'reconcile_until', 'zp_trans_id',
            'zp_trans_token', 'order_token', 'order_url', 'qr_code', 'provider_return_code',
            'provider_sub_return_code', 'provider_return_message', 'provider_sub_return_message',
            'server_time_ms', 'callback_received_at', 'last_queried_at', 'verified_at',
            'paid_at', 'failed_at', 'failure_reason', 'create_response_hash',
            'callback_payload_hash', 'query_response_hash',
        ]));
        $this->assertTrue(Schema::hasColumn('bookings', 'ticket_emailed_at'));
        $this->assertTrue(Schema::hasColumns('booking_ticket_deliveries', [
            'booking_id', 'status', 'attempts', 'available_at', 'processing_started_at',
            'lease_expires_at', 'sent_at', 'last_error_code',
        ]));
        foreach (['guest_token', 'email_body', 'secret', 'password', 'mac'] as $forbiddenColumn) {
            $this->assertFalse(Schema::hasColumn('booking_ticket_deliveries', $forbiddenColumn));
        }
        $this->assertFalse(Schema::hasColumn('payments', 'key1'));
        $this->assertFalse(Schema::hasColumn('payments', 'key2'));
        $this->assertFalse(Schema::hasColumn('payments', 'mac'));
    }

    public function test_booking_supports_many_attempts_and_singular_relationship_returns_latest(): void
    {
        $booking = $this->payableBooking();
        $first = $this->pendingPayment($booking, ['status' => Payment::STATUS_FAILED]);
        $second = $this->pendingPayment($booking);

        $this->assertCount(2, $booking->payments);
        $this->assertSame($second->id, $booking->payment->id);
        $this->assertNotSame($first->id, $booking->payment->id);
    }

    public function test_provider_app_identity_is_unique(): void
    {
        $payment = $this->pendingPayment();

        $this->expectException(QueryException::class);
        $this->pendingPayment($payment->booking, [
            'app_id' => $payment->app_id,
            'app_trans_id' => $payment->app_trans_id,
        ]);
    }

    public function test_nullable_zp_transaction_identity_is_unique_when_present(): void
    {
        $this->pendingPayment(overrides: ['zp_trans_id' => '123456789']);

        $this->expectException(QueryException::class);
        $this->pendingPayment(overrides: ['zp_trans_id' => '123456789']);
    }

    public function test_database_rejects_a_second_attempt_when_an_unresolved_attempt_exists(): void
    {
        $booking = $this->payableBooking();
        $first = $this->pendingPayment($booking, ['status' => Payment::STATUS_UNRESOLVED]);
        $this->assertSame('ACTIVE', $first->fresh()->active_attempt_key);

        $this->expectException(QueryException::class);
        $this->pendingPayment($booking);
    }

    public function test_database_keeps_manual_review_locked_against_automatic_replacement(): void
    {
        $booking = $this->payableBooking();
        $review = $this->pendingPayment($booking, ['status' => Payment::STATUS_REVIEW]);
        $this->assertSame('ACTIVE', $review->fresh()->active_attempt_key);

        $this->expectException(QueryException::class);
        $this->pendingPayment($booking);
    }

    public function test_historical_final_attempts_are_preserved_when_a_new_attempt_is_created(): void
    {
        $booking = $this->payableBooking();
        $failed = $this->pendingPayment($booking, [
            'status' => Payment::STATUS_FAILED,
            'failure_reason' => 'provider_rejected',
        ]);
        $successful = $this->pendingPayment($booking, [
            'status' => Payment::STATUS_SUCCESS,
            'zp_trans_id' => '60000001',
        ]);
        $active = $this->pendingPayment($booking);

        $this->assertDatabaseCount('payments', 3);
        $this->assertNull($failed->fresh()->active_attempt_key);
        $this->assertNull($successful->fresh()->active_attempt_key);
        $this->assertSame('ACTIVE', $active->fresh()->active_attempt_key);
    }

    public function test_zalopay_schema_migration_can_roundtrip_and_preserve_payment_history(): void
    {
        [$zalopayMigration, $reconciliationMigration] = $this->paymentMigrations();
        $payment = $this->pendingPayment(overrides: [
            'provider' => 'vnpay',
            'payment_method' => 'vnpay',
            'status' => Payment::STATUS_FAILED,
        ]);

        $reconciliationMigration->down();
        $zalopayMigration->down();

        $this->assertZaloPayColumnsAreMissing();
        $this->assertSame(1, DB::table('payments')->where('id', $payment->id)->count());
        $this->assertBookingForeignKeyDeleteAction('cascade');

        $zalopayMigration->up();
        $reconciliationMigration->up();

        $this->assertZaloPayColumnsExist();
        $this->assertSame(1, DB::table('payments')->where('id', $payment->id)->count());
        $this->assertBookingForeignKeyDeleteAction('restrict');
    }

    public function test_reconciliation_migration_aborts_before_ddl_for_duplicate_unresolved_attempts(): void
    {
        [, $reconciliationMigration] = $this->paymentMigrations();
        $booking = $this->payableBooking();
        $first = $this->pendingPayment($booking);

        $reconciliationMigration->down();
        $secondId = DB::table('payments')->insertGetId([
            'booking_id' => $booking->id,
            'provider' => 'zalopay',
            'payment_method' => 'zalopay',
            'app_id' => 2553,
            'app_trans_id' => now('Asia/Ho_Chi_Minh')->format('ymd').'_duplicate_unresolved',
            'app_user' => 'migration-preflight',
            'app_time_ms' => (int) floor(microtime(true) * 1000),
            'amount' => 50000,
            'currency' => 'VND',
            'status' => Payment::STATUS_UNRESOLVED,
            'description' => 'Migration preflight fixture',
            'expires_at' => now()->addMinutes(10),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $statusesBefore = DB::table('payments')->orderBy('id')->pluck('status', 'id')->all();

        $exception = null;
        try {
            $reconciliationMigration->up();
        } catch (RuntimeException $caught) {
            $exception = $caught;
        }

        $this->assertInstanceOf(RuntimeException::class, $exception);
        $this->assertStringContainsString("booking_id={$booking->id}", $exception->getMessage());
        $this->assertStringContainsString("payment_ids={$first->id},{$secondId}", $exception->getMessage());
        $this->assertFalse(Schema::hasColumn('payments', 'reconcile_until'));
        $this->assertFalse(Schema::hasColumn('payments', 'active_attempt_key'));
        $this->assertFalse(Schema::hasTable('booking_ticket_deliveries'));
        $this->assertSame($statusesBefore, DB::table('payments')->orderBy('id')->pluck('status', 'id')->all());

        DB::table('payments')->where('id', $secondId)->delete();
        $reconciliationMigration->up();
    }

    public function test_zalopay_down_handles_a_missing_foreign_key_while_booking_index_remains(): void
    {
        [$zalopayMigration, $reconciliationMigration] = $this->paymentMigrations();
        $reconciliationMigration->down();

        Schema::table('payments', function (Blueprint $table): void {
            $table->dropForeign(['booking_id']);
        });

        $this->assertTrue($this->hasPaymentIndex('payments_booking_status_index'));

        $zalopayMigration->down();

        $this->assertZaloPayColumnsAreMissing();
        $this->assertBookingForeignKeyDeleteAction('cascade');

        $zalopayMigration->up();
        $reconciliationMigration->up();
        $this->assertZaloPayColumnsExist();
    }

    public function test_zalopay_down_is_safe_for_missing_indexes_and_columns_after_partial_ddl(): void
    {
        [$zalopayMigration, $reconciliationMigration] = $this->paymentMigrations();
        $reconciliationMigration->down();

        Schema::table('payments', function (Blueprint $table): void {
            $table->dropForeign(['booking_id']);
        });
        Schema::table('payments', function (Blueprint $table): void {
            $table->dropUnique('payments_provider_app_trans_unique');
            $table->dropIndex('payments_provider_status_expiry_index');
        });
        Schema::table('payments', function (Blueprint $table): void {
            $table->dropColumn('failure_reason');
        });
        Schema::table('bookings', function (Blueprint $table): void {
            $table->dropColumn('ticket_emailed_at');
        });

        $zalopayMigration->down();
        $zalopayMigration->down();
        $this->assertZaloPayColumnsAreMissing();
        $this->assertBookingForeignKeyDeleteAction('cascade');

        $zalopayMigration->up();
        $reconciliationMigration->up();
        $this->assertZaloPayColumnsExist();
    }

    public function test_zalopay_down_declares_foreign_key_drop_before_booking_index_drop(): void
    {
        $source = file_get_contents(
            database_path('migrations/2026_08_04_110000_extend_payments_for_zalopay.php'),
        );

        $foreignDrop = strpos($source, '$table->dropForeign(self::BOOKING_FOREIGN)');
        $indexLoop = strpos($source, 'foreach (self::INDEXES as $indexName => $indexType)');

        $this->assertNotFalse($foreignDrop);
        $this->assertNotFalse($indexLoop);
        $this->assertLessThan($indexLoop, $foreignDrop);
    }

    /** @return array{0: object, 1: object} */
    private function paymentMigrations(): array
    {
        return [
            require database_path('migrations/2026_08_04_110000_extend_payments_for_zalopay.php'),
            require database_path('migrations/2026_08_04_115000_add_payment_reconciliation_and_ticket_outbox.php'),
        ];
    }

    private function assertZaloPayColumnsAreMissing(): void
    {
        foreach ($this->zaloPayColumns() as $column) {
            $this->assertFalse(Schema::hasColumn('payments', $column));
        }
        $this->assertFalse(Schema::hasColumn('bookings', 'ticket_emailed_at'));

        foreach ($this->zaloPayIndexes() as $index) {
            $this->assertFalse($this->hasPaymentIndex($index));
        }
    }

    private function assertZaloPayColumnsExist(): void
    {
        $this->assertTrue(Schema::hasColumns('payments', $this->zaloPayColumns()));
        $this->assertTrue(Schema::hasColumn('bookings', 'ticket_emailed_at'));

        foreach ($this->zaloPayIndexes() as $index) {
            $this->assertTrue($this->hasPaymentIndex($index));
        }
    }

    private function assertBookingForeignKeyDeleteAction(string $expected): void
    {
        $foreignKey = collect(Schema::getForeignKeys('payments'))->first(
            fn (array $key): bool => ($key['columns'] ?? []) === ['booking_id'],
        );

        $this->assertNotNull($foreignKey);
        $this->assertSame($expected, strtolower((string) $foreignKey['on_delete']));
    }

    private function hasPaymentIndex(string $name): bool
    {
        return collect(Schema::getIndexes('payments'))->contains(
            fn (array $index): bool => ($index['name'] ?? null) === $name,
        );
    }

    /** @return list<string> */
    private function zaloPayColumns(): array
    {
        return [
            'app_id', 'app_trans_id', 'app_user', 'app_time_ms', 'currency', 'description',
            'expires_at', 'zp_trans_id', 'zp_trans_token', 'order_token', 'order_url', 'qr_code',
            'provider_return_code', 'provider_sub_return_code', 'provider_return_message',
            'provider_sub_return_message', 'server_time_ms', 'callback_received_at',
            'last_queried_at', 'verified_at', 'failed_at', 'failure_reason',
            'create_response_hash', 'callback_payload_hash', 'query_response_hash',
        ];
    }

    /** @return list<string> */
    private function zaloPayIndexes(): array
    {
        return [
            'payments_provider_app_trans_unique',
            'payments_provider_zp_trans_unique',
            'payments_booking_status_index',
            'payments_provider_status_expiry_index',
        ];
    }
}
