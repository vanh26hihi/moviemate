<?php

namespace App\Services\PayOs;

use App\Domain\Payments\PayOsConfig;
use App\Domain\Payments\PayOsGatewayResponse;
use App\Domain\Payments\PayOsSigner;
use App\Exceptions\PayOsAuthenticationException;
use App\Exceptions\PayOsRateLimitException;
use App\Exceptions\PayOsResponseException;
use App\Exceptions\PayOsTransportException;
use App\Models\Payment;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use JsonException;
use Throwable;

final class PayOsGateway
{
    public function __construct(
        private readonly PayOsConfig $config,
        private readonly PayOsSigner $signer,
    ) {}

    public function create(Payment $payment, string $returnUrl, string $cancelUrl): PayOsGatewayResponse
    {
        $orderCode = $this->orderCode($payment);
        $fields = [
            'amount' => $payment->amount,
            'cancelUrl' => $cancelUrl,
            'description' => (string) $payment->description,
            'orderCode' => $orderCode,
            'returnUrl' => $returnUrl,
        ];
        $payload = $fields + [
            'expiredAt' => $this->expiration($payment),
            'signature' => $this->signer->createPaymentRequestSignature($fields, $this->config->checksumKey),
        ];

        return $this->send(
            fn (): Response => $this->client()->post('/v2/payment-requests', $payload),
            'create payment link',
        );
    }

    public function query(Payment $payment): PayOsGatewayResponse
    {
        $id = rawurlencode((string) $this->orderCode($payment));

        return $this->send(
            fn (): Response => $this->client()->retry(
                2,
                200,
                fn (Throwable $exception): bool => $exception instanceof ConnectionException,
                throw: false,
            )->get("/v2/payment-requests/{$id}"),
            'query payment link',
        );
    }

    public function cancel(Payment $payment): PayOsGatewayResponse
    {
        $id = rawurlencode((string) $this->orderCode($payment));

        return $this->send(
            fn (): Response => $this->client()->post("/v2/payment-requests/{$id}/cancel", [
                'cancellationReason' => 'Customer requested cancellation',
            ]),
            'cancel payment link',
        );
    }

    private function client(): PendingRequest
    {
        return Http::baseUrl($this->config->baseUrl)
            ->asJson()
            ->acceptJson()
            ->withHeaders([
                'x-client-id' => $this->config->clientId,
                'x-api-key' => $this->config->apiKey,
            ])
            ->connectTimeout($this->config->connectTimeoutSeconds)
            ->timeout($this->config->requestTimeoutSeconds);
    }

    /** @param callable():Response $request */
    private function send(callable $request, string $operation): PayOsGatewayResponse
    {
        try {
            $response = $request();
        } catch (ConnectionException) {
            throw new PayOsTransportException("payOS {$operation} could not be reached.");
        } catch (Throwable) {
            throw new PayOsTransportException("payOS {$operation} failed during transport.");
        }

        if (in_array($response->status(), [401, 403], true)) {
            throw new PayOsAuthenticationException("payOS {$operation} authentication was rejected.");
        }
        if ($response->status() === 429) {
            throw new PayOsRateLimitException("payOS {$operation} was rate limited.");
        }
        if ($response->serverError()) {
            throw new PayOsTransportException("payOS {$operation} is temporarily unavailable.");
        }
        if (! $response->successful()) {
            throw new PayOsResponseException("payOS {$operation} was rejected.");
        }

        try {
            $payload = json_decode($response->body(), true, 64, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new PayOsResponseException("payOS {$operation} returned malformed JSON.", previous: $exception);
        }

        if (! is_array($payload)
            || ! is_string($payload['code'] ?? null)
            || ! is_array($payload['data'] ?? null)
            || ! $this->signer->verifyData($payload['data'], $payload['signature'] ?? null, $this->config->checksumKey)) {
            throw new PayOsAuthenticationException("payOS {$operation} response signature was rejected.");
        }
        if ($payload['code'] !== '00') {
            throw new PayOsResponseException("payOS {$operation} returned a non-success result.");
        }

        return new PayOsGatewayResponse(
            $payload['code'],
            $payload['data'],
            hash('sha256', $response->body()),
        );
    }

    private function orderCode(Payment $payment): int
    {
        if ($payment->provider !== 'payos'
            || ! is_string($payment->order_code)
            || preg_match('/^[1-9][0-9]{0,15}$/D', $payment->order_code) !== 1) {
            throw new PayOsResponseException('payOS payment identity is incomplete.');
        }

        return (int) $payment->order_code;
    }

    private function expiration(Payment $payment): int
    {
        $timestamp = $payment->expires_at?->getTimestamp();
        if (! is_int($timestamp) || $timestamp <= now()->getTimestamp() + 59 || $timestamp > 2147483647) {
            throw new PayOsResponseException('payOS payment expiration is outside the supported window.');
        }

        return $timestamp;
    }
}
