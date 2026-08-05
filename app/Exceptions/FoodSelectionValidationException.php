<?php

namespace App\Exceptions;

use InvalidArgumentException;

class FoodSelectionValidationException extends InvalidArgumentException
{
    public static function invalidSelection(): self
    {
        return new self('Lựa chọn đồ ăn không hợp lệ. Vui lòng chọn lại món tại bước đồ ăn.');
    }

    public static function unavailable(): self
    {
        return new self('Một hoặc nhiều món ăn không còn khả dụng. Vui lòng chọn lại món.');
    }

    public static function invalidPrice(): self
    {
        return new self('Giá món ăn không hợp lệ. Vui lòng chọn lại món hoặc liên hệ rạp.');
    }
}
