<?php

return [
    'enabled' => env('QUERY_XRAY_ENABLED', true),

    'environments' => ['local', 'staging'],

    'slow_threshold_ms' => env('QUERY_XRAY_SLOW_MS', 100),

    'n_plus_one_threshold' => env('QUERY_XRAY_N_PLUS_ONE', 10),

    'table_name' => env('QUERY_XRAY_TABLE', 'query_xray_findings'),

    'auto_migrate' => env('QUERY_XRAY_AUTO_MIGRATE', true),

    'sensitive_patterns' => [
        'password', 'passwd', 'secret', 'token', 'api_key', 'apikey',
        'access_key', 'private_key', 'client_secret', 'auth_code',
        'credit_card', 'card_number', 'cvv', 'cvc', 'ssn',
        'social_security', 'pin_code', 'otp', 'bank_account', 'iban',
    ],
    
    'dashboard' => [
        'enabled' => env('QUERY_XRAY_DASHBOARD', true),
        'path' => env('QUERY_XRAY_DASHBOARD_PATH', 'query-xray'),
        'middleware' => ['web'],
        'poll_seconds' => 60,
        'top_n' => env('QUERY_XRAY_TOP_N', 10),
    ],
];
