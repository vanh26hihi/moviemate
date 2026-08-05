<?php

namespace App\Exceptions;

use RuntimeException;

class ShowtimeScheduleException extends RuntimeException
{
    public function __construct(
        string $message,
        public readonly string $field = 'show_time',
    ) {
        parent::__construct($message);
    }
}
