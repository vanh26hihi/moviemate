<?php

namespace App\Services\Vnpay;

use App\Domain\Payments\VerifiedPaymentData;
use App\Domain\Payments\VnpayConfig;
use App\Domain\Payments\VnpaySigner;
use App\Models\Booking;
use App\Models\Payment;
use App\Services\Payments\VerifiedPaymentService;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class VnpayIpnService
{
    public function __construct(
        private readonly VnpayConfig $config,
        private readonly VnpaySigner $signer,
        private readonly VerifiedPaymentService $verifiedPayments,
    ) {}

    /** @return array{RspCode:string,Message:string} */
    public function handle(Request $request): array
    {
        try {
            $parameters = $this->signer->parseQueryString((string) $request->server('QUERY_STRING', ''));
        } catch (InvalidArgumentException) {
            return $this->response('97', 'Invalid signature');
        }

        $secureHash = $parameters['vnp_SecureHash'] ?? null;
        if (! is_string($secureHash)
            || ! $this->signer->verifyPayment($parameters, $secureHash, $this->config->hashSecret)) {
            return $this->response('97', 'Invalid signature');
        }

        if (($parameters['vnp_TmnCode'] ?? null) !== $this->config->tmnCode
            || ! $this->validSchema($parameters)) {
            return $this->response('99', 'Invalid request');
        }
        $payloadHash = hash('sha256', (string) $request->server('QUERY_STRING', ''));

        $payment = Payment::query()
            ->where('provider', 'vnpay')
            ->where('order_code', $parameters['vnp_TxnRef'])
            ->first();
        if (! $payment) {
            return $this->response('01', 'Order not found');
        }

        $expectedAmount = $this->providerAmount($payment->amount);
        if ($expectedAmount === null || ! hash_equals($expectedAmount, $parameters['vnp_Amount'])) {
            $this->storeOutcome($payment, $parameters, Payment::STATUS_REVIEW, 'ipn_amount_mismatch', $payloadHash);

            return $this->response('04', 'Invalid amount');
        }

        if ($parameters['vnp_ResponseCode'] === '00' && $parameters['vnp_TransactionStatus'] === '00') {
            $result = $this->verifiedPayments->verify($payment, new VerifiedPaymentData(
                provider: 'vnpay',
                merchantReference: $parameters['vnp_TxnRef'],
                amount: $payment->amount,
                providerTransactionId: $parameters['vnp_TransactionNo'],
                source: 'ipn',
                payloadHash: $payloadHash,
                responseCode: $parameters['vnp_ResponseCode'],
                transactionStatus: $parameters['vnp_TransactionStatus'],
                bankCode: $this->shortText($parameters['vnp_BankCode'] ?? null, 20),
                cardType: $this->shortText($parameters['vnp_CardType'] ?? null, 20),
                providerPaidAt: $this->payDate($parameters['vnp_PayDate'] ?? null),
            ));

            if ($result->accepted && ! $result->transitioned) {
                return $this->response('02', 'Order already confirmed');
            }
            if ($result->accepted) {
                return $this->response('00', 'Confirm success');
            }

            return $this->response('99', 'Order cannot be fulfilled');
        }

        $status = match ($parameters['vnp_TransactionStatus']) {
            '01' => Payment::STATUS_UNRESOLVED,
            '02' => Payment::STATUS_FAILED,
            '04', '07' => Payment::STATUS_REVIEW,
            default => Payment::STATUS_REVIEW,
        };
        $current = $this->storeOutcome(
            $payment,
            $parameters,
            $status,
            'ipn_status_'.$parameters['vnp_TransactionStatus'],
            $payloadHash,
        );

        return $current === Payment::STATUS_SUCCESS
            ? $this->response('02', 'Order already confirmed')
            : $this->response('00', 'Confirm success');
    }

    /** @param array<string,string> $parameters */
    private function validSchema(array $parameters): bool
    {
        foreach (['vnp_TxnRef', 'vnp_Amount', 'vnp_ResponseCode', 'vnp_TransactionStatus', 'vnp_TransactionNo'] as $key) {
            if (! isset($parameters[$key]) || ! is_string($parameters[$key])) {
                return false;
            }
        }

        return preg_match('/^[A-Za-z0-9]{1,100}$/D', $parameters['vnp_TxnRef']) === 1
            && preg_match('/^[0-9]{3,15}$/D', $parameters['vnp_Amount']) === 1
            && preg_match('/^[0-9]{2}$/D', $parameters['vnp_ResponseCode']) === 1
            && preg_match('/^[0-9]{2}$/D', $parameters['vnp_TransactionStatus']) === 1
            && preg_match('/^[0-9]+$/D', $parameters['vnp_TransactionNo']) === 1;
    }

    /** @param array<string,string> $parameters */
    private function storeOutcome(
        Payment $payment,
        array $parameters,
        string $status,
        string $reason,
        string $payloadHash,
    ): string {
        return DB::transaction(function () use ($payment, $parameters, $status, $reason, $payloadHash): string {
            Booking::query()->lockForUpdate()->findOrFail($payment->booking_id);
            $locked = Payment::query()->lockForUpdate()->findOrFail($payment->id);
            if ($locked->status === Payment::STATUS_SUCCESS) {
                return Payment::STATUS_SUCCESS;
            }
            if (! in_array($locked->status, Payment::RECONCILABLE_STATUSES, true)
                && $locked->status !== Payment::STATUS_REVIEW) {
                return $locked->status;
            }
            $nextStatus = $locked->status === Payment::STATUS_REVIEW ? Payment::STATUS_REVIEW : $status;
            $locked->forceFill([
                'status' => $nextStatus,
                'response_code' => $parameters['vnp_ResponseCode'] ?? null,
                'transaction_status' => $parameters['vnp_TransactionStatus'] ?? null,
                'bank_code' => $this->shortText($parameters['vnp_BankCode'] ?? null, 20),
                'card_type' => $this->shortText($parameters['vnp_CardType'] ?? null, 20),
                'callback_received_at' => now(),
                'callback_payload_hash' => $payloadHash,
                'failure_reason' => $locked->status === Payment::STATUS_REVIEW
                    ? $locked->failure_reason : $reason,
                'failed_at' => in_array($nextStatus, Payment::RECONCILABLE_STATUSES, true) ? null : now(),
            ])->save();

            return $locked->status;
        });
    }

    private function providerAmount(int $amount): ?string
    {
        if ($amount <= 0 || $amount > intdiv(PHP_INT_MAX, 100)) {
            return null;
        }
        $providerAmount = (string) ($amount * 100);

        return strlen($providerAmount) <= 15 ? $providerAmount : null;
    }

    private function payDate(mixed $value): ?CarbonImmutable
    {
        if (! is_string($value) || preg_match('/^\d{14}$/D', $value) !== 1) {
            return null;
        }
        try {
            return CarbonImmutable::createFromFormat('!YmdHis', $value, VnpayConfig::TIMEZONE);
        } catch (\Throwable) {
            return null;
        }
    }

    private function shortText(mixed $value, int $length): ?string
    {
        return is_string($value) ? mb_substr($value, 0, $length) : null;
    }

    /** @return array{RspCode:string,Message:string} */
    private function response(string $code, string $message): array
    {
        return ['RspCode' => $code, 'Message' => $message];
    }
}
