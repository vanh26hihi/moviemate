<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const ORDERS_BOOKING_FOREIGN = 'orders_booking_id_foreign';

    private const ORDERS_BOOKING_UNIQUE = 'orders_booking_id_unique';

    public function up(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            if (! Schema::hasColumn('bookings', 'seat_subtotal')) {
                $table->unsignedBigInteger('seat_subtotal')->default(0)->after('total_amount');
            }
            if (! Schema::hasColumn('bookings', 'food_subtotal')) {
                $table->unsignedBigInteger('food_subtotal')->default(0)->after('seat_subtotal');
            }
            if (! Schema::hasColumn('bookings', 'currency')) {
                $table->char('currency', 3)->default('VND')->after('food_subtotal');
            }
        });

        Schema::table('orders', function (Blueprint $table) {
            if (! Schema::hasColumn('orders', 'booking_id')) {
                $table->unsignedBigInteger('booking_id')->nullable()->after('id');
            }
            if (! Schema::hasColumn('orders', 'subtotal')) {
                $table->unsignedBigInteger('subtotal')->default(0)->after('pickup_cinema_id');
            }
        });

        if ($this->foreignKeyName('orders') === null) {
            Schema::table('orders', function (Blueprint $table): void {
                $table->foreign('booking_id', self::ORDERS_BOOKING_FOREIGN)
                    ->references('id')->on('bookings')->nullOnDelete();
            });
        }
        if ($this->uniqueBookingIndexName('orders') === null) {
            Schema::table('orders', function (Blueprint $table): void {
                $table->unique('booking_id', self::ORDERS_BOOKING_UNIQUE);
            });
        }

        Schema::table('order_items', function (Blueprint $table) {
            if (! Schema::hasColumn('order_items', 'snapshot_name')) {
                $table->string('snapshot_name')->nullable()->after('food_item_id');
            }
            if (! Schema::hasColumn('order_items', 'unit_price')) {
                $table->unsignedBigInteger('unit_price')->nullable()->after('quantity');
            }
            if (! Schema::hasColumn('order_items', 'line_total')) {
                $table->unsignedBigInteger('line_total')->nullable()->after('unit_price');
            }
        });
    }

    public function down(): void
    {
        $protectedTables = collect(['bookings', 'orders', 'order_items'])
            ->filter(fn (string $table): bool => Schema::hasTable($table) && DB::table($table)->exists())
            ->values();
        if ($protectedTables->isNotEmpty()) {
            throw new RuntimeException(
                'Cannot roll back checkout pricing snapshots while protected rows exist in ['
                .$protectedTables->implode(', ').']. No rows or schema objects were changed.',
            );
        }

        $this->dropColumnsIfPresent('order_items', [
            'snapshot_name',
            'unit_price',
            'line_total',
        ]);

        if (Schema::hasTable('orders')) {
            $foreignKey = $this->foreignKeyName('orders');
            if ($foreignKey !== null) {
                Schema::table('orders', function (Blueprint $table) use ($foreignKey): void {
                    if (DB::getDriverName() === 'sqlite') {
                        $table->dropForeign(['booking_id']);
                    } else {
                        $table->dropForeign($foreignKey);
                    }
                });
            }

            $index = $this->uniqueBookingIndexName('orders');
            if ($index !== null) {
                Schema::table('orders', function (Blueprint $table) use ($index): void {
                    $table->dropUnique($index);
                });
            }

            $this->dropColumnsIfPresent('orders', ['subtotal']);
            $this->dropColumnsIfPresent('orders', ['booking_id']);
        }

        $this->dropColumnsIfPresent('bookings', [
            'currency',
            'food_subtotal',
            'seat_subtotal',
        ]);
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

        if ($existingColumns === []) {
            return;
        }

        Schema::table($tableName, function (Blueprint $table) use ($existingColumns): void {
            $table->dropColumn($existingColumns);
        });
    }

    private function foreignKeyName(string $tableName): ?string
    {
        if (! Schema::hasTable($tableName)) {
            return null;
        }

        $foreign = collect(Schema::getForeignKeys($tableName))->first(
            fn (array $foreign): bool => ($foreign['columns'] ?? []) === ['booking_id']
                && ($foreign['foreign_table'] ?? null) === 'bookings'
                && ($foreign['foreign_columns'] ?? []) === ['id']
        );

        if ($foreign === null) {
            return null;
        }

        return DB::getDriverName() === 'sqlite'
            ? self::ORDERS_BOOKING_FOREIGN
            : ($foreign['name'] ?? null);
    }

    private function uniqueBookingIndexName(string $tableName): ?string
    {
        if (! Schema::hasTable($tableName)) {
            return null;
        }

        $index = collect(Schema::getIndexes($tableName))->first(
            fn (array $index): bool => ($index['columns'] ?? []) === ['booking_id']
                && (bool) ($index['unique'] ?? false)
        );

        return $index['name'] ?? null;
    }
};
