<?php

namespace App\Exceptions;

class InvalidMovieRuntimeException extends ShowtimeScheduleException
{
    public function __construct()
    {
        parent::__construct('Không thể xếp lịch vì thời lượng phim không hợp lệ.', 'movie_id');
    }
}
