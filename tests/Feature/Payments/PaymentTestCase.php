<?php

namespace Tests\Feature\Payments;

use App\Domain\Payments\ZaloPaySigner;
use App\Models\Booking;
use App\Models\Payment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\Support\CreatesBookingFixtures;
use Tests\TestCase;

abstract class PaymentTestCase extends TestCase
{
    use CreatesBookingFixtures, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'payment.driver' => 'zalopay',
            'payment.zalopay.environment' => 'sandbox',
            'payment.zalopay.app_id' => 2553,
            'payment.zalopay.key1' => 'test-key1',
            'payment.zalopay.key2' => 'test-key2',
            'payment.zalopay.callback_url' => 'https://merchant.example.test/payments/zalopay/callback',
            'payment.zalopay.redirect_url' => 'https://merchant.example.test/payments/zalopay/return',
            'payment.zalopay.expire_duration_seconds' => 600,
            'payment.zalopay.http_timeout_seconds' => 10,
            'payment.zalopay.query_interval_seconds' => 60,
        ]);

        Queue::fake();
    }

    protected function payableBooking(array $overrides = []): Booking
    {
        $scenario = $this->bookingScenario(false);
        $result = $this->reserve($scenario, [$scenario['seats'][0]->id]);

        if ($overrides !== []) {
            $result->booking->forceFill($overrides)->save();
        }

        return $result->booking->fresh();
    }

    protected function pendingPayment(?Booking $booking = null, array $overrides = []): Payment
    {
        $booking ??= $this->payableBooking();

        $attributes = array_merge([
            'booking_id' => $booking->id,
            'provider' => 'zalopay',
            'payment_method' => 'zalopay',
            'app_id' => 2553,
            'app_trans_id' => now('Asia/Ho_Chi_Minh')->format('ymd').'_'.bin2hex(random_bytes(12)),
            'app_user' => 'test-user',
            'app_time_ms' => (int) floor(microtime(true) * 1000),
            'amount' => 50000,
            'currency' => 'VND',
            'status' => Payment::STATUS_PENDING,
            'description' => 'Test booking',
            'expires_at' => now()->addMinutes(10),
            'reconcile_until' => now()->addHours(24),
        ], $overrides);
        $provider = $attributes['provider'];
        unset($attributes['provider']);

        return Payment::createForProvider($provider, $attributes);
    }

    protected function callbackBody(Payment $payment, array $overrides = [], bool $validMac = true): array
    {
        $data = array_merge([
            'app_id' => $payment->app_id,
            'app_trans_id' => $payment->app_trans_id,
            'amount' => $payment->amount,
            'zp_trans_id' => random_int(100000, 999999),
            'server_time' => (int) floor(microtime(true) * 1000),
        ], $overrides);
        $raw = json_encode($data, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);

        return [
            'data' => $raw,
            'mac' => $validMac
                ? app(ZaloPaySigner::class)->callbackMac($raw, 'test-key2')
                : str_repeat('0', 64),
            'type' => 1,
        ];
    }
}
