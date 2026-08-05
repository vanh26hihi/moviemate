<?php

return [
    'pending_ttl_minutes' => (int) env('BOOKING_PENDING_TTL_MINUTES', 15),
    'checkout_token_ttl_minutes' => (int) env('BOOKING_CHECKOUT_TOKEN_TTL_MINUTES', 15),
    'expiration_batch_size' => (int) env('BOOKING_EXPIRATION_BATCH_SIZE', 100),
    'guest_access_ttl_minutes' => (int) env('BOOKING_GUEST_ACCESS_TTL_MINUTES', 1440),
    'guest_session_ttl_minutes' => (int) env('BOOKING_GUEST_SESSION_TTL_MINUTES', 60),
    'ticket_email_access_ttl_minutes' => (int) env('BOOKING_TICKET_EMAIL_ACCESS_TTL_MINUTES', 10080),
    'food_mutation_max_attempts' => (int) env('BOOKING_FOOD_MUTATION_MAX_ATTEMPTS', 6),
    'couple_price_multiplier' => 2,
    'max_food_quantity' => 20,
    'currency' => 'VND',
];
