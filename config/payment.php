<?php

return [
    'driver' => env('PAYMENT_DRIVER', 'zalopay'),
    'reconciliation_grace_hours' => (int) env('PAYMENT_RECONCILIATION_GRACE_HOURS', 24),
    'return_state_ttl_minutes' => (int) env('PAYMENT_RETURN_STATE_TTL_MINUTES', 30),
    'public_hosts' => array_values(array_filter(array_map(
        'trim',
        explode(',', (string) env('PAYMENT_PUBLIC_HOSTS', parse_url((string) env('APP_URL', ''), PHP_URL_HOST) ?: '')),
    ))),

    'ticket_delivery' => [
        'batch_size' => (int) env('TICKET_DELIVERY_BATCH_SIZE', 100),
        'lease_seconds' => (int) env('TICKET_DELIVERY_LEASE_SECONDS', 300),
        'backoff_base_seconds' => (int) env('TICKET_DELIVERY_BACKOFF_BASE_SECONDS', 60),
        'backoff_max_seconds' => (int) env('TICKET_DELIVERY_BACKOFF_MAX_SECONDS', 3600),
    ],

    'zalopay' => [
        'environment' => env('ZALOPAY_ENV', 'sandbox'),
        'app_id' => env('ZALOPAY_APP_ID'),
        'key1' => env('ZALOPAY_KEY1'),
        'key2' => env('ZALOPAY_KEY2'),
        'callback_url' => env('ZALOPAY_CALLBACK_URL'),
        'redirect_url' => env('ZALOPAY_REDIRECT_URL'),
        'callback_path' => '/payments/zalopay/callback',
        'redirect_path' => '/payments/zalopay/return',
        'expire_duration_seconds' => (int) env('ZALOPAY_EXPIRE_DURATION_SECONDS', 600),
        'http_timeout_seconds' => (int) env('ZALOPAY_HTTP_TIMEOUT_SECONDS', 10),
        'query_interval_seconds' => (int) env('ZALOPAY_QUERY_INTERVAL_SECONDS', 60),
        'sub_app_id' => env('ZALOPAY_SUB_APP_ID'),
        'zlp_payment_id' => env('ZALOPAY_ZLP_PAYMENT_ID'),
        'bank_code' => env('ZALOPAY_BANK_CODE'),
        'endpoints' => [
            'sandbox' => [
                'create' => 'https://sb-openapi.zalopay.vn/v2/create',
                'query' => 'https://sb-openapi.zalopay.vn/v2/query',
            ],
            'production' => [
                'create' => 'https://openapi.zalopay.vn/v2/create',
                'query' => 'https://openapi.zalopay.vn/v2/query',
            ],
        ],
    ],
];
