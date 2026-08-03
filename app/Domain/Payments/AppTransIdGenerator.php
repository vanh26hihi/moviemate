<?php

namespace App\Domain\Payments;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

final class AppTransIdGenerator
{
    private const TIMEZONE = 'Asia/Ho_Chi_Minh';

    public function generate(?CarbonInterface $at = null): string
    {
        $date = $at
            ? CarbonImmutable::instance($at)->setTimezone(self::TIMEZONE)
            : CarbonImmutable::now(self::TIMEZONE);

        return $date->format('ymd').'_'.bin2hex(random_bytes(12));
    }
}
