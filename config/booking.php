<?php

return [
    'pending_ttl_minutes' => (int) env('BOOKING_PENDING_TTL_MINUTES', 15),
    'expiration_batch_size' => (int) env('BOOKING_EXPIRATION_BATCH_SIZE', 100),
    'couple_price_multiplier' => 2,
    'max_food_quantity' => 20,
];
