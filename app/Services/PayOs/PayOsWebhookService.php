<?php

namespace App\Services\PayOs;

use App\Domain\Payments\PayOsConfig;
use App\Domain\Payments\PayOsSigner;
use App\Models\Payment;
use App\Services\Payments\PayOsPaymentStateService;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;

final class PayOsWebhookService
{
    public function __construct(
        private readonly PayOsConfig $config,
        private readonly PayOsSigner $signer,
        private readonly PayOsPaymentStateService $states,
    ) {}

    /** @param array<string, mixed> $payload */
    public function handle(array $payload, string $payloadHash): string
    {
        if (count($payload) > 8
            || ! is_string($payload['code'] ?? null)
            || ! is_string($payload['desc'] ?? null)
            || ! is_bool($payload['success'] ?? null)
            || ! is_array($payload['data'] ?? null)
            || count($payload['data']) > 40
            || ! is_string($payload['signature'] ?? null)) {
            throw new InvalidArgumentException('Invalid payOS webhook envelope.');
        }
        $data = $payload['data'];
        if (! $this->signer->verifyData($data, $payload['signature'], $this->config->checksumKey)) {
            throw new InvalidArgumentException('Invalid payOS webhook signature.');
        }

        $orderCode = $data['orderCode'] ?? null;
        if (! is_int($orderCode) || $orderCode <= 0) {
            throw new InvalidArgumentException('Invalid payOS webhook identity.');
        }

        $payment = Payment::query()
            ->where('provider', 'payos')
            ->where('order_code', (string) $orderCode)
            ->first();
        if (! $payment) {
            Log::warning('payOS webhook referenced an unknown payment attempt.', [
                'provider' => 'payos',
                'order_code_hash' => hash('sha256', (string) $orderCode),
            ]);

            return 'unknown';
        }

        if (! isset($data['status'])
            && $payload['success'] === true
            && $payload['code'] === '00'
            && ($data['code'] ?? null) === '00') {
            $data['status'] = 'PAID';
        }

        return $this->states->apply($payment, $data, 'webhook', $payloadHash);
    }
}
