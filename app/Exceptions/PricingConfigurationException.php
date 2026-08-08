<?php

namespace App\Exceptions;

use RuntimeException;

class PricingConfigurationException extends RuntimeException
{
    public function __construct(string $message = 'Chưa cấu hình giá cơ bản phù hợp cho chi nhánh này.')
    {
        parent::__construct($message);
    }
}
