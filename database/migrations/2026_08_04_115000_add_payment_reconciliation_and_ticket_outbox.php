<?php

use Carbon\Carbon;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const UNSAFE_RETRY_STATUSES = ['pending', 'processing', 'unresolved', 'review'];

    public function up(): void
    {
        $this->assertNoDuplicateUnsafeAttempts();

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

        Schema::table('payments', function (Blueprint $table) {
            $table->string('active_attempt_key', 16)
                ->nullable()
                ->virtualAs("case when status in ('pending', 'processing', 'unresolved', 'review') then 'ACTIVE' else null end")
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

    private function assertNoDuplicateUnsafeAttempts(): void
    {
        $duplicates = DB::table('payments')
            ->select('booking_id', 'provider')
            ->whereIn('status', self::UNSAFE_RETRY_STATUSES)
            ->groupBy('booking_id', 'provider')
            ->havingRaw('COUNT(*) > 1')
            ->get();

        if ($duplicates->isEmpty()) {
            return;
        }

        $details = $duplicates->map(function (object $group): string {
            $ids = DB::table('payments')
                ->where('booking_id', $group->booking_id)
                ->where('provider', $group->provider)
                ->whereIn('status', self::UNSAFE_RETRY_STATUSES)
                ->orderBy('id')
                ->pluck('id')
                ->implode(',');

            return "booking_id={$group->booking_id}, provider={$group->provider}, payment_ids={$ids}";
        })->implode('; ');

        throw new RuntimeException(
            'Cannot add the active payment-attempt constraint because duplicate unsafe attempts exist. '
            .'Resolve them explicitly before retrying this migration: '.$details,
        );
    }
};
