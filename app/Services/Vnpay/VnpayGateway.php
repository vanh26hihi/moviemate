<?php

namespace App\Services\Vnpay;

use App\Domain\Payments\VnpayConfig;
use App\Domain\Payments\VnpayGatewayResponse;
use App\Domain\Payments\VnpaySigner;
use App\Exceptions\VnpayAuthenticationException;
use App\Exceptions\VnpayResponseException;
use App\Exceptions\VnpayTransportException;
use App\Models\Payment;
use App\Services\ActivityLogger;
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
        private readonly ActivityLogger $activities,
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
        $requestId = strtoupper(Str::random(32));
        $transactionDate = $payment->provider_transaction_created_at
            ->setTimezone(VnpayConfig::TIMEZONE)->format('YmdHis');
        $fields = [
            'vnp_RequestId' => $requestId,
            'vnp_Version' => '2.1.0',
            'vnp_Command' => 'querydr',
            'vnp_TmnCode' => $this->config->tmnCode,
            'vnp_TxnRef' => $payment->order_code,
            'vnp_OrderInfo' => $this->urls->orderInfo($payment),
            'vnp_TransactionDate' => $transactionDate,
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
            $this->recordAttempt($payment, $requestId, $now->format('YmdHis'), $transactionDate, [
                'error_category' => 'transport_timeout',
                'checksum_verification' => 'unavailable',
                'response_has_checksum' => false,
            ]);
            throw new VnpayTransportException('VNPAY QueryDr could not be reached.');
        } catch (Throwable) {
            $this->recordAttempt($payment, $requestId, $now->format('YmdHis'), $transactionDate, [
                'error_category' => 'transport_other',
                'checksum_verification' => 'unavailable',
                'response_has_checksum' => false,
            ]);
            throw new VnpayTransportException('VNPAY QueryDr failed during transport.');
        }

        if ($response->status() === 401 || $response->status() === 403) {
            $classification = 'transport_http_'.$response->status();
            $this->recordAttempt($payment, $requestId, $now->format('YmdHis'), $transactionDate, [
                'http_status' => $response->status(),
                'error_category' => $classification,
                'checksum_verification' => 'unavailable',
                'response_has_checksum' => false,
            ]);
            throw new VnpayAuthenticationException("VNPAY QueryDr returned HTTP {$response->status()}.");
        }
        if (! $response->successful()) {
            $this->recordAttempt($payment, $requestId, $now->format('YmdHis'), $transactionDate, [
                'http_status' => $response->status(),
                'error_category' => 'transport_http_other',
                'checksum_verification' => 'unavailable',
                'response_has_checksum' => false,
            ]);
            throw new VnpayResponseException("VNPAY QueryDr returned HTTP {$response->status()}.");
        }

        try {
            $payload = json_decode($response->body(), true, 64, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            $this->recordAttempt($payment, $requestId, $now->format('YmdHis'), $transactionDate, [
                'http_status' => $response->status(),
                'error_category' => 'response_invalid_json',
                'checksum_verification' => 'unavailable',
                'response_has_checksum' => false,
            ]);
            throw new VnpayResponseException('VNPAY QueryDr returned malformed JSON.', previous: $exception);
        }
        if (! is_array($payload)) {
            $this->recordAttempt($payment, $requestId, $now->format('YmdHis'), $transactionDate, [
                'http_status' => $response->status(),
                'error_category' => 'response_invalid_schema',
                'checksum_verification' => 'unavailable',
                'response_has_checksum' => false,
            ]);
            throw new VnpayResponseException('VNPAY QueryDr returned an invalid schema.');
        }

        $normalized = [];
        foreach ($payload as $key => $value) {
            if (! is_string($key) || (! is_string($value) && ! is_int($value))) {
                $this->recordAttempt($payment, $requestId, $now->format('YmdHis'), $transactionDate, [
                    'http_status' => $response->status(),
                    'error_category' => 'response_invalid_schema',
                    'checksum_verification' => 'unavailable',
                    'response_has_checksum' => false,
                ]);
                throw new VnpayResponseException('VNPAY QueryDr returned non-scalar fields.');
            }
            $normalized[$key] = (string) $value;
        }
        $secureHash = $normalized['vnp_SecureHash'] ?? null;
        $safeResponse = $this->safeResponseEvidence($normalized);
        if (! is_string($secureHash) || $secureHash === '') {
            $this->recordAttempt($payment, $requestId, $now->format('YmdHis'), $transactionDate, $safeResponse + [
                'http_status' => $response->status(),
                'error_category' => 'response_missing_checksum',
                'checksum_verification' => 'unavailable',
                'response_has_checksum' => false,
            ]);
            throw new VnpayAuthenticationException('VNPAY QueryDr response checksum was missing.');
        }
        if (! $this->signer->verifyQueryResponse($normalized, $secureHash, $this->config->hashSecret)) {
            $this->recordAttempt($payment, $requestId, $now->format('YmdHis'), $transactionDate, $safeResponse + [
                'http_status' => $response->status(),
                'error_category' => 'response_checksum_mismatch',
                'checksum_verification' => 'mismatch',
                'response_has_checksum' => true,
            ]);
            throw new VnpayAuthenticationException('VNPAY QueryDr response checksum was rejected.');
        }

        $responseCode = $normalized['vnp_ResponseCode'] ?? null;
        $this->recordAttempt($payment, $requestId, $now->format('YmdHis'), $transactionDate, $safeResponse + [
            'http_status' => $response->status(),
            'error_category' => $responseCode === '00' ? 'provider_query_success' : 'provider_response_error',
            'checksum_verification' => 'match',
            'response_has_checksum' => true,
        ]);

        return new VnpayGatewayResponse($normalized, hash('sha256', $response->body()));
    }

    /** @param array<string, mixed> $outcome */
    private function recordAttempt(
        Payment $payment,
        string $requestId,
        string $requestedAt,
        string $transactionDate,
        array $outcome,
    ): void {
        $this->activities->log('payment.vnpay_query_attempted', $payment, context: [
            'payment_id' => $payment->id,
            'booking_id' => $payment->booking_id,
            'provider' => 'vnpay',
            'txn_ref' => $payment->order_code,
            'query_request_id' => $requestId,
            'query_requested_at' => $requestedAt,
            'transaction_date_sent' => $transactionDate,
            ...$outcome,
        ]);
    }

    /** @param array<string, string> $response @return array<string, string> */
    private function safeResponseEvidence(array $response): array
    {
        return array_filter([
            'provider_response_code' => $this->bounded($response['vnp_ResponseCode'] ?? null, 20),
            'provider_transaction_status' => $this->bounded($response['vnp_TransactionStatus'] ?? null, 20),
            'provider_message' => $this->bounded($response['vnp_Message'] ?? null, 255),
        ], static fn (?string $value): bool => $value !== null);
    }

    private function bounded(?string $value, int $length): ?string
    {
        return is_string($value) && $value !== '' ? mb_substr($value, 0, $length) : null;
    }
}
