<?php

namespace App\Exceptions;

class ShowtimeScheduleConfigurationException extends ShowtimeScheduleException
{
    public function __construct(string $message)
    {
        parent::__construct($message, 'show_time');
    }
}
