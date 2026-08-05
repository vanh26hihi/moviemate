<?php

namespace App\Console\Commands;

use App\Models\Payment;
use App\Services\Payments\PaymentReconciliationService;
use Illuminate\Console\Command;
use Throwable;

class QueryPendingPayments extends Command
{
    protected $signature = 'payments:query-pending {--batch=100 : Maximum attempts to query}';

    protected $description = 'Reconcile pending provider payment attempts';

    public function handle(PaymentReconciliationService $reconciliation): int
    {
        $batch = max(1, min(1000, (int) $this->option('batch')));
        $interval = min(
            max(1, (int) config('payment.vnpay.query_interval_seconds', 60)),
            max(1, (int) config('payment.zalopay.query_interval_seconds', 60)),
        );
        $cutoff = now()->subSeconds($interval);
        $counts = ['checked' => 0, 'success' => 0, 'pending' => 0, 'failed' => 0, 'errors' => 0];

        Payment::query()
            ->whereIn('provider', Payment::SUPPORTED_PROVIDERS)
            ->whereIn('status', Payment::RECONCILABLE_STATUSES)
            ->where(function ($query): void {
                $query->whereNull('reconcile_until')->orWhere('reconcile_until', '<=', now());
            })
            ->update([
                'status' => Payment::STATUS_UNRESOLVED,
                'failed_at' => null,
                'failure_reason' => 'reconciliation_window_elapsed',
                'updated_at' => now(),
            ]);

        Payment::query()
            ->whereIn('provider', Payment::SUPPORTED_PROVIDERS)
            ->whereIn('status', Payment::RECONCILABLE_STATUSES)
            ->where('reconcile_until', '>', now())
            ->where(function ($query) use ($cutoff) {
                $query->whereNull('last_queried_at')->orWhere('last_queried_at', '<=', $cutoff);
            })
            ->orderBy('id')
            ->limit($batch)
            ->get()
            ->each(function (Payment $payment) use ($reconciliation, &$counts): void {
                $counts['checked']++;

                try {
                    $status = $reconciliation->reconcile($payment);
                    if ($status === Payment::STATUS_SUCCESS) {
                        $counts['success']++;
                    } elseif (in_array($status, Payment::RECONCILABLE_STATUSES, true)) {
                        $counts['pending']++;
                    } else {
                        $counts['failed']++;
                    }
                } catch (Throwable $exception) {
                    $counts['errors']++;
                    report($exception);
                    $this->warn("Payment {$payment->id} could not be reconciled.");
                }
            });

        $this->info(sprintf(
            'Checked: %d; success: %d; pending: %d; failed/review/expired: %d; errors: %d',
            $counts['checked'],
            $counts['success'],
            $counts['pending'],
            $counts['failed'],
            $counts['errors'],
        ));

        return self::SUCCESS;
    }
}
