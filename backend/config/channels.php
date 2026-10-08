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
        'ircub-docker-callback-secret-change-me',
        'change-me-to-a-long-random-secret-32chars',
        'local-dev-only-change-me-32chars-min!!',
        'changeme',
        'secret',
        '',
    ],
    'local_currency' => env('CHANNEL_LOCAL_CURRENCY', 'SOS'),
    'max_retries' => (int) env('CHANNEL_MAX_RETRIES', 3),
    'retry_delay_seconds' => (int) env('CHANNEL_RETRY_DELAY_SECONDS', 30),
    /*
    | Client-driven simulate=SUCCESS|FAILED is local/testing only.
    | AppServiceProvider forces this off outside those environments.
    */
    'allow_simulate' => (bool) env(
        'CHANNEL_ALLOW_SIMULATE',
        in_array(env('APP_ENV', 'production'), ['local', 'testing'], true)
    ),
    /*
    | In-process MockChannelClient. Production/staging fail closed unless
    | CHANNEL_ALLOW_MOCK=true is set explicitly (POC acknowledgment).
    */
    'allow_mock_providers' => (bool) env(
        'CHANNEL_ALLOW_MOCK',
        in_array(env('APP_ENV', 'production'), ['local', 'testing'], true)
    ),
];
