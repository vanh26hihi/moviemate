<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropForeign(['booking_id']);
        });

        Schema::table('payments', function (Blueprint $table) {
            $table->foreign('booking_id')->references('id')->on('bookings')->restrictOnDelete();
            $table->unsignedBigInteger('app_id')->nullable()->after('provider');
            $table->string('app_trans_id', 40)->nullable()->after('app_id');
            $table->string('app_user')->nullable()->after('app_trans_id');
            $table->unsignedBigInteger('app_time_ms')->nullable()->after('app_user');
            $table->char('currency', 3)->default('VND')->after('amount');
            $table->string('description')->nullable()->after('currency');
            $table->timestamp('expires_at')->nullable()->after('description');
            $table->string('zp_trans_id', 64)->nullable()->after('transaction_id');
            $table->string('zp_trans_token')->nullable()->after('zp_trans_id');
            $table->string('order_token')->nullable()->after('zp_trans_token');
            $table->text('order_url')->nullable()->after('order_token');
            $table->text('qr_code')->nullable()->after('order_url');
            $table->integer('provider_return_code')->nullable()->after('qr_code');
            $table->integer('provider_sub_return_code')->nullable()->after('provider_return_code');
            $table->string('provider_return_message')->nullable()->after('provider_sub_return_code');
            $table->string('provider_sub_return_message')->nullable()->after('provider_return_message');
            $table->unsignedBigInteger('server_time_ms')->nullable()->after('provider_sub_return_message');
            $table->timestamp('callback_received_at')->nullable()->after('server_time_ms');
            $table->timestamp('last_queried_at')->nullable()->after('callback_received_at');
            $table->timestamp('verified_at')->nullable()->after('last_queried_at');
            $table->timestamp('failed_at')->nullable()->after('verified_at');
            $table->string('failure_reason')->nullable()->after('failed_at');
            $table->char('create_response_hash', 64)->nullable()->after('failure_reason');
            $table->char('callback_payload_hash', 64)->nullable()->after('create_response_hash');
            $table->char('query_response_hash', 64)->nullable()->after('callback_payload_hash');

            $table->unique(['provider', 'app_id', 'app_trans_id'], 'payments_provider_app_trans_unique');
            $table->unique(['provider', 'zp_trans_id'], 'payments_provider_zp_trans_unique');
            $table->index(['booking_id', 'status'], 'payments_booking_status_index');
            $table->index(['provider', 'status', 'expires_at'], 'payments_provider_status_expiry_index');
        });

        Schema::table('bookings', function (Blueprint $table) {
            $table->timestamp('ticket_emailed_at')->nullable()->after('paid_at');
        });
    }

    public function down(): void
    {
        if (DB::table('payments')->where('provider', 'zalopay')->exists()) {
            throw new RuntimeException('Cannot roll back ZaloPay payment fields while ZaloPay attempts exist.');
        }

        Schema::table('bookings', function (Blueprint $table) {
            $table->dropColumn('ticket_emailed_at');
        });

        Schema::table('payments', function (Blueprint $table) {
            $table->dropUnique('payments_provider_app_trans_unique');
            $table->dropUnique('payments_provider_zp_trans_unique');
            $table->dropIndex('payments_booking_status_index');
            $table->dropIndex('payments_provider_status_expiry_index');
            $table->dropForeign(['booking_id']);
            $table->dropColumn([
                'app_id',
                'app_trans_id',
                'app_user',
                'app_time_ms',
                'currency',
                'description',
                'expires_at',
                'zp_trans_id',
                'zp_trans_token',
                'order_token',
                'order_url',
                'qr_code',
                'provider_return_code',
                'provider_sub_return_code',
                'provider_return_message',
                'provider_sub_return_message',
                'server_time_ms',
                'callback_received_at',
                'last_queried_at',
                'verified_at',
                'failed_at',
                'failure_reason',
                'create_response_hash',
                'callback_payload_hash',
                'query_response_hash',
            ]);
        });

        Schema::table('payments', function (Blueprint $table) {
            $table->foreign('booking_id')->references('id')->on('bookings')->cascadeOnDelete();
        });
    }
};
