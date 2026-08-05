<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->string('payment_method')->nullable()->after('total_amount');
            $table->string('payment_status', 30)->default('unpaid')->change();
            $table->string('booking_status', 40)->default('pending_payment')->change();
            $table->timestamp('expires_at')->nullable()->after('used_at');
            $table->timestamp('paid_at')->nullable()->after('expires_at');
        });

        Schema::table('payments', function (Blueprint $table) {
            $table->string('provider')->default('vnpay')->after('booking_id');
            $table->string('payment_method', 30)->nullable()->change();
            $table->string('order_code')->nullable()->unique()->after('payment_method');
            $table->unsignedBigInteger('amount')->change();
            $table->string('status', 30)->default('pending')->change();
            $table->string('transaction_status')->nullable()->after('transaction_code');
            $table->text('payment_url')->nullable()->after('transaction_status');
            $table->string('response_code')->nullable()->after('payment_url');
            $table->string('card_type')->nullable()->after('response_code');
            $table->string('bank_code')->nullable()->after('card_type');
            $table->string('transaction_id')->nullable()->after('bank_code');
            $table->json('raw_request')->nullable()->after('paid_at');
            $table->json('raw_response')->nullable()->after('raw_request');
        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropUnique(['order_code']);
            $table->dropColumn([
                'provider',
                'order_code',
                'transaction_status',
                'payment_url',
                'response_code',
                'card_type',
                'bank_code',
                'transaction_id',
                'raw_request',
                'raw_response',
            ]);
            $table->enum('payment_method', ['fake', 'counter', 'vnpay'])->default('fake')->change();
            $table->decimal('amount', 10, 2)->change();
            $table->enum('status', ['pending', 'success', 'failed'])->default('pending')->change();
        });

        Schema::table('bookings', function (Blueprint $table) {
            $table->dropColumn(['payment_method', 'expires_at', 'paid_at']);
            $table->enum('payment_status', ['pending', 'paid', 'failed', 'refunded'])->default('pending')->change();
            $table->enum('booking_status', ['pending', 'paid', 'cancelled', 'used', 'expired'])->default('pending')->change();
        });
    }
};
