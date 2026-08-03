<?php

namespace App\Services\Payments;

use App\Exceptions\PaymentConfigurationException;
use App\Models\Payment;

class PaymentReturnTokenService
{
    public function issue(Payment $payment): string
    {
        return hash_hmac('sha256', $this->message($payment), $this->key());
    }

    public function verify(Payment $payment, mixed $token): bool
    {
        return is_string($token)
            && preg_match('/^[a-f0-9]{64}$/D', $token) === 1
            && hash_equals($this->issue($payment), strtolower($token));
    }

    private function message(Payment $payment): string
    {
        return 'zalopay-return|'.$payment->booking_id.'|'.$payment->app_trans_id;
    }

    private function key(): string
    {
        $key = config('app.key');

        if (! is_string($key) || $key === '') {
            throw new PaymentConfigurationException('APP_KEY is required for payment return tokens.');
        }

        return $key;
    }
}
