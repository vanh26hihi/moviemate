<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const USER_FOREIGN = 'bookings_user_id_foreign';

    private const GUEST_TOKEN_UNIQUE = 'bookings_guest_access_token_hash_unique';

    private const IDEMPOTENCY_UNIQUE = 'bookings_checkout_idempotency_key_hash_unique';

    private const EXPIRATION_INDEX = 'bookings_expiration_lookup_index';

    public function up(): void
    {
        if (! Schema::hasTable('bookings')) {
            throw new RuntimeException('Cannot harden booking foundations because the bookings table is missing. No DDL was executed.');
        }

        $userForeign = $this->userForeign();
        if ($userForeign !== null && strtolower((string) ($userForeign['on_delete'] ?? '')) !== 'set null') {
            $this->dropUserForeign($userForeign);
        }

        if (! $this->columnIsNullable('bookings', 'user_id')) {
            Schema::table('bookings', function (Blueprint $table): void {
                $table->foreignId('user_id')->nullable()->change();
            });
        }

        if (! Schema::hasColumn('bookings', 'guest_access_token_hash')) {
            Schema::table('bookings', function (Blueprint $table): void {
                $table->char('guest_access_token_hash', 64)->nullable()->after('customer_email');
            });
        }
        if (! Schema::hasColumn('bookings', 'checkout_idempotency_key_hash')) {
            Schema::table('bookings', function (Blueprint $table): void {
                $table->char('checkout_idempotency_key_hash', 64)->nullable()->after('guest_access_token_hash');
            });
        }

        $this->ensureIndex(self::GUEST_TOKEN_UNIQUE, ['guest_access_token_hash'], true);
        $this->ensureIndex(self::IDEMPOTENCY_UNIQUE, ['checkout_idempotency_key_hash'], true);
        $this->ensureIndex(self::EXPIRATION_INDEX, ['booking_status', 'expires_at'], false);

        if ($this->userForeign() === null) {
            Schema::table('bookings', function (Blueprint $table): void {
                $table->foreign('user_id', self::USER_FOREIGN)
                    ->references('id')->on('users')->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        $this->assertRollbackPreservesBookings();

        $this->dropIndexIfPresent(self::GUEST_TOKEN_UNIQUE, ['guest_access_token_hash'], true);
        $this->dropIndexIfPresent(self::IDEMPOTENCY_UNIQUE, ['checkout_idempotency_key_hash'], true);
        $this->dropIndexIfPresent(self::EXPIRATION_INDEX, ['booking_status', 'expires_at'], false);

        $columns = array_values(array_filter(
            ['guest_access_token_hash', 'checkout_idempotency_key_hash'],
            fn (string $column): bool => Schema::hasColumn('bookings', $column),
        ));
        if ($columns !== []) {
            Schema::table('bookings', function (Blueprint $table) use ($columns): void {
                $table->dropColumn($columns);
            });
        }

        $userForeign = $this->userForeign();
        if ($userForeign !== null && strtolower((string) ($userForeign['on_delete'] ?? '')) !== 'set null') {
            $this->dropUserForeign($userForeign);
        }

        if (! $this->columnIsNullable('bookings', 'user_id')) {
            Schema::table('bookings', function (Blueprint $table): void {
                $table->foreignId('user_id')->nullable()->change();
            });
        }

        if ($this->userForeign() === null) {
            Schema::table('bookings', function (Blueprint $table): void {
                $table->foreign('user_id', self::USER_FOREIGN)
                    ->references('id')->on('users')->nullOnDelete();
            });
        }
    }

    private function assertRollbackPreservesBookings(): void
    {
        if (! Schema::hasTable('bookings')) {
            return;
        }

        foreach (['guest_access_token_hash', 'checkout_idempotency_key_hash'] as $column) {
            if (Schema::hasColumn('bookings', $column) && DB::table('bookings')->whereNotNull($column)->exists()) {
                throw new RuntimeException(
                    "Cannot roll back booking foundations while protected {$column} data exists. No rows or schema objects were changed.",
                );
            }
        }
    }

    /** @param list<string> $columns */
    private function ensureIndex(string $expectedName, array $columns, bool $unique): void
    {
        $named = collect(Schema::getIndexes('bookings'))->firstWhere('name', $expectedName);
        if ($named !== null && (($named['columns'] ?? []) !== $columns || (bool) ($named['unique'] ?? false) !== $unique)) {
            throw new RuntimeException(
                "Cannot continue booking foundation migration because index {$expectedName} has an unexpected definition. No DDL was executed for that index.",
            );
        }

        $matching = collect(Schema::getIndexes('bookings'))->contains(
            fn (array $index): bool => ($index['columns'] ?? []) === $columns
                && (bool) ($index['unique'] ?? false) === $unique,
        );
        if ($matching) {
            return;
        }

        Schema::table('bookings', function (Blueprint $table) use ($expectedName, $columns, $unique): void {
            $unique ? $table->unique($columns, $expectedName) : $table->index($columns, $expectedName);
        });
    }

    /** @param list<string> $columns */
    private function dropIndexIfPresent(string $expectedName, array $columns, bool $unique): void
    {
        $index = collect(Schema::getIndexes('bookings'))->first(
            fn (array $candidate): bool => ($candidate['name'] ?? null) === $expectedName
                || (($candidate['columns'] ?? []) === $columns
                    && (bool) ($candidate['unique'] ?? false) === $unique),
        );
        if ($index === null) {
            return;
        }

        Schema::table('bookings', function (Blueprint $table) use ($index, $unique): void {
            $unique ? $table->dropUnique($index['name']) : $table->dropIndex($index['name']);
        });
    }

    /** @return array<string, mixed>|null */
    private function userForeign(): ?array
    {
        if (! Schema::hasTable('bookings')) {
            return null;
        }

        return collect(Schema::getForeignKeys('bookings'))->first(
            fn (array $foreign): bool => ($foreign['columns'] ?? []) === ['user_id']
                && ($foreign['foreign_table'] ?? null) === 'users'
                && ($foreign['foreign_columns'] ?? []) === ['id'],
        );
    }

    /** @param array<string, mixed> $foreign */
    private function dropUserForeign(array $foreign): void
    {
        Schema::table('bookings', function (Blueprint $table) use ($foreign): void {
            $table->dropForeign(
                DB::getDriverName() === 'sqlite' ? ['user_id'] : (string) $foreign['name'],
            );
        });
    }

    private function columnIsNullable(string $tableName, string $columnName): bool
    {
        $column = collect(Schema::getColumns($tableName))->firstWhere('name', $columnName);
        if ($column === null) {
            throw new RuntimeException("Cannot continue migration because {$tableName}.{$columnName} is missing. No DDL was executed.");
        }

        return (bool) $column['nullable'];
    }
};
