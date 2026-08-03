<?php

namespace App\Console\Commands;

use App\Domain\Payments\ZaloPayConfig;
use App\Models\Payment;
use App\Services\Payments\PaymentReconciliationService;
use Illuminate\Console\Command;
use Throwable;

class QueryPendingPayments extends Command
{
    protected $signature = 'payments:query-pending {--batch=100 : Maximum attempts to query}';

    protected $description = 'Reconcile pending ZaloPay payment attempts';

    public function handle(PaymentReconciliationService $reconciliation, ZaloPayConfig $config): int
    {
        $batch = max(1, min(1000, (int) $this->option('batch')));
        $cutoff = now()->subSeconds($config->queryIntervalSeconds);
        $counts = ['checked' => 0, 'success' => 0, 'pending' => 0, 'failed' => 0, 'errors' => 0];

        Payment::query()
            ->where('provider', 'zalopay')
            ->where('status', Payment::STATUS_PENDING)
            ->where('expires_at', '>', now())
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
                    } elseif ($status === Payment::STATUS_PENDING) {
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
