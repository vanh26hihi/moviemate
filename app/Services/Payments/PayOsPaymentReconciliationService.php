<?php

namespace App\Services\Payments;

use App\Models\Booking;
use App\Models\Payment;
use App\Services\PayOs\PayOsGateway;
use Illuminate\Support\Facades\DB;

final class PayOsPaymentReconciliationService
{
    public function __construct(
        private readonly PayOsGateway $gateway,
        private readonly PayOsPaymentStateService $states,
    ) {}

    public function reconcile(Payment $payment): string
    {
        return $this->reconcileEligible($payment, false);
    }

    public function reconcileReview(Payment $payment): string
    {
        return $this->reconcileEligible($payment, true);
    }

    private function reconcileEligible(Payment $payment, bool $allowReview): string
    {
        $started = DB::transaction(function () use ($payment, $allowReview): bool {
            Booking::query()->lockForUpdate()->findOrFail($payment->booking_id);
            $locked = Payment::query()->lockForUpdate()->findOrFail($payment->id);
            $eligible = in_array($locked->status, Payment::RECONCILABLE_STATUSES, true)
                || ($allowReview && $locked->status === Payment::STATUS_REVIEW);
            if (! $eligible) {
                return false;
            }
            if (! $allowReview && (! $locked->reconcile_until || $locked->reconcile_until->isPast())) {
                $locked->forceFill([
                    'status' => Payment::STATUS_UNRESOLVED,
                    'failure_reason' => 'reconciliation_window_elapsed',
                ])->save();

                return false;
            }
            $locked->forceFill(['last_queried_at' => now()])->save();

            return true;
        });
        if (! $started) {
            return $payment->fresh()->status;
        }

        $response = $this->gateway->query($payment->fresh());

        return $this->states->apply($payment, $response->data, 'query', $response->hash, $allowReview);
    }
}
