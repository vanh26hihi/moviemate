<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const INDEX = 'bookings_ticket_email_token_hash_unique';

    private const COLUMNS = [
        'ticket_email_token_nonce',
        'ticket_email_token_hash',
        'ticket_email_token_expires_at',
    ];

    public function up(): void
    {
        if (! Schema::hasColumn('bookings', 'ticket_email_token_nonce')) {
            Schema::table('bookings', function (Blueprint $table): void {
                $table->char('ticket_email_token_nonce', 43)
                    ->nullable()
                    ->after('guest_access_expires_at');
            });
        }

        if (! Schema::hasColumn('bookings', 'ticket_email_token_hash')) {
            Schema::table('bookings', function (Blueprint $table): void {
                $table->char('ticket_email_token_hash', 64)
                    ->nullable()
                    ->after('ticket_email_token_nonce');
            });
        }

        if (! Schema::hasColumn('bookings', 'ticket_email_token_expires_at')) {
            Schema::table('bookings', function (Blueprint $table): void {
                $table->timestamp('ticket_email_token_expires_at')
                    ->nullable()
                    ->after('ticket_email_token_hash');
            });
        }

        if (! $this->hasIndex()) {
            Schema::table('bookings', function (Blueprint $table): void {
                $table->unique('ticket_email_token_hash', self::INDEX);
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('bookings')) {
            return;
        }

        $columns = array_values(array_filter(
            self::COLUMNS,
            fn (string $column): bool => Schema::hasColumn('bookings', $column),
        ));
        if ($columns === []) {
            return;
        }

        $activeCredential = DB::table('bookings')->where(function ($query) use ($columns): void {
            foreach ($columns as $column) {
                $query->orWhereNotNull($column);
            }
        })->exists();
        if ($activeCredential) {
            throw new RuntimeException(
                'Cannot roll back ticket email access columns while credential data exists.',
            );
        }

        if ($this->hasIndex()) {
            Schema::table('bookings', function (Blueprint $table): void {
                $table->dropUnique(self::INDEX);
            });
        }

        Schema::table('bookings', function (Blueprint $table) use ($columns): void {
            $table->dropColumn($columns);
        });
    }

    private function hasIndex(): bool
    {
        if (! Schema::hasTable('bookings')) {
            return false;
        }

        return collect(Schema::getIndexes('bookings'))->contains(
            fn (array $index): bool => ($index['name'] ?? null) === self::INDEX,
        );
    }
};
