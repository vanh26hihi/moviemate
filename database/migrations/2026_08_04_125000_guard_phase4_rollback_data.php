<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const PROTECTED_TABLES = [
        'bookings',
        'booking_seats',
        'payments',
        'orders',
        'order_items',
        'booking_ticket_deliveries',
        'payment_review_events',
    ];

    public function up(): void
    {
        // This forward-only compatibility migration protects the immutable
        // 115000 migration before an exact Phase-4 batch rollback begins.
    }

    public function down(): void
    {
        $counts = collect(self::PROTECTED_TABLES)
            ->filter(fn (string $table): bool => Schema::hasTable($table))
            ->mapWithKeys(fn (string $table): array => [$table => DB::table($table)->count()])
            ->filter(fn (int $count): bool => $count > 0);

        if ($counts->isEmpty()) {
            return;
        }

        $summary = $counts
            ->map(fn (int $count, string $table): string => "{$table}={$count}")
            ->implode(', ');

        throw new RuntimeException(
            'Cannot roll back the Phase-4 migration batch while protected business data exists: '
            .$summary.'. Archive or resolve the data and use a dedicated rehearsal database; no rows or schema objects were changed.',
        );
    }
};
