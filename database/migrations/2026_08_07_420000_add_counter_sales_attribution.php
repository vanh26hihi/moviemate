<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bookings', function (Blueprint $table): void {
            $table->string('sales_channel', 20)->default('online')->after('user_id');
            $table->foreignId('created_by_staff_id')->nullable()->after('sales_channel')
                ->constrained('users')->nullOnDelete();
            $table->string('customer_name', 120)->nullable()->after('created_by_staff_id');
            $table->string('customer_phone', 30)->nullable()->after('customer_name');
            $table->index(['sales_channel', 'created_at'], 'bookings_sales_channel_created_index');
            $table->index(['created_by_staff_id', 'created_at'], 'bookings_creator_created_index');
        });

        Schema::table('payments', function (Blueprint $table): void {
            $table->foreignId('settled_by_user_id')->nullable()->after('verified_at')
                ->constrained('users')->nullOnDelete();
            $table->timestamp('settled_at')->nullable()->after('settled_by_user_id');
            $table->index(['settled_by_user_id', 'settled_at'], 'payments_settler_settled_index');
        });

        Schema::table('food_items', function (Blueprint $table): void {
            $table->foreignId('cinema_id')->nullable()->after('id')->constrained('cinemas')->restrictOnDelete();
            $table->index(['cinema_id', 'active'], 'food_items_cinema_active_index');
        });

        DB::table('bookings')->whereNull('sales_channel')->update(['sales_channel' => 'online']);
    }

    public function down(): void
    {
        if (DB::table('bookings')->where('sales_channel', 'counter')->exists()
            || DB::table('payments')->whereNotNull('settled_at')->exists()
            || DB::table('food_items')->whereNotNull('cinema_id')->exists()) {
            throw new RuntimeException('Cannot remove counter-sale attribution while counter history exists.');
        }

        Schema::table('food_items', function (Blueprint $table): void {
            $table->dropIndex('food_items_cinema_active_index');
            $table->dropConstrainedForeignId('cinema_id');
        });
        Schema::table('payments', function (Blueprint $table): void {
            $table->dropIndex('payments_settler_settled_index');
            $table->dropConstrainedForeignId('settled_by_user_id');
            $table->dropColumn('settled_at');
        });
        Schema::table('bookings', function (Blueprint $table): void {
            $table->dropIndex('bookings_sales_channel_created_index');
            $table->dropIndex('bookings_creator_created_index');
            $table->dropConstrainedForeignId('created_by_staff_id');
            $table->dropColumn(['sales_channel', 'customer_name', 'customer_phone']);
        });
    }
};
