<?php

$cleaningBuffer = env('CINEMA_CLEANING_BUFFER_MINUTES', 15);
if (is_string($cleaningBuffer) && preg_match('/^\d+$/', $cleaningBuffer)) {
    $cleaningBuffer = (int) $cleaningBuffer;
}

return [
    'timezone' => env('CINEMA_TIMEZONE', 'Asia/Ho_Chi_Minh'),

    'showtime_cleaning_buffer_minutes' => $cleaningBuffer,
];
