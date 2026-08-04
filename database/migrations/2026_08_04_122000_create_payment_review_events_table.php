<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_review_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payment_id')->constrained()->restrictOnDelete();
            $table->foreignId('actor_user_id')->constrained('users')->restrictOnDelete();
            $table->string('action', 64);
            $table->string('previous_status', 30);
            $table->string('resulting_status', 30);
            $table->string('provider_result_category', 64);
            $table->string('provider_result_code', 64)->nullable();
            $table->timestamp('created_at');
            $table->index(['payment_id', 'created_at'], 'payment_review_events_payment_time_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_review_events');
    }
};
