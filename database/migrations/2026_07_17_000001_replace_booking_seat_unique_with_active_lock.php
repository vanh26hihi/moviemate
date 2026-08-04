<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('booking_seats', function (Blueprint $table) {
            $table->dropUnique(['showtime_id', 'seat_id']);
            $table->string('active_lock_key', 16)->nullable()->default('ACTIVE')->after('seat_id');
            $table->index(['showtime_id', 'seat_id'], 'booking_seats_showtime_seat_index');
            $table->unique(
                ['showtime_id', 'seat_id', 'active_lock_key'],
                'booking_seats_active_inventory_unique'
            );
        });
    }

    public function down(): void
    {
        Schema::table('booking_seats', function (Blueprint $table) {
            $table->dropUnique('booking_seats_active_inventory_unique');
            $table->dropIndex('booking_seats_showtime_seat_index');
            $table->dropColumn('active_lock_key');
            $table->unique(['showtime_id', 'seat_id']);
        });
    }
};
