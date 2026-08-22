<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('booking_point_redemptions');
        Schema::dropIfExists('loyalty_transactions');
        Schema::dropIfExists('loyalty_accounts');
        Schema::dropIfExists('loyalty_settings');

        if (Schema::hasColumn('bookings', 'points_discount_amount')) {
            Schema::table('bookings', fn (Blueprint $table) => $table->dropColumn('points_discount_amount'));
        }
        if (Schema::hasColumn('reviews', 'reward_awarded_at')) {
            Schema::table('reviews', fn (Blueprint $table) => $table->dropColumn('reward_awarded_at'));
        }
    }

    public function down(): void
    {
        throw new RuntimeException('The removed loyalty system cannot be restored by rollback.');
    }
};
