<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('discount_codes', function (Blueprint $table): void {
            $table->id();
            $table->string('code', 50)->unique();
            $table->string('name');
            $table->text('description')->nullable();
            $table->enum('discount_type', ['fixed', 'percent']);
            $table->unsignedBigInteger('discount_value');
            $table->unsignedBigInteger('maximum_discount_amount')->nullable();
            $table->unsignedBigInteger('minimum_order_amount')->default(0);
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->unsignedInteger('total_quota')->nullable();
            $table->unsignedInteger('per_user_quota')->nullable();
            $table->boolean('registered_users_only')->default(false);
            $table->boolean('first_order_only')->default(false);
            $table->boolean('can_combine')->default(false);
            $table->integer('priority')->default(0);
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('archived_at')->nullable()->index();
            $table->timestamps();
        });

        Schema::create('discount_code_cinema', function (Blueprint $table): void {
            $table->foreignId('discount_code_id')->constrained()->cascadeOnDelete();
            $table->foreignId('cinema_id')->constrained()->cascadeOnDelete();
            $table->primary(['discount_code_id', 'cinema_id']);
        });

        Schema::table('bookings', function (Blueprint $table): void {
            $table->unsignedBigInteger('gross_amount')->default(0)->after('food_subtotal');
            $table->unsignedBigInteger('promotion_discount_amount')->default(0)->after('gross_amount');
            $table->unsignedBigInteger('points_discount_amount')->default(0)->after('promotion_discount_amount');
        });
        DB::table('bookings')->update([
            'gross_amount' => DB::raw('seat_subtotal + food_subtotal'),
        ]);

        Schema::create('booking_discount_codes', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('booking_id')->constrained()->cascadeOnDelete();
            $table->foreignId('discount_code_id')->constrained()->restrictOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('code_snapshot', 50);
            $table->string('name_snapshot');
            $table->string('discount_type_snapshot', 20);
            $table->unsignedBigInteger('discount_value_snapshot');
            $table->unsignedBigInteger('discount_amount');
            $table->unsignedBigInteger('subtotal_before');
            $table->unsignedBigInteger('subtotal_after');
            $table->enum('status', ['reserved', 'redeemed', 'released'])->default('reserved')->index();
            $table->timestamp('reserved_at');
            $table->timestamp('redeemed_at')->nullable();
            $table->timestamp('released_at')->nullable();
            $table->timestamps();
            $table->unique(['booking_id', 'discount_code_id']);
            $table->index(['discount_code_id', 'status']);
            $table->index(['discount_code_id', 'user_id', 'status'], 'discount_user_quota_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('booking_discount_codes');
        Schema::table('bookings', fn (Blueprint $table) => $table->dropColumn(['gross_amount', 'promotion_discount_amount', 'points_discount_amount']));
        Schema::dropIfExists('discount_code_cinema');
        Schema::dropIfExists('discount_codes');
    }
};
