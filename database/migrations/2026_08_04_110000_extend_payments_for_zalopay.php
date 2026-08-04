<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const BOOKING_FOREIGN = 'payments_booking_id_foreign';

    private const INDEXES = [
        'payments_provider_app_trans_unique' => ['columns' => ['provider', 'app_id', 'app_trans_id'], 'unique' => true],
        'payments_provider_zp_trans_unique' => ['columns' => ['provider', 'zp_trans_id'], 'unique' => true],
        'payments_booking_status_index' => ['columns' => ['booking_id', 'status'], 'unique' => false],
        'payments_provider_status_expiry_index' => ['columns' => ['provider', 'status', 'expires_at'], 'unique' => false],
    ];

    private const ZALOPAY_COLUMNS = [
        'app_id',
        'app_trans_id',
        'app_user',
        'app_time_ms',
        'currency',
        'description',
        'expires_at',
        'zp_trans_id',
        'zp_trans_token',
        'order_token',
        'order_url',
        'qr_code',
        'provider_return_code',
        'provider_sub_return_code',
        'provider_return_message',
        'provider_sub_return_message',
        'server_time_ms',
        'callback_received_at',
        'last_queried_at',
        'verified_at',
        'failed_at',
        'failure_reason',
        'create_response_hash',
        'callback_payload_hash',
        'query_response_hash',
    ];

    public function up(): void
    {
        if (! Schema::hasTable('payments') || ! Schema::hasTable('bookings')) {
            throw new RuntimeException('Cannot extend payments for ZaloPay because prerequisite tables are missing. No DDL was executed.');
        }

        $bookingForeign = $this->bookingForeign();
        if ($bookingForeign !== null && strtolower((string) ($bookingForeign['on_delete'] ?? '')) !== 'restrict') {
            $this->dropBookingForeign($bookingForeign);
        }

        Schema::table('payments', function (Blueprint $table): void {
            if (! Schema::hasColumn('payments', 'app_id')) {
                $table->unsignedBigInteger('app_id')->nullable()->after('provider');
            }
            if (! Schema::hasColumn('payments', 'app_trans_id')) {
                $table->string('app_trans_id', 40)->nullable()->after('app_id');
            }
            if (! Schema::hasColumn('payments', 'app_user')) {
                $table->string('app_user')->nullable()->after('app_trans_id');
            }
            if (! Schema::hasColumn('payments', 'app_time_ms')) {
                $table->unsignedBigInteger('app_time_ms')->nullable()->after('app_user');
            }
            if (! Schema::hasColumn('payments', 'currency')) {
                $table->char('currency', 3)->default('VND')->after('amount');
            }
            if (! Schema::hasColumn('payments', 'description')) {
                $table->string('description')->nullable()->after('currency');
            }
            if (! Schema::hasColumn('payments', 'expires_at')) {
                $table->timestamp('expires_at')->nullable()->after('description');
            }
            if (! Schema::hasColumn('payments', 'zp_trans_id')) {
                $table->string('zp_trans_id', 64)->nullable()->after('transaction_id');
            }
            if (! Schema::hasColumn('payments', 'zp_trans_token')) {
                $table->string('zp_trans_token')->nullable()->after('zp_trans_id');
            }
            if (! Schema::hasColumn('payments', 'order_token')) {
                $table->string('order_token')->nullable()->after('zp_trans_token');
            }
            if (! Schema::hasColumn('payments', 'order_url')) {
                $table->text('order_url')->nullable()->after('order_token');
            }
            if (! Schema::hasColumn('payments', 'qr_code')) {
                $table->text('qr_code')->nullable()->after('order_url');
            }
            if (! Schema::hasColumn('payments', 'provider_return_code')) {
                $table->integer('provider_return_code')->nullable()->after('qr_code');
            }
            if (! Schema::hasColumn('payments', 'provider_sub_return_code')) {
                $table->integer('provider_sub_return_code')->nullable()->after('provider_return_code');
            }
            if (! Schema::hasColumn('payments', 'provider_return_message')) {
                $table->string('provider_return_message')->nullable()->after('provider_sub_return_code');
            }
            if (! Schema::hasColumn('payments', 'provider_sub_return_message')) {
                $table->string('provider_sub_return_message')->nullable()->after('provider_return_message');
            }
            if (! Schema::hasColumn('payments', 'server_time_ms')) {
                $table->unsignedBigInteger('server_time_ms')->nullable()->after('provider_sub_return_message');
            }
            if (! Schema::hasColumn('payments', 'callback_received_at')) {
                $table->timestamp('callback_received_at')->nullable()->after('server_time_ms');
            }
            if (! Schema::hasColumn('payments', 'last_queried_at')) {
                $table->timestamp('last_queried_at')->nullable()->after('callback_received_at');
            }
            if (! Schema::hasColumn('payments', 'verified_at')) {
                $table->timestamp('verified_at')->nullable()->after('last_queried_at');
            }
            if (! Schema::hasColumn('payments', 'failed_at')) {
                $table->timestamp('failed_at')->nullable()->after('verified_at');
            }
            if (! Schema::hasColumn('payments', 'failure_reason')) {
                $table->string('failure_reason')->nullable()->after('failed_at');
            }
            if (! Schema::hasColumn('payments', 'create_response_hash')) {
                $table->char('create_response_hash', 64)->nullable()->after('failure_reason');
            }
            if (! Schema::hasColumn('payments', 'callback_payload_hash')) {
                $table->char('callback_payload_hash', 64)->nullable()->after('create_response_hash');
            }
            if (! Schema::hasColumn('payments', 'query_response_hash')) {
                $table->char('query_response_hash', 64)->nullable()->after('callback_payload_hash');
            }
        });

        foreach (self::INDEXES as $name => $definition) {
            $this->ensureIndex($name, $definition['columns'], $definition['unique']);
        }

        if ($this->bookingForeign() === null) {
            Schema::table('payments', function (Blueprint $table): void {
                $table->foreign('booking_id', self::BOOKING_FOREIGN)
                    ->references('id')->on('bookings')->restrictOnDelete();
            });
        }

        if (! Schema::hasColumn('bookings', 'ticket_emailed_at')) {
            Schema::table('bookings', function (Blueprint $table): void {
                $table->timestamp('ticket_emailed_at')->nullable()->after('paid_at');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('payments') && DB::table('payments')->exists()) {
            throw new RuntimeException(
                'Cannot roll back ZaloPay payment fields while payment history exists. No rows or schema objects were changed.',
            );
        }
        if (Schema::hasTable('bookings')
            && Schema::hasColumn('bookings', 'ticket_emailed_at')
            && DB::table('bookings')->whereNotNull('ticket_emailed_at')->exists()) {
            throw new RuntimeException(
                'Cannot roll back ZaloPay payment fields while ticket email history exists. No rows or schema objects were changed.',
            );
        }

        $bookingForeign = $this->bookingForeign();
        if ($bookingForeign !== null) {
            $this->dropBookingForeign($bookingForeign);
        }

        foreach (self::INDEXES as $name => $definition) {
            $this->dropIndexIfPresent($name, $definition['columns'], $definition['unique']);
        }

        $this->dropColumnsIfPresent('payments', self::ZALOPAY_COLUMNS);
        $this->dropColumnsIfPresent('bookings', ['ticket_emailed_at']);

        if (Schema::hasTable('payments')
            && Schema::hasColumn('payments', 'booking_id')
            && Schema::hasTable('bookings')
            && Schema::hasColumn('bookings', 'id')
            && $this->bookingForeign() === null) {
            Schema::table('payments', function (Blueprint $table): void {
                $table->foreign('booking_id', self::BOOKING_FOREIGN)
                    ->references('id')->on('bookings')->cascadeOnDelete();
            });
        }
    }

    /** @param list<string> $columns */
    private function ensureIndex(string $expectedName, array $columns, bool $unique): void
    {
        $named = collect(Schema::getIndexes('payments'))->firstWhere('name', $expectedName);
        if ($named !== null && (($named['columns'] ?? []) !== $columns || (bool) ($named['unique'] ?? false) !== $unique)) {
            throw new RuntimeException(
                "Cannot continue ZaloPay migration because index {$expectedName} has an unexpected definition. No DDL was executed for that index.",
            );
        }

        if (collect(Schema::getIndexes('payments'))->contains(
            fn (array $index): bool => ($index['columns'] ?? []) === $columns
                && (bool) ($index['unique'] ?? false) === $unique,
        )) {
            return;
        }

        Schema::table('payments', function (Blueprint $table) use ($expectedName, $columns, $unique): void {
            $unique ? $table->unique($columns, $expectedName) : $table->index($columns, $expectedName);
        });
    }

    /** @param list<string> $columns */
    private function dropIndexIfPresent(string $expectedName, array $columns, bool $unique): void
    {
        if (! Schema::hasTable('payments')) {
            return;
        }

        $index = collect(Schema::getIndexes('payments'))->first(
            fn (array $candidate): bool => ($candidate['name'] ?? null) === $expectedName
                || (($candidate['columns'] ?? []) === $columns
                    && (bool) ($candidate['unique'] ?? false) === $unique),
        );
        if ($index === null) {
            return;
        }

        Schema::table('payments', function (Blueprint $table) use ($index, $unique): void {
            $unique ? $table->dropUnique($index['name']) : $table->dropIndex($index['name']);
        });
    }

    /** @param list<string> $columns */
    private function dropColumnsIfPresent(string $tableName, array $columns): void
    {
        if (! Schema::hasTable($tableName)) {
            return;
        }

        $existingColumns = array_values(array_filter(
            $columns,
            fn (string $column): bool => Schema::hasColumn($tableName, $column),
        ));
        if ($existingColumns !== []) {
            Schema::table($tableName, function (Blueprint $table) use ($existingColumns): void {
                $table->dropColumn($existingColumns);
            });
        }
    }

    /** @return array<string, mixed>|null */
    private function bookingForeign(): ?array
    {
        if (! Schema::hasTable('payments')) {
            return null;
        }

        return collect(Schema::getForeignKeys('payments'))->first(
            fn (array $foreign): bool => ($foreign['columns'] ?? []) === ['booking_id']
                && ($foreign['foreign_table'] ?? null) === 'bookings'
                && ($foreign['foreign_columns'] ?? []) === ['id'],
        );
    }

    /** @param array<string, mixed> $foreign */
    private function dropBookingForeign(array $foreign): void
    {
        Schema::table('payments', function (Blueprint $table) use ($foreign): void {
            $table->dropForeign(
                DB::getDriverName() === 'sqlite' ? ['booking_id'] : (string) $foreign['name'],
            );
        });
    }
};
