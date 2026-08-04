<?php

use Carbon\Carbon;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->timestamp('reconcile_until')->nullable()->after('expires_at');
        });

        $graceHours = max(1, (int) config('payment.reconciliation_grace_hours', 24));
        DB::table('payments')
            ->whereNull('reconcile_until')
            ->whereNotNull('expires_at')
            ->orderBy('id')
            ->eachById(function (object $payment) use ($graceHours): void {
                DB::table('payments')->where('id', $payment->id)->update([
                    'reconcile_until' => Carbon::parse($payment->expires_at)->addHours($graceHours),
                ]);
            });

        DB::table('payments')
            ->select('booking_id', 'provider')
            ->whereIn('status', ['pending', 'processing'])
            ->groupBy('booking_id', 'provider')
            ->havingRaw('COUNT(*) > 1')
            ->get()
            ->each(function (object $group): void {
                $keepId = DB::table('payments')
                    ->where('booking_id', $group->booking_id)
                    ->where('provider', $group->provider)
                    ->whereIn('status', ['pending', 'processing'])
                    ->max('id');

                DB::table('payments')
                    ->where('booking_id', $group->booking_id)
                    ->where('provider', $group->provider)
                    ->whereIn('status', ['pending', 'processing'])
                    ->where('id', '<>', $keepId)
                    ->update([
                        'status' => 'review',
                        'failure_reason' => 'duplicate_active_attempt_migration',
                        'failed_at' => now(),
                    ]);
            });

        Schema::table('payments', function (Blueprint $table) {
            $table->string('active_attempt_key', 16)
                ->nullable()
                ->virtualAs("case when status in ('pending', 'processing') then 'ACTIVE' else null end")
                ->after('status');
            $table->unique(
                ['booking_id', 'provider', 'active_attempt_key'],
                'payments_one_active_attempt_unique',
            );
            $table->index(
                ['provider', 'status', 'reconcile_until', 'last_queried_at'],
                'payments_reconciliation_due_index',
            );
        });

        Schema::create('booking_ticket_deliveries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('booking_id')->unique()->constrained()->restrictOnDelete();
            $table->string('status', 16)->default('pending');
            $table->unsignedInteger('attempts')->default(0);
            $table->timestamp('available_at')->nullable();
            $table->timestamp('processing_started_at')->nullable();
            $table->timestamp('lease_expires_at')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->string('last_error_code', 100)->nullable();
            $table->timestamps();
            $table->index(['status', 'available_at', 'lease_expires_at'], 'ticket_deliveries_claim_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('booking_ticket_deliveries');

        Schema::table('payments', function (Blueprint $table) {
            $table->dropIndex('payments_reconciliation_due_index');
            $table->dropUnique('payments_one_active_attempt_unique');
            $table->dropColumn(['active_attempt_key', 'reconcile_until']);
        });
    }
};
