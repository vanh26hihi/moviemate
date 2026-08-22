<?php

namespace App\Exceptions;

use App\Domain\Showtimes\BulkShowtimeValidationResult;
use RuntimeException;

final class BulkShowtimeValidationException extends RuntimeException
{
    public function __construct(public readonly BulkShowtimeValidationResult $result)
    {
        parent::__construct('Lô suất chiếu không còn hợp lệ. Không có suất chiếu nào được tạo.');
    }
}
