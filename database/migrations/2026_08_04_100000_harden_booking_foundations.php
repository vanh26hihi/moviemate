<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->dropForeign(['user_id']);
        });

        Schema::table('bookings', function (Blueprint $table) {
            $table->foreignId('user_id')->nullable()->change();
        });

        Schema::table('bookings', function (Blueprint $table) {
            $table->foreign('user_id')->references('id')->on('users')->nullOnDelete();
            $table->char('guest_access_token_hash', 64)->nullable()->after('customer_email');
            $table->char('checkout_idempotency_key_hash', 64)->nullable()->after('guest_access_token_hash');
            $table->unique('guest_access_token_hash', 'bookings_guest_access_token_hash_unique');
            $table->unique('checkout_idempotency_key_hash', 'bookings_checkout_idempotency_key_hash_unique');
            $table->index(['booking_status', 'expires_at'], 'bookings_expiration_lookup_index');
        });
    }

    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->dropUnique('bookings_guest_access_token_hash_unique');
            $table->dropUnique('bookings_checkout_idempotency_key_hash_unique');
            $table->dropIndex('bookings_expiration_lookup_index');
            $table->dropColumn([
                'guest_access_token_hash',
                'checkout_idempotency_key_hash',
            ]);
            $table->dropForeign(['user_id']);
        });

        Schema::table('bookings', function (Blueprint $table) {
            $table->foreign('user_id')->references('id')->on('users')->nullOnDelete();
        });
    }
};
