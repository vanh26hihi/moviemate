<?php

namespace App\Services\Payments;

use App\Exceptions\PaymentInitiationException;
use App\Models\Payment;

class PaymentReconciliationService
{
    public function reconcile(Payment $payment): string
    {
        return match ($payment->provider) {
            'vnpay' => app(VnpayQueryService::class)->reconcile($payment),
            'zalopay' => app(ZaloPayPaymentReconciliationService::class)->reconcile($payment),
            'payos' => app(PayOsPaymentReconciliationService::class)->reconcile($payment),
            default => throw new PaymentInitiationException('The payment provider cannot be reconciled.'),
        };
    }
}
