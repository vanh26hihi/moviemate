<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('showtime_cancellations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('showtime_id')->unique()->constrained()->restrictOnDelete();
            $table->foreignId('cinema_id')->constrained()->restrictOnDelete();
            $table->string('reason_code', 64);
            $table->string('reason_note', 500)->nullable();
            $table->string('status', 32)->default('open');
            $table->foreignId('cancelled_by_user_id')->constrained('users')->restrictOnDelete();
            $table->timestamp('cancelled_at');
            $table->foreignId('resolved_by_user_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();
            $table->index(['cinema_id', 'status', 'cancelled_at'], 'showtime_cancellations_queue_idx');
        });

        Schema::create('showtime_cancellation_impacts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('showtime_cancellation_id')->constrained()->restrictOnDelete();
            $table->foreignId('booking_id')->constrained()->restrictOnDelete();
            $table->string('outcome', 64);
            $table->string('booking_status_before', 32);
            $table->string('payment_status_before', 32);
            $table->unsignedBigInteger('authoritative_amount')->default(0);
            $table->string('currency', 3)->default('VND');
            $table->unsignedInteger('seat_count')->default(0);
            $table->json('audit_snapshot');
            $table->timestamps();
            $table->unique(['showtime_cancellation_id', 'booking_id'], 'showtime_cancellation_booking_unique');
            $table->index(['booking_id', 'outcome'], 'showtime_cancellation_impacts_booking_idx');
        });

        Schema::create('refund_cases', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('showtime_cancellation_id')->constrained()->restrictOnDelete();
            $table->foreignId('showtime_cancellation_impact_id')->unique()->constrained('showtime_cancellation_impacts')->restrictOnDelete();
            $table->foreignId('cinema_id')->constrained()->restrictOnDelete();
            $table->foreignId('booking_id')->constrained()->restrictOnDelete();
            $table->foreignId('payment_id')->constrained()->restrictOnDelete();
            $table->string('status', 32)->default('required');
            $table->unsignedBigInteger('required_amount');
            $table->string('currency', 3)->default('VND');
            $table->string('resolution_method', 64)->nullable();
            $table->string('resolution_reference', 200)->nullable();
            $table->string('resolution_note', 500)->nullable();
            $table->foreignId('resolved_by_user_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();
            $table->unique(['showtime_cancellation_id', 'booking_id'], 'refund_cases_cancellation_booking_unique');
            $table->index(['cinema_id', 'status', 'created_at'], 'refund_cases_queue_idx');
            $table->index(['payment_id', 'status'], 'refund_cases_payment_idx');
        });

        if (Schema::hasTable('permissions') && Schema::hasTable('permission_role')) {
            $now = now();
            foreach (['refunds.view' => 'Xem nghĩa vụ hoàn tiền', 'refunds.resolve' => 'Ghi nhận hoàn tiền thủ công'] as $slug => $name) {
                DB::table('permissions')->updateOrInsert(
                    ['slug' => $slug],
                    ['name' => $name, 'group' => 'refunds', 'updated_at' => $now, 'created_at' => $now],
                );
                $permissionId = DB::table('permissions')->where('slug', $slug)->value('id');
                $roleIds = DB::table('roles')->whereIn('slug', ['admin', 'manager'])->pluck('id');
                foreach ($roleIds as $roleId) {
                    DB::table('permission_role')->insertOrIgnore([
                        'permission_id' => $permissionId,
                        'role_id' => $roleId,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);
                }
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('refund_cases');
        Schema::dropIfExists('showtime_cancellation_impacts');
        Schema::dropIfExists('showtime_cancellations');
    }
};
