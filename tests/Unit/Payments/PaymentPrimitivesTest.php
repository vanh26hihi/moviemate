<?php

namespace Tests\Unit\Payments;

use App\Domain\Payments\AppTransIdGenerator;
use App\Domain\Payments\VndAmount;
use App\Domain\Payments\ZaloPayConfig;
use App\Exceptions\PaymentConfigurationException;
use Carbon\CarbonImmutable;
use InvalidArgumentException;
use Tests\TestCase;

class PaymentPrimitivesTest extends TestCase
{
    public function test_app_trans_id_date_prefix_uses_gmt_plus_seven(): void
    {
        $at = CarbonImmutable::parse('2026-08-03 18:30:00', 'UTC');
        $id = (new AppTransIdGenerator)->generate($at);

        $this->assertStringStartsWith('260804_', $id);
        $this->assertLessThanOrEqual(40, strlen($id));
    }

    public function test_app_trans_id_is_unique(): void
    {
        $generator = new AppTransIdGenerator;
        $ids = array_map(fn () => $generator->generate(), range(1, 100));

        $this->assertCount(100, array_unique($ids));
    }

    public function test_integer_vnd_conversion_is_exact_and_does_not_use_float(): void
    {
        $this->assertSame(50000, VndAmount::fromDecimal('50000.00'));
        $this->assertSame(PHP_INT_MAX, VndAmount::fromDecimal((string) PHP_INT_MAX));
    }

    public function test_fractional_non_positive_float_and_overflow_amounts_are_rejected(): void
    {
        foreach (['50000.01', '0', '-1', '1e5', (string) PHP_INT_MAX.'0', 50000.5] as $invalid) {
            try {
                VndAmount::fromDecimal($invalid);
                $this->fail("{$invalid} should have been rejected.");
            } catch (InvalidArgumentException) {
                $this->addToAssertionCount(1);
            }
        }
    }

    public function test_missing_payment_configuration_fails_closed(): void
    {
        config([
            'payment.driver' => 'zalopay',
            'payment.zalopay.app_id' => null,
            'payment.zalopay.key1' => null,
            'payment.zalopay.key2' => null,
        ]);

        $this->expectException(PaymentConfigurationException::class);
        new ZaloPayConfig;
    }
}
