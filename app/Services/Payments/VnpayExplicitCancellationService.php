<?php

namespace App\Services\Payments;

use App\Domain\Bookings\BookingCancellationResult;
use App\Domain\Payments\VnpayConfig;
use App\Models\Payment;
use App\Services\BookingCancellationService;

final class VnpayExplicitCancellationService
{
    public function __construct(
        private readonly VnpayConfig $config,
        private readonly BookingCancellationService $cancellations,
    ) {}

    /**
     * Finalize only after the caller has cryptographically verified the VNPAY envelope.
     *
     * @param  array<string, string>  $parameters
     */
    public function finalizeVerified(
        Payment $payment,
        array $parameters,
        string $source,
        ?string $payloadHash = null,
    ): BookingCancellationResult {
        if (! $this->matchesCancellationEvidence($payment, $parameters)
            || ! in_array($source, ['return', 'ipn'], true)) {
            return BookingCancellationResult::notCancellable();
        }

        $paymentOutcome = [
            'response_code' => '24',
            'transaction_status' => $this->shortText($parameters['vnp_TransactionStatus'] ?? null, 20),
            'bank_code' => $this->shortText($parameters['vnp_BankCode'] ?? null, 20),
            'card_type' => $this->shortText($parameters['vnp_CardType'] ?? null, 20),
        ];
        if ($source === 'ipn') {
            $paymentOutcome += [
                'callback_received_at' => now(),
                'callback_payload_hash' => $payloadHash,
            ];
        }

        return $this->cancellations->cancelVerifiedPayment(
            $payment->id,
            'vnpay',
            $parameters['vnp_TxnRef'],
            $payment->amount,
            'vnpay_customer_cancelled',
            $paymentOutcome,
            ['source' => $source],
        );
    }

    /** @param array<string, string> $parameters */
    private function matchesCancellationEvidence(Payment $payment, array $parameters): bool
    {
        $providerAmount = $parameters['vnp_Amount'] ?? null;

        return $payment->provider === 'vnpay'
            && ($parameters['vnp_ResponseCode'] ?? null) === '24'
            && ($parameters['vnp_TmnCode'] ?? null) === $this->config->tmnCode
            && isset($parameters['vnp_TxnRef'])
            && hash_equals($payment->order_code, $parameters['vnp_TxnRef'])
            && is_string($providerAmount)
            && preg_match('/^[0-9]{3,15}$/D', $providerAmount) === 1
            && $payment->amount > 0
            && $payment->amount <= intdiv(PHP_INT_MAX, 100)
            && hash_equals((string) ($payment->amount * 100), $providerAmount);
    }

    private function shortText(mixed $value, int $length): ?string
    {
        return is_string($value) ? mb_substr($value, 0, $length) : null;
    }
}
