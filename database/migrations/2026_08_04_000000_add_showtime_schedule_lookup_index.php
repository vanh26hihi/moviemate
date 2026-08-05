<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('showtimes', function (Blueprint $table) {
            $table->index(
                ['room_id', 'show_date', 'show_time', 'status'],
                'showtimes_room_schedule_lookup_index'
            );
        });
    }

    public function down(): void
    {
        Schema::table('showtimes', function (Blueprint $table) {
            $table->dropIndex('showtimes_room_schedule_lookup_index');
        });
    }
};
