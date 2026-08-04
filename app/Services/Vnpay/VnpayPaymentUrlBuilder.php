<?php

namespace App\Services\Vnpay;

use App\Domain\Payments\VnpayConfig;
use App\Domain\Payments\VnpaySigner;
use App\Exceptions\PaymentInitiationException;
use App\Models\Payment;

class VnpayPaymentUrlBuilder
{
    public function __construct(
        private readonly VnpayConfig $config,
        private readonly VnpaySigner $signer,
    ) {}

    public function build(Payment $payment, string $returnUrl, string $clientIp): string
    {
        if ($payment->provider !== 'vnpay'
            || ! is_string($payment->order_code)
            || preg_match('/^[A-Za-z0-9]{1,100}$/D', $payment->order_code) !== 1
            || ! $payment->provider_transaction_created_at
            || ! $payment->expires_at) {
            throw new PaymentInitiationException('The VNPAY payment attempt is incomplete.');
        }
        if (filter_var($clientIp, FILTER_VALIDATE_IP) === false) {
            throw new PaymentInitiationException('The VNPAY client IP address is invalid.');
        }

        $amount = $payment->amount;
        if (! is_int($amount) || $amount <= 0 || $amount > intdiv(PHP_INT_MAX, 100)) {
            throw new PaymentInitiationException('The VNPAY amount is outside the supported integer range.');
        }
        $providerAmount = $amount * 100;
        if (strlen((string) $providerAmount) > 12) {
            throw new PaymentInitiationException('The VNPAY amount exceeds the provider limit.');
        }

        $parameters = [
            'vnp_Version' => '2.1.0',
            'vnp_Command' => 'pay',
            'vnp_TmnCode' => $this->config->tmnCode,
            'vnp_Amount' => $providerAmount,
            'vnp_CreateDate' => $payment->provider_transaction_created_at
                ->setTimezone(VnpayConfig::TIMEZONE)->format('YmdHis'),
            'vnp_CurrCode' => 'VND',
            'vnp_IpAddr' => $clientIp,
            'vnp_Locale' => $this->config->locale,
            'vnp_OrderInfo' => $this->orderInfo($payment),
            'vnp_OrderType' => $this->config->orderType,
            'vnp_ReturnUrl' => $returnUrl,
            'vnp_ExpireDate' => $payment->expires_at
                ->setTimezone(VnpayConfig::TIMEZONE)->format('YmdHis'),
            'vnp_TxnRef' => $payment->order_code,
        ];
        if ($this->config->bankCode !== '') {
            $parameters['vnp_BankCode'] = $this->config->bankCode;
        }

        $query = $this->signer->paymentCanonical($parameters);
        $secureHash = $this->signer->signPayment($parameters, $this->config->hashSecret);

        return $this->config->paymentUrl.'?'.$query.'&vnp_SecureHash='.$secureHash;
    }

    public function orderInfo(Payment $payment): string
    {
        $bookingCode = $payment->booking?->booking_code ?? (string) $payment->booking_id;
        $value = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', 'MovieMate booking '.$bookingCode);
        $value = is_string($value) ? $value : 'MovieMate booking';
        $value = preg_replace('/[^A-Za-z0-9 ._-]+/', ' ', $value) ?? 'MovieMate booking';
        $value = trim(preg_replace('/\s+/', ' ', $value) ?? $value);

        return mb_substr($value, 0, 255, 'UTF-8');
    }
}
