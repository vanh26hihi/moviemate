<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const INDEX = 'payments_booking_active_attempt_unique';

    private const COLUMN = 'booking_attempt_guard';

    private const COLUMNS = ['booking_id', self::COLUMN];

    private const BLOCKING_STATUSES = ['pending', 'processing', 'unresolved', 'review'];

    public function up(): void
    {
        if (DB::connection()->pretending()) {
            $this->compileSchemaChanges();

            return;
        }

        if (! Schema::hasTable('payments') || ! Schema::hasColumn('payments', 'status')) {
            throw new RuntimeException('Cannot enforce one active payment attempt because the payment schema is incomplete. No DDL was executed.');
        }

        $named = collect(Schema::getIndexes('payments'))->firstWhere('name', self::INDEX);
        if ($named !== null) {
            if (! (bool) ($named['unique'] ?? false) || ($named['columns'] ?? []) !== self::COLUMNS) {
                throw new RuntimeException(self::INDEX.' has an unexpected definition. No DDL was executed.');
            }

            return;
        }

        $duplicate = DB::table('payments')
            ->select('booking_id', DB::raw('COUNT(*) AS aggregate'))
            ->whereIn('status', self::BLOCKING_STATUSES)
            ->groupBy('booking_id')
            ->havingRaw('COUNT(*) > 1')
            ->orderBy('booking_id')
            ->first();
        if ($duplicate !== null) {
            throw new RuntimeException(
                'Cannot enforce one active payment attempt for booking_id='.(string) $duplicate->booking_id
                .' because multiple provider attempts are unresolved. Resolve them explicitly and retry. No DDL was executed.',
            );
        }

        $this->compileSchemaChanges(! Schema::hasColumn('payments', self::COLUMN));
    }

    public function down(): void
    {
        if (! Schema::hasTable('payments')) {
            return;
        }
        $named = collect(Schema::getIndexes('payments'))->firstWhere('name', self::INDEX);
        if ($named === null) {
            return;
        }
        if (! (bool) ($named['unique'] ?? false) || ($named['columns'] ?? []) !== self::COLUMNS) {
            throw new RuntimeException(self::INDEX.' has an unexpected definition. No DDL was executed.');
        }

        Schema::table('payments', function (Blueprint $table): void {
            $table->dropUnique(self::INDEX);
        });
        if (Schema::hasColumn('payments', self::COLUMN)) {
            Schema::table('payments', function (Blueprint $table): void {
                $table->dropColumn(self::COLUMN);
            });
        }
    }

    private function compileSchemaChanges(bool $addColumn = true): void
    {
        if ($addColumn) {
            Schema::table('payments', function (Blueprint $table): void {
                $table->string(self::COLUMN, 16)
                    ->nullable()
                    ->virtualAs("case when status in ('pending', 'processing', 'unresolved', 'review') then 'ACTIVE' else null end");
            });
        }
        Schema::table('payments', function (Blueprint $table): void {
            $table->unique(self::COLUMNS, self::INDEX);
        });
    }
};
