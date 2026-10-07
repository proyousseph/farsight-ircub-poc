<?php

return [
    'mock_base_url' => env('CHANNEL_MOCK_BASE_URL', 'http://127.0.0.1:8001/mock-api'),
    /*
    | Callback HMAC secret. Production refuses insecure/default values.
    | Local/testing may use the mock default for POC scripts.
    */
    'callback_secret' => env('CHANNEL_CALLBACK_SECRET', 'ircub-mock-callback-secret'),
    'callback_max_skew_seconds' => (int) env('CHANNEL_CALLBACK_MAX_SKEW', 300),
    'insecure_callback_secrets' => [
        'ircub-mock-callback-secret',
        'changeme',
        'secret',
        '',
    ],
    'local_currency' => env('CHANNEL_LOCAL_CURRENCY', 'SOS'),
    'max_retries' => (int) env('CHANNEL_MAX_RETRIES', 3),
    'retry_delay_seconds' => (int) env('CHANNEL_RETRY_DELAY_SECONDS', 30),
    'allow_simulate' => (bool) env('CHANNEL_ALLOW_SIMULATE', env('APP_ENV', 'production') === 'local' || env('APP_ENV') === 'testing'),
];
