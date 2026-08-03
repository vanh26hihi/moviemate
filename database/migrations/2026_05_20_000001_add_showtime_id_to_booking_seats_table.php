<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('booking_seats', function (Blueprint $table) {
            $table->foreignId('showtime_id')->nullable()->after('booking_id')
                ->constrained('showtimes')->cascadeOnDelete();
            $table->unique(['showtime_id', 'seat_id']);
        });
    }

    public function down(): void
    {
        Schema::table('booking_seats', function (Blueprint $table) {
            $table->dropUnique(['showtime_id', 'seat_id']);
            $table->dropForeign(['showtime_id']);
            $table->dropColumn('showtime_id');
        });
    }
};
