<?php

namespace Tests\Feature\Payments;

use App\Domain\Payments\PayOsSigner;
use App\Models\Booking;
use App\Models\Payment;

abstract class PayOsPaymentTestCase extends PaymentTestCase
{
    protected const CHECKSUM_KEY = 'payos-test-checksum-key-only';

    protected function setUp(): void
    {
        parent::setUp();
        config([
            'services.payos.client_id' => 'payos-test-client-id',
            'services.payos.api_key' => 'payos-test-api-key',
            'services.payos.checksum_key' => self::CHECKSUM_KEY,
            'services.payos.base_url' => 'https://api-merchant.payos.vn',
            'services.payos.connect_timeout_seconds' => 3,
            'services.payos.request_timeout_seconds' => 10,
        ]);
    }

    protected function payOsPayment(?Booking $booking = null, array $overrides = []): Payment
    {
        $booking ??= $this->payableBooking();
        $orderCode = (string) random_int(100000, 999999999);
        $attributes = array_merge([
            'booking_id' => $booking->id,
            'payment_method' => 'payos',
            'order_code' => $orderCode,
            'amount' => 50000,
            'currency' => 'VND',
            'status' => Payment::STATUS_PENDING,
            'description' => 'MM'.substr($orderCode, -6),
            'transaction_code' => '124c33293c43417ab7879e14c8d9eb18',
            'transaction_status' => 'PENDING',
            'expires_at' => now()->addMinutes(10),
            'reconcile_until' => now()->addHours(24),
            'provider_transaction_created_at' => now(),
        ], $overrides);

        return Payment::createForProvider('payos', $attributes);
    }

    /** @param array<string, mixed> $data */
    protected function providerEnvelope(array $data): array
    {
        return [
            'code' => '00',
            'desc' => 'success',
            'data' => $data,
            'signature' => app(PayOsSigner::class)->signData($data, self::CHECKSUM_KEY),
        ];
    }

    protected function webhookBody(Payment $payment, array $overrides = [], bool $validSignature = true): array
    {
        $data = array_merge([
            'orderCode' => (int) $payment->order_code,
            'amount' => $payment->amount,
            'description' => $payment->description,
            'reference' => 'TF230204212323',
            'transactionDateTime' => now()->format('Y-m-d H:i:s'),
            'currency' => 'VND',
            'paymentLinkId' => $payment->transaction_code,
            'code' => '00',
            'desc' => 'Thành công',
        ], $overrides);

        return [
            'code' => '00',
            'desc' => 'success',
            'success' => true,
            'data' => $data,
            'signature' => $validSignature
                ? app(PayOsSigner::class)->signData($data, self::CHECKSUM_KEY)
                : str_repeat('0', 64),
        ];
    }

    protected function queryData(Payment $payment, string $status = 'PENDING', array $overrides = []): array
    {
        return array_merge([
            'id' => $payment->transaction_code,
            'orderCode' => (int) $payment->order_code,
            'amount' => $payment->amount,
            'amountPaid' => $status === 'PAID' ? $payment->amount : 0,
            'amountRemaining' => $status === 'PAID' ? 0 : $payment->amount,
            'status' => $status,
            'transactions' => $status === 'PAID' ? [[
                'reference' => 'TF230204212323',
                'amount' => $payment->amount,
            ]] : [],
        ], $overrides);
    }
}
