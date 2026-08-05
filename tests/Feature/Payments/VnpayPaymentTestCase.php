<?php

namespace Tests\Feature\Payments;

use App\Domain\Payments\VnpaySigner;
use App\Models\Booking;
use App\Models\Payment;

abstract class VnpayPaymentTestCase extends PaymentTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->app['url']->forceRootUrl('https://merchant.example.test');
        $this->app['url']->forceScheme('https');
        config([
            'app.url' => 'https://merchant.example.test',
            'payment.public_hosts' => ['merchant.example.test'],
            'payment.driver' => 'vnpay',
            'payment.vnpay.environment' => 'sandbox',
            'payment.vnpay.tmn_code' => 'MOVIE123',
            'payment.vnpay.hash_secret' => str_repeat('sandbox-secret-', 4),
            'payment.vnpay.payment_url' => 'https://sandbox.vnpayment.vn/paymentv2/vpcpay.html',
            'payment.vnpay.query_url' => 'https://sandbox.vnpayment.vn/merchant_webapi/api/transaction',
            'payment.vnpay.bank_code' => 'VNPAYQR',
            'payment.vnpay.locale' => 'vn',
            'payment.vnpay.order_type' => 'other',
            'payment.vnpay.payment_ttl_minutes' => 15,
            'payment.vnpay.http_timeout_seconds' => 10,
            'payment.vnpay.query_interval_seconds' => 60,
            'payment.vnpay.query_ip' => '127.0.0.1',
        ]);
    }

    protected function vnpayPayment(?Booking $booking = null, array $overrides = []): Payment
    {
        $booking ??= $this->payableBooking();

        return Payment::createForProvider('vnpay', array_merge([
            'booking_id' => $booking->id,
            'payment_method' => 'vnpay',
            'order_code' => 'MM'.strtoupper(bin2hex(random_bytes(12))),
            'amount' => 50000,
            'currency' => 'VND',
            'status' => Payment::STATUS_PENDING,
            'description' => 'MovieMate test booking',
            'expires_at' => now()->addMinutes(10),
            'reconcile_until' => now()->addHours(24),
            'provider_transaction_created_at' => now(),
        ], $overrides));
    }

    /** @param array<string,string|int> $overrides */
    protected function signedParameters(Payment $payment, array $overrides = []): array
    {
        $parameters = array_merge([
            'vnp_Amount' => (string) ($payment->amount * 100),
            'vnp_BankCode' => 'NCB',
            'vnp_CardType' => 'ATM',
            'vnp_PayDate' => now('Asia/Ho_Chi_Minh')->format('YmdHis'),
            'vnp_ResponseCode' => '00',
            'vnp_TmnCode' => 'MOVIE123',
            'vnp_TransactionNo' => '14567890',
            'vnp_TransactionStatus' => '00',
            'vnp_TxnRef' => $payment->order_code,
        ], $overrides);
        $parameters['vnp_SecureHash'] = app(VnpaySigner::class)->signPayment(
            $parameters,
            (string) config('payment.vnpay.hash_secret'),
        );

        return $parameters;
    }
}
