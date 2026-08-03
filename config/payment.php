<?php

return [
    'driver' => env('PAYMENT_DRIVER', 'zalopay'),

    'zalopay' => [
        'environment' => env('ZALOPAY_ENV', 'sandbox'),
        'app_id' => env('ZALOPAY_APP_ID'),
        'key1' => env('ZALOPAY_KEY1'),
        'key2' => env('ZALOPAY_KEY2'),
        'callback_url' => env('ZALOPAY_CALLBACK_URL'),
        'redirect_url' => env('ZALOPAY_REDIRECT_URL'),
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
