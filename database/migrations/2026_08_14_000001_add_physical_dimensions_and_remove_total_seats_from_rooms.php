<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('rooms', function (Blueprint $table): void {
            $table->unsignedInteger('width_mm')->nullable()->after('room_type_id');
            $table->unsignedInteger('length_mm')->nullable()->after('width_mm');
            $table->dropColumn('total_seats');
        });
    }

    public function down(): void
    {
        Schema::table('rooms', function (Blueprint $table): void {
            $table->integer('total_seats')->default(0)->after('room_type_id');
            $table->dropColumn(['width_mm', 'length_mm']);
        });
    }
};
