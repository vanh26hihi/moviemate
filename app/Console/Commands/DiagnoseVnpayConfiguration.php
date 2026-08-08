<?php

namespace App\Console\Commands;

use App\Domain\Payments\VnpayConfig;
use Illuminate\Console\Command;
use Throwable;

class DiagnoseVnpayConfiguration extends Command
{
    protected $signature = 'payments:vnpay-config';

    protected $description = 'Validate VNPAY configuration without printing credentials';

    public function handle(): int
    {
        $tmnCode = config('payment.vnpay.tmn_code');
        $secret = config('payment.vnpay.hash_secret');
        $returnUrl = route('payments.vnpay.return');
        $ipnUrl = route('payments.vnpay.ipn');
        $this->components->twoColumnDetail('Environment', (string) config('payment.vnpay.environment', 'unset'));
        $this->components->twoColumnDetail('TmnCode present', $this->present($tmnCode));
        $this->components->twoColumnDetail('TmnCode masked', $this->maskedTmnCode($tmnCode));
        $this->components->twoColumnDetail('TmnCode length', is_string($tmnCode) ? (string) strlen($tmnCode) : '0');
        $this->components->twoColumnDetail('TmnCode format valid', $this->yesNo(
            is_string($tmnCode) && preg_match('/^[A-Za-z0-9]{8}$/D', $tmnCode) === 1,
        ));
        $this->components->twoColumnDetail('HashSecret present', $this->present($secret));
        $this->components->twoColumnDetail('HashSecret length', is_string($secret) ? (string) strlen($secret) : '0');
        $this->components->twoColumnDetail('HashSecret surrounding whitespace', $this->yesNo(
            is_string($secret) && $secret !== trim($secret),
        ));
        $this->components->twoColumnDetail('HashSecret newline', $this->yesNo(
            is_string($secret) && (str_contains($secret, "\n") || str_contains($secret, "\r")),
        ));
        $this->components->twoColumnDetail('HashSecret contains #', $this->yesNo(
            is_string($secret) && str_contains($secret, '#'),
        ));
        $this->components->twoColumnDetail('Payment host', (string) parse_url((string) config('payment.vnpay.payment_url'), PHP_URL_HOST));
        $this->components->twoColumnDetail('BankCode configured', $this->yesNo((string) config('payment.vnpay.bank_code') !== ''));
        $this->components->twoColumnDetail('Return URL', $returnUrl);
        $this->components->twoColumnDetail('Return host', (string) parse_url($returnUrl, PHP_URL_HOST));
        $this->components->twoColumnDetail('IPN URL', $ipnUrl);
        $this->components->twoColumnDetail('IPN host', (string) parse_url($ipnUrl, PHP_URL_HOST));

        try {
            app(VnpayConfig::class);
        } catch (Throwable $exception) {
            $this->components->error('VNPAY configuration is invalid: '.$exception->getMessage());

            return self::FAILURE;
        }

        $this->components->info('VNPAY configuration is valid. No credentials were displayed.');

        return self::SUCCESS;
    }

    private function present(mixed $value): string
    {
        return is_string($value) && trim($value) !== '' ? 'yes' : 'no';
    }

    private function maskedTmnCode(mixed $value): string
    {
        if (! is_string($value) || $value === '') {
            return 'unset';
        }
        if (strlen($value) < 4) {
            return str_repeat('*', strlen($value));
        }

        return substr($value, 0, 2).str_repeat('*', strlen($value) - 4).substr($value, -2);
    }

    private function yesNo(bool $value): string
    {
        return $value ? 'yes' : 'no';
    }
}
