<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reviews', function (Blueprint $table): void {
            $table->foreignId('booking_id')->nullable()->after('movie_id')->constrained()->nullOnDelete();
            $table->string('moderation_status', 20)->default('published')->index();
            $table->json('moderation_flags')->nullable();
            $table->text('moderation_reason')->nullable();
            $table->boolean('is_verified')->default(false)->index();
            $table->timestamp('first_published_at')->nullable();
            $table->timestamp('reward_awarded_at')->nullable();
            $table->foreignId('moderated_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('moderated_at')->nullable();
        });
        DB::table('reviews')->where('status', 'hidden')->update(['moderation_status' => 'hidden']);
        DB::table('reviews')->orderByDesc('id')->get()->groupBy(fn ($row) => $row->user_id.':'.$row->movie_id)
            ->each(fn ($rows) => $rows->skip(1)->each(fn ($row) => DB::table('reviews')->where('id', $row->id)->delete()));
        Schema::table('reviews', fn (Blueprint $table) => $table->unique(['user_id', 'movie_id']));

        Schema::create('loyalty_accounts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->bigInteger('points_balance')->default(0);
            $table->unsignedBigInteger('lifetime_earned')->default(0);
            $table->timestamps();
        });
        Schema::create('loyalty_transactions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('loyalty_account_id')->constrained()->cascadeOnDelete();
            $table->string('source_key', 191)->unique();
            $table->string('type', 30)->index();
            $table->bigInteger('points_delta');
            $table->bigInteger('balance_after');
            $table->json('metadata')->nullable();
            $table->timestamps();
        });
        Schema::create('booking_point_redemptions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('booking_id')->unique()->constrained()->cascadeOnDelete();
            $table->foreignId('loyalty_account_id')->constrained()->restrictOnDelete();
            $table->unsignedBigInteger('points');
            $table->unsignedBigInteger('discount_amount');
            $table->string('status', 20)->default('reserved')->index();
            $table->timestamp('reserved_at');
            $table->timestamp('redeemed_at')->nullable();
            $table->timestamp('released_at')->nullable();
            $table->timestamps();
        });
        Schema::create('loyalty_settings', function (Blueprint $table): void {
            $table->id();
            $table->unsignedInteger('review_reward_points')->default(100);
            $table->unsignedInteger('point_value_vnd')->default(100);
            $table->unsignedTinyInteger('max_points_discount_percent')->default(50);
            $table->unsignedInteger('minimum_points_redemption')->default(1);
            $table->foreignId('updated_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
        DB::table('loyalty_settings')->insert(['review_reward_points' => 100, 'point_value_vnd' => 100, 'max_points_discount_percent' => 50, 'minimum_points_redemption' => 1, 'created_at' => now(), 'updated_at' => now()]);
    }

    public function down(): void
    {
        Schema::dropIfExists('loyalty_settings');
        Schema::dropIfExists('booking_point_redemptions');
        Schema::dropIfExists('loyalty_transactions');
        Schema::dropIfExists('loyalty_accounts');
        Schema::table('reviews', function (Blueprint $table): void {
            $table->dropUnique(['user_id', 'movie_id']);
            $table->dropConstrainedForeignId('booking_id');
            $table->dropConstrainedForeignId('moderated_by_user_id');
            $table->dropColumn(['moderation_status', 'moderation_flags', 'moderation_reason', 'is_verified', 'first_published_at', 'reward_awarded_at', 'moderated_at']);
        });
    }
};
