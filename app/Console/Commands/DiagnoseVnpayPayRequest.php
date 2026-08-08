<?php

namespace App\Console\Commands;

use App\Domain\Payments\VnpayConfig;
use App\Domain\Payments\VnpayPayRequestValidator;
use App\Domain\Payments\VnpaySigner;
use App\Models\Payment;
use App\Services\Payments\PaymentReturnTokenService;
use App\Services\Vnpay\VnpayPaymentUrlBuilder;
use Illuminate\Console\Command;
use Throwable;

class DiagnoseVnpayPayRequest extends Command
{
    protected $signature = 'payments:vnpay-request-diagnostics
        {--payment= : Existing VNPAY payment ID}
        {--latest : Inspect the latest existing VNPAY attempt}
        {--client-ip= : Safe IP sample to validate; defaults to configured query IP}';

    protected $description = 'Validate an existing VNPAY PAY request without mutating data or displaying secrets';

    public function handle(): int
    {
        $payment = $this->payment();
        if (! $payment) {
            $this->components->error('No matching VNPAY payment attempt was found.');

            return self::FAILURE;
        }

        $clientIpOption = $this->option('client-ip');
        $clientIp = is_string($clientIpOption) && $clientIpOption !== ''
            ? $clientIpOption
            : (string) config('payment.vnpay.query_ip', '');

        try {
            $config = app(VnpayConfig::class);
            $returnUrl = $config->returnUrl(app(PaymentReturnTokenService::class)->issue($payment));
            $parameters = app(VnpayPaymentUrlBuilder::class)->parameters($payment, $returnUrl, $clientIp);
        } catch (Throwable $exception) {
            $this->identity($payment);
            $this->components->error('Request reconstruction failed safely: '.$exception->getMessage());

            return self::FAILURE;
        }

        $violations = app(VnpayPayRequestValidator::class)->violations(
            $parameters,
            $payment,
            mustBeCurrentlyPayable: false,
        );
        $signer = app(VnpaySigner::class);
        $canonical = $signer->paymentCanonical($parameters);
        $ordered = $parameters;
        ksort($ordered, SORT_STRING);

        $this->identity($payment);
        $this->components->twoColumnDetail('Endpoint', $config->paymentUrl);
        $this->components->twoColumnDetail('Parameter order', implode(', ', array_keys($ordered)));
        foreach ($ordered as $name => $value) {
            $this->components->twoColumnDetail($name, $this->safeValue($name, (string) $value));
        }
        $this->components->twoColumnDetail('Amount digit count', (string) strlen((string) $parameters['vnp_Amount']));
        $this->components->twoColumnDetail('TxnRef format valid', $this->yesNo(
            preg_match('/^[A-Za-z0-9]{1,100}$/D', (string) $payment->order_code) === 1,
        ));
        $this->components->twoColumnDetail('TxnRef duplicate count', (string) Payment::query()
            ->where('provider', 'vnpay')
            ->where('order_code', $payment->order_code)
            ->count());
        $this->components->twoColumnDetail('Attempt replay count', 'not persisted');
        $this->components->twoColumnDetail('Client IP source', is_string($clientIpOption) && $clientIpOption !== ''
            ? 'explicit diagnostic option'
            : 'configured diagnostic sample; browser IP is not persisted');
        $this->components->twoColumnDetail('Client IP family', $this->ipFamily((string) $parameters['vnp_IpAddr']));
        $this->components->twoColumnDetail('Return URL bytes', (string) strlen((string) $parameters['vnp_ReturnUrl']));
        $this->components->twoColumnDetail('Return URL scheme', (string) parse_url((string) $parameters['vnp_ReturnUrl'], PHP_URL_SCHEME));
        $this->components->twoColumnDetail('Return URL host', (string) parse_url((string) $parameters['vnp_ReturnUrl'], PHP_URL_HOST));
        $this->components->twoColumnDetail('Return URL path', (string) parse_url((string) $parameters['vnp_ReturnUrl'], PHP_URL_PATH));
        $this->components->twoColumnDetail('BankCode omitted', $this->yesNo(! array_key_exists('vnp_BankCode', $parameters)));
        $this->components->twoColumnDetail('SecureHashType omitted', $this->yesNo(! array_key_exists('vnp_SecureHashType', $parameters)));
        $this->components->twoColumnDetail('Canonical signing bytes', (string) strlen($canonical));
        $this->components->twoColumnDetail('Canonical SHA-256', hash('sha256', $canonical));
        $this->components->twoColumnDetail('Unsigned query bytes', (string) strlen($canonical));
        $this->components->twoColumnDetail('Expired now', $this->yesNo((bool) $payment->expires_at?->isPast()));

        if ($violations !== []) {
            foreach ($violations as $violation) {
                $this->components->error('Contract violation: '.$violation);
            }

            return self::FAILURE;
        }

        $this->components->info('VNPAY PAY request contract passes. No secret, signature, or signed URL was displayed.');

        return self::SUCCESS;
    }

    private function payment(): ?Payment
    {
        $id = $this->option('payment');
        $query = Payment::query()->with('booking')->where('provider', 'vnpay');
        if (is_string($id) && preg_match('/^[1-9][0-9]*$/D', $id) === 1) {
            return $query->find((int) $id);
        }
        if ($id !== null && $id !== '') {
            return null;
        }

        return $query->latest('id')->first();
    }

    private function identity(Payment $payment): void
    {
        $this->components->twoColumnDetail('Payment ID', (string) $payment->id);
        $this->components->twoColumnDetail('Booking ID', (string) $payment->booking_id);
        $this->components->twoColumnDetail('Provider', (string) $payment->provider);
        $this->components->twoColumnDetail('Status', (string) $payment->status);
        $this->components->twoColumnDetail('Created at', (string) $payment->created_at);
    }

    private function safeValue(string $name, string $value): string
    {
        return match ($name) {
            'vnp_TmnCode' => strlen($value) >= 4
                ? substr($value, 0, 2).str_repeat('*', strlen($value) - 4).substr($value, -2)
                : str_repeat('*', strlen($value)),
            'vnp_ReturnUrl' => (string) preg_replace('/\?.*$/', '?state=[redacted]', $value),
            default => $value,
        };
    }

    private function ipFamily(string $ip): string
    {
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) !== false) {
            return 'IPv4';
        }
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6) !== false) {
            return 'IPv6';
        }

        return 'invalid';
    }

    private function yesNo(bool $value): string
    {
        return $value ? 'yes' : 'no';
    }
}
