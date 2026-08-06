<?php

namespace App\Services\Payments;

use App\Exceptions\PaymentInitiationException;
use App\Models\Payment;
use App\Services\PayOs\PayOsGateway;

final class PayOsCancellationService
{
    public function __construct(
        private readonly PayOsGateway $gateway,
        private readonly PayOsPaymentStateService $states,
    ) {}

    public function cancel(Payment $payment): string
    {
        if ($payment->provider !== 'payos'
            || ! in_array($payment->status, Payment::RECONCILABLE_STATUSES, true)) {
            throw new PaymentInitiationException('The payOS attempt is not eligible for provider cancellation.');
        }

        $response = $this->gateway->cancel($payment);

        return $this->states->apply($payment, $response->data, 'query', $response->hash);
    }
}
