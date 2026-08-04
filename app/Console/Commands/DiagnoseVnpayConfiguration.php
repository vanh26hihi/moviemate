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
        $returnUrl = route('payments.vnpay.return');
        $ipnUrl = route('payments.vnpay.ipn');
        $this->components->twoColumnDetail('Environment', (string) config('payment.vnpay.environment', 'unset'));
        $this->components->twoColumnDetail('TmnCode present', $this->present(config('payment.vnpay.tmn_code')));
        $this->components->twoColumnDetail('HashSecret present', $this->present(config('payment.vnpay.hash_secret')));
        $this->components->twoColumnDetail('Payment host', (string) parse_url((string) config('payment.vnpay.payment_url'), PHP_URL_HOST));
        $this->components->twoColumnDetail('Return host', (string) parse_url($returnUrl, PHP_URL_HOST));
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
}
