<?php

namespace App\Console\Commands;

use App\Models\Payment;
use App\Services\Payments\VnpayStoredQueryEvidenceService;
use Illuminate\Console\Command;
use LogicException;

final class ReconcileStoredVnpayEvidence extends Command
{
    protected $signature = 'payments:reconcile-vnpay-stored {payment : Existing VNPAY payment ID}';

    protected $description = 'Apply authenticated stored VNPAY terminal-expiration evidence without contacting VNPAY';

    public function handle(VnpayStoredQueryEvidenceService $reconciliation): int
    {
        $paymentId = filter_var($this->argument('payment'), FILTER_VALIDATE_INT, [
            'options' => ['min_range' => 1],
        ]);
        $payment = $paymentId === false ? null : Payment::query()->find($paymentId);

        if (! $payment) {
            $this->error('The requested payment does not exist.');

            return self::FAILURE;
        }

        try {
            $status = $reconciliation->reconcileTerminalExpiration($payment);
        } catch (LogicException) {
            $this->error('Stored VNPAY evidence did not pass the reconciliation guards.');

            return self::FAILURE;
        }

        $booking = $payment->booking()->firstOrFail();
        $this->info(sprintf(
            'Payment %d: %s; booking %d: %s.',
            $payment->id,
            $status,
            $booking->id,
            $booking->booking_status,
        ));

        return self::SUCCESS;
    }
}
