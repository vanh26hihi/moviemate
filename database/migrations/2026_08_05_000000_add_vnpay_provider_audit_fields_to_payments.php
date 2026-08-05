<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const TRANSACTION_UNIQUE = 'payments_provider_transaction_id_unique';

    public function up(): void
    {
        if (! Schema::hasTable('payments')) {
            throw new RuntimeException('Cannot add VNPAY audit fields because the payments table is missing. No DDL was executed.');
        }

        if (! Schema::hasColumn('payments', 'provider_transaction_created_at')) {
            Schema::table('payments', function (Blueprint $table): void {
                $table->timestamp('provider_transaction_created_at')->nullable()->after('transaction_id');
            });
        }
        if (! Schema::hasColumn('payments', 'provider_paid_at')) {
            Schema::table('payments', function (Blueprint $table): void {
                $table->timestamp('provider_paid_at')->nullable()->after('provider_transaction_created_at');
            });
        }

        $named = collect(Schema::getIndexes('payments'))->firstWhere('name', self::TRANSACTION_UNIQUE);
        if ($named !== null
            && (! (bool) ($named['unique'] ?? false)
                || ($named['columns'] ?? []) !== ['provider', 'transaction_id'])) {
            throw new RuntimeException(
                self::TRANSACTION_UNIQUE.' has an unexpected definition. No index DDL was executed.',
            );
        }
        if ($this->transactionIndexName() === null) {
            Schema::table('payments', function (Blueprint $table): void {
                $table->unique(['provider', 'transaction_id'], self::TRANSACTION_UNIQUE);
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('payments')) {
            return;
        }
        if (DB::table('payments')->where('provider', 'vnpay')->exists()
            || (Schema::hasColumn('payments', 'provider_transaction_created_at')
                && DB::table('payments')->whereNotNull('provider_transaction_created_at')->exists())
            || (Schema::hasColumn('payments', 'provider_paid_at')
                && DB::table('payments')->whereNotNull('provider_paid_at')->exists())) {
            throw new RuntimeException(
                'Cannot roll back VNPAY audit fields while protected provider payment data exists. No DDL was executed; no rows or schema objects were changed.',
            );
        }

        $index = $this->transactionIndexName();
        if ($index !== null) {
            Schema::table('payments', function (Blueprint $table) use ($index): void {
                $table->dropUnique($index);
            });
        }

        $columns = array_values(array_filter(
            ['provider_paid_at', 'provider_transaction_created_at'],
            fn (string $column): bool => Schema::hasColumn('payments', $column),
        ));
        if ($columns !== []) {
            Schema::table('payments', function (Blueprint $table) use ($columns): void {
                $table->dropColumn($columns);
            });
        }
    }

    private function transactionIndexName(): ?string
    {
        $index = collect(Schema::getIndexes('payments'))->first(
            fn (array $candidate): bool => (bool) ($candidate['unique'] ?? false)
                && ($candidate['columns'] ?? []) === ['provider', 'transaction_id'],
        );

        return $index['name'] ?? null;
    }
};
