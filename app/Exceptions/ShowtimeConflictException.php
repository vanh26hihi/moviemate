<?php

namespace App\Exceptions;

use App\Domain\Showtimes\ShowtimeWindow;
use App\Models\Showtime;

class ShowtimeConflictException extends ShowtimeScheduleException
{
    public function __construct(
        public readonly Showtime $conflictingShowtime,
        public readonly ShowtimeWindow $conflictingWindow,
    ) {
        $room = $conflictingShowtime->room?->code ?? $conflictingShowtime->room?->name ?? 'đã chọn';
        $movie = $conflictingShowtime->movie?->title ?? 'suất chiếu khác';

        parent::__construct(sprintf(
            'Phòng %s đã có phim “%s” từ %s đến %s; phòng sẵn sàng lúc %s sau %d phút vệ sinh.',
            $room,
            $movie,
            $conflictingWindow->start->format('d/m/Y H:i'),
            $conflictingWindow->movieEnd->format('d/m/Y H:i'),
            $conflictingWindow->operationalEnd->format('d/m/Y H:i'),
            $conflictingWindow->cleaningBufferMinutes,
        ), 'show_time');
    }
}
