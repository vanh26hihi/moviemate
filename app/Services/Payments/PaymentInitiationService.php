<?php

namespace App\Services\Payments;

use App\Domain\Payments\PayOsConfig;
use App\Domain\Payments\VnpayConfig;
use App\Domain\Payments\ZaloPayConfig;
use App\Exceptions\PaymentConfigurationException;
use App\Exceptions\PaymentInitiationException;
use App\Models\Booking;

class PaymentInitiationService
{
    public function initiate(
        Booking $booking,
        ?string $provider = null,
        ?string $clientIp = null,
    ): PaymentInitiationResult {
        $provider = strtolower(trim($provider ?? (string) config('payment.driver', 'vnpay')));
        $this->assertAvailable($provider);

        return match ($provider) {
            'vnpay' => app(VnpayPaymentInitiationService::class)->initiate($booking, $clientIp ?? '127.0.0.1'),
            'zalopay' => app(ZaloPayPaymentInitiationService::class)->initiate($booking),
            'payos' => app(PayOsPaymentInitiationService::class)->initiate($booking),
            default => throw new PaymentInitiationException('The selected payment provider is not supported.'),
        };
    }

    public function assertAvailable(string $provider): void
    {
        if (! $this->isAvailable($provider)) {
            throw new PaymentInitiationException('The selected payment provider is not configured.');
        }
    }

    /** @return array<string, bool> */
    public function availability(): array
    {
        return [
            'vnpay' => $this->isAvailable('vnpay'),
            'zalopay' => $this->isAvailable('zalopay'),
            'payos' => $this->isAvailable('payos'),
        ];
    }

    private function isAvailable(string $provider): bool
    {
        try {
            return match (strtolower(trim($provider))) {
                'vnpay' => VnpayConfig::isConfigured() && app(VnpayConfig::class) instanceof VnpayConfig,
                'zalopay' => ZaloPayConfig::isConfigured() && app(ZaloPayConfig::class) instanceof ZaloPayConfig,
                'payos' => PayOsConfig::isConfigured() && app(PayOsConfig::class) instanceof PayOsConfig,
                default => false,
            };
        } catch (PaymentConfigurationException) {
            return false;
        }
    }
}
