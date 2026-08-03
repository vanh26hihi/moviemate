<?php

return [
    'pending_ttl_minutes' => (int) env('BOOKING_PENDING_TTL_MINUTES', 15),
    'expiration_batch_size' => (int) env('BOOKING_EXPIRATION_BATCH_SIZE', 100),
    'guest_access_ttl_minutes' => (int) env('BOOKING_GUEST_ACCESS_TTL_MINUTES', 1440),
    'guest_session_ttl_minutes' => (int) env('BOOKING_GUEST_SESSION_TTL_MINUTES', 60),
];
