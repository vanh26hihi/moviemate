<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('booking_point_redemptions', function (Blueprint $table): void {
            $table->unsignedInteger('point_value_vnd_snapshot')->default(0)->after('points');
            $table->string('release_reason', 100)->nullable()->after('released_at');
        });
    }

    public function down(): void
    {
        Schema::table('booking_point_redemptions', function (Blueprint $table): void {
            $table->dropColumn(['point_value_vnd_snapshot', 'release_reason']);
        });
    }
};
