<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
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
        Schema::table('order_items', function (Blueprint $table) {
            $columns = array_values(array_filter(
                ['snapshot_name', 'unit_price', 'line_total'],
                fn (string $column) => Schema::hasColumn('order_items', $column),
            ));
            if ($columns !== []) {
                $table->dropColumn($columns);
            }
        });

        Schema::table('orders', function (Blueprint $table) {
            if (Schema::hasColumn('orders', 'booking_id')) {
                $table->dropUnique('orders_booking_id_unique');
                $table->dropForeign(['booking_id']);
                $table->dropColumn('booking_id');
            }
            if (Schema::hasColumn('orders', 'subtotal')) {
                $table->dropColumn('subtotal');
            }
        });

        Schema::table('bookings', function (Blueprint $table) {
            $columns = array_values(array_filter(
                ['seat_subtotal', 'food_subtotal', 'currency'],
                fn (string $column) => Schema::hasColumn('bookings', $column),
            ));
            if ($columns !== []) {
                $table->dropColumn($columns);
            }
        });
    }
};
