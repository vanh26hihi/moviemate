<?php

namespace App\Services\Vnpay;

use App\Domain\Payments\VnpayConfig;
use App\Domain\Payments\VnpayGatewayResponse;
use App\Domain\Payments\VnpaySigner;
use App\Exceptions\VnpayAuthenticationException;
use App\Exceptions\VnpayResponseException;
use App\Exceptions\VnpayTransportException;
use App\Models\Payment;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use JsonException;
use Throwable;

class VnpayGateway
{
    public function __construct(
        private readonly VnpayConfig $config,
        private readonly VnpaySigner $signer,
        private readonly VnpayPaymentUrlBuilder $urls,
    ) {}

    public function query(Payment $payment): VnpayGatewayResponse
    {
        $payment->loadMissing('booking');
        if ($payment->provider !== 'vnpay'
            || ! is_string($payment->order_code)
            || ! $payment->provider_transaction_created_at) {
            throw new VnpayResponseException('VNPAY query payment identity is incomplete.');
        }

        $now = now(VnpayConfig::TIMEZONE);
        $fields = [
            'vnp_RequestId' => strtoupper(Str::random(32)),
            'vnp_Version' => '2.1.0',
            'vnp_Command' => 'querydr',
            'vnp_TmnCode' => $this->config->tmnCode,
            'vnp_TxnRef' => $payment->order_code,
            'vnp_OrderInfo' => $this->urls->orderInfo($payment),
            'vnp_TransactionDate' => $payment->provider_transaction_created_at
                ->setTimezone(VnpayConfig::TIMEZONE)->format('YmdHis'),
            'vnp_CreateDate' => $now->format('YmdHis'),
            'vnp_IpAddr' => $this->config->queryIp,
        ];
        if (is_string($payment->transaction_id) && $payment->transaction_id !== '') {
            $fields['vnp_TransactionNo'] = $payment->transaction_id;
        }
        $fields['vnp_SecureHash'] = $this->signer->signQueryRequest($fields, $this->config->hashSecret);

        try {
            $response = Http::asJson()
                ->acceptJson()
                ->connectTimeout(min(3, $this->config->httpTimeoutSeconds))
                ->timeout($this->config->httpTimeoutSeconds)
                ->retry(2, 200, fn (Throwable $exception): bool => $exception instanceof ConnectionException, throw: false)
                ->post($this->config->queryUrl, $fields);
        } catch (ConnectionException) {
            throw new VnpayTransportException('VNPAY QueryDr could not be reached.');
        } catch (Throwable) {
            throw new VnpayTransportException('VNPAY QueryDr failed during transport.');
        }

        if (in_array($response->status(), [401, 403], true)) {
            throw new VnpayAuthenticationException('VNPAY QueryDr authentication was rejected.');
        }
        if (! $response->successful()) {
            throw new VnpayResponseException("VNPAY QueryDr returned HTTP {$response->status()}.");
        }

        try {
            $payload = json_decode($response->body(), true, 64, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new VnpayResponseException('VNPAY QueryDr returned malformed JSON.', previous: $exception);
        }
        if (! is_array($payload)) {
            throw new VnpayResponseException('VNPAY QueryDr returned an invalid schema.');
        }

        $normalized = [];
        foreach ($payload as $key => $value) {
            if (! is_string($key) || (! is_string($value) && ! is_int($value))) {
                throw new VnpayResponseException('VNPAY QueryDr returned non-scalar fields.');
            }
            $normalized[$key] = (string) $value;
        }
        $secureHash = $normalized['vnp_SecureHash'] ?? null;
        if (! is_string($secureHash)
            || ! $this->signer->verifyQueryResponse($normalized, $secureHash, $this->config->hashSecret)) {
            throw new VnpayAuthenticationException('VNPAY QueryDr response checksum was rejected.');
        }

        return new VnpayGatewayResponse($normalized, hash('sha256', $response->body()));
    }
}
