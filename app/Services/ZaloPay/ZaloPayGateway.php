<?php

namespace App\Services\ZaloPay;

use App\Domain\Payments\ZaloPayConfig;
use App\Domain\Payments\ZaloPayGatewayResponse;
use App\Domain\Payments\ZaloPaySigner;
use App\Exceptions\ZaloPayAuthenticationException;
use App\Exceptions\ZaloPayResponseException;
use App\Exceptions\ZaloPayTransportException;
use App\Models\Payment;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use JsonException;
use Throwable;

class ZaloPayGateway
{
    public function __construct(
        private readonly ZaloPayConfig $config,
        private readonly ZaloPaySigner $signer,
    ) {}

    public function create(Payment $payment, string $redirectUrl): ZaloPayGatewayResponse
    {
        $payment->loadMissing('booking');

        try {
            $item = json_encode([[
                'booking_code' => $payment->booking->booking_code,
                'amount' => $payment->amount,
            ]], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
            $embedData = json_encode([
                'redirecturl' => $redirectUrl,
            ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new ZaloPayResponseException('Unable to encode the ZaloPay order payload.', previous: $exception);
        }

        $fields = [
            'app_id' => $payment->app_id,
            'app_user' => $payment->app_user,
            'app_trans_id' => $payment->app_trans_id,
            'app_time' => $payment->app_time_ms,
            'amount' => $payment->amount,
            'description' => $payment->description,
            'item' => $item,
            'embed_data' => $embedData,
        ];
        $fields['mac'] = $this->signer->createMac($fields, $this->config->key1);

        try {
            $response = $this->client()->post($this->config->createEndpoint, $fields);
        } catch (ConnectionException) {
            throw new ZaloPayTransportException('ZaloPay create order could not be reached.');
        } catch (Throwable) {
            throw new ZaloPayTransportException('ZaloPay create order failed during transport.');
        }

        return $this->parse($response, 'create order');
    }

    public function query(Payment $payment): ZaloPayGatewayResponse
    {
        $fields = [
            'app_id' => $payment->app_id,
            'app_trans_id' => $payment->app_trans_id,
        ];
        $fields['mac'] = $this->signer->queryMac(
            $payment->app_id,
            $payment->app_trans_id,
            $this->config->key1,
        );

        try {
            $response = $this->client()
                ->retry(
                    2,
                    200,
                    fn (Throwable $exception): bool => $exception instanceof ConnectionException,
                    throw: false,
                )
                ->post($this->config->queryEndpoint, $fields);
        } catch (ConnectionException) {
            throw new ZaloPayTransportException('ZaloPay query could not be reached.');
        } catch (Throwable) {
            throw new ZaloPayTransportException('ZaloPay query failed during transport.');
        }

        return $this->parse($response, 'query order');
    }

    private function client(): PendingRequest
    {
        return Http::asForm()
            ->acceptJson()
            ->connectTimeout(min(3, $this->config->httpTimeoutSeconds))
            ->timeout($this->config->httpTimeoutSeconds);
    }

    private function parse(Response $response, string $operation): ZaloPayGatewayResponse
    {
        if (in_array($response->status(), [401, 403], true)) {
            throw new ZaloPayAuthenticationException("ZaloPay {$operation} authentication was rejected.");
        }

        if (! $response->successful()) {
            throw new ZaloPayResponseException("ZaloPay {$operation} returned HTTP {$response->status()}.");
        }

        try {
            $payload = json_decode($response->body(), true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new ZaloPayResponseException("ZaloPay {$operation} returned malformed JSON.", previous: $exception);
        }

        if (! is_array($payload) || ! is_int($payload['return_code'] ?? null)) {
            throw new ZaloPayResponseException("ZaloPay {$operation} returned an invalid schema.");
        }

        return new ZaloPayGatewayResponse($payload, hash('sha256', $response->body()));
    }
}
