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
                $table->foreignId('booking_id')->nullable()->after('id')
                    ->constrained('bookings')->nullOnDelete();
                $table->unique('booking_id', 'orders_booking_id_unique');
            }
            if (! Schema::hasColumn('orders', 'subtotal')) {
                $table->unsignedBigInteger('subtotal')->default(0)->after('pickup_cinema_id');
            }
        });

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
        $this->dropColumnsIfPresent('order_items', [
            'snapshot_name',
            'unit_price',
            'line_total',
        ]);

        if (Schema::hasTable('orders')) {
            if ($this->hasForeignKey('orders', self::ORDERS_BOOKING_FOREIGN)) {
                Schema::table('orders', function (Blueprint $table): void {
                    if (DB::getDriverName() === 'sqlite') {
                        $table->dropForeign(['booking_id']);
                    } else {
                        $table->dropForeign(self::ORDERS_BOOKING_FOREIGN);
                    }
                });
            }

            if ($this->hasIndex('orders', self::ORDERS_BOOKING_UNIQUE)) {
                Schema::table('orders', function (Blueprint $table): void {
                    $table->dropUnique(self::ORDERS_BOOKING_UNIQUE);
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

    private function hasForeignKey(string $tableName, string $foreignKeyName): bool
    {
        if (! Schema::hasTable($tableName)) {
            return false;
        }

        if (DB::getDriverName() === 'mysql') {
            return DB::table('information_schema.TABLE_CONSTRAINTS')
                ->where('CONSTRAINT_SCHEMA', DB::getDatabaseName())
                ->where('TABLE_NAME', $tableName)
                ->where('CONSTRAINT_NAME', $foreignKeyName)
                ->where('CONSTRAINT_TYPE', 'FOREIGN KEY')
                ->exists();
        }

        return collect(Schema::getForeignKeys($tableName))->contains(
            fn (array $foreign): bool => ($foreign['name'] ?? null) === $foreignKeyName
                || (DB::getDriverName() === 'sqlite'
                    && ($foreign['columns'] ?? []) === ['booking_id']
                    && ($foreign['foreign_table'] ?? null) === 'bookings'
                    && ($foreign['foreign_columns'] ?? []) === ['id'])
        );
    }

    private function hasIndex(string $tableName, string $indexName): bool
    {
        if (! Schema::hasTable($tableName)) {
            return false;
        }

        if (DB::getDriverName() === 'mysql') {
            return DB::table('information_schema.STATISTICS')
                ->where('TABLE_SCHEMA', DB::getDatabaseName())
                ->where('TABLE_NAME', $tableName)
                ->where('INDEX_NAME', $indexName)
                ->exists();
        }

        return collect(Schema::getIndexes($tableName))->contains(
            fn (array $index): bool => ($index['name'] ?? null) === $indexName
        );
    }
};
