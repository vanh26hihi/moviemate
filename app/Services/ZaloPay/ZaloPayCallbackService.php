<?php

namespace App\Services\ZaloPay;

use App\Domain\Payments\VerifiedPaymentData;
use App\Domain\Payments\ZaloPayConfig;
use App\Domain\Payments\ZaloPaySigner;
use App\Models\Payment;
use App\Services\Payments\VerifiedPaymentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use JsonException;
use Throwable;

class ZaloPayCallbackService
{
    public function __construct(
        private readonly ZaloPayConfig $config,
        private readonly ZaloPaySigner $signer,
        private readonly VerifiedPaymentService $verifiedPayments,
        private readonly ZaloPayCallbackResponseFactory $responses,
    ) {}

    public function handle(Request $request): JsonResponse
    {
        $type = $request->input('type');
        $rawData = $request->input('data');
        $mac = $request->input('mac');

        if (($type !== 1 && $type !== '1') || ! is_string($rawData) || ! is_string($mac)) {
            return $this->responses->permanent('Invalid callback envelope');
        }

        if (! $this->signer->verifyCallback($rawData, $mac, $this->config->key2)) {
            return $this->responses->permanent('Invalid MAC');
        }

        try {
            $data = json_decode($rawData, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return $this->responses->permanent('Invalid callback data');
        }

        if (! is_array($data)
            || ! is_int($data['app_id'] ?? null)
            || ! is_string($data['app_trans_id'] ?? null)
            || ! is_int($data['amount'] ?? null)) {
            return $this->responses->permanent('Invalid callback identity');
        }

        $payment = Payment::query()
            ->where('provider', 'zalopay')
            ->where('app_trans_id', $data['app_trans_id'])
            ->first();

        if (! $payment) {
            Log::warning('ZaloPay callback referenced an unknown payment attempt.', [
                'app_trans_id_hash' => hash('sha256', $data['app_trans_id']),
            ]);

            return $this->responses->permanent('Unknown payment');
        }

        try {
            $result = $this->verifiedPayments->verify($payment, new VerifiedPaymentData(
                appId: $data['app_id'],
                appTransId: $data['app_trans_id'],
                amount: $data['amount'],
                zpTransId: $this->normalizeTransactionId($data['zp_trans_id'] ?? null),
                serverTimeMs: is_int($data['server_time'] ?? null) ? $data['server_time'] : null,
                source: 'callback',
                payloadHash: hash('sha256', $rawData),
            ));
        } catch (Throwable $exception) {
            Log::error('ZaloPay callback processing failed transiently.', [
                'payment_id' => $payment->id,
                'exception' => $exception::class,
            ]);

            return $this->responses->transient();
        }

        return $result->accepted
            ? $this->responses->success()
            : $this->responses->permanent($result->message);
    }

    private function normalizeTransactionId(mixed $value): ?string
    {
        if (is_int($value)) {
            return (string) $value;
        }

        return is_string($value) && preg_match('/^[0-9]+$/D', $value) ? $value : null;
    }
}
