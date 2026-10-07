<?php

return [
    'mock_base_url' => env('CHANNEL_MOCK_BASE_URL', 'http://127.0.0.1:8001/mock-api'),
    'callback_secret' => env('CHANNEL_CALLBACK_SECRET', 'ircub-mock-callback-secret'),
    'local_currency' => env('CHANNEL_LOCAL_CURRENCY', 'SOS'),
    'max_retries' => (int) env('CHANNEL_MAX_RETRIES', 3),
    'retry_delay_seconds' => (int) env('CHANNEL_RETRY_DELAY_SECONDS', 30),
];
