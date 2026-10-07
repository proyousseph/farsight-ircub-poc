<?php

return [
    /*
    | Password policy for create/update user (and documented for login UX).
    */
    'password_policy' => [
        'min_length' => 10,
        'require_uppercase' => true,
        'require_lowercase' => true,
        'require_number' => true,
        'require_symbol' => true,
        'description' => 'Min 10 chars with upper, lower, number, and symbol.',
    ],

    /*
    | Optional 2FA stub — only for local/testing POC. Production must use a real provider.
    */
    'two_factor' => [
        'enabled_globally' => (bool) env('IRCUB_2FA_ENABLED', true),
        'demo_otp' => env('IRCUB_DEMO_OTP', env('APP_ENV', 'production') === 'local' || env('APP_ENV') === 'testing' ? '123456' : null),
        'allow_stub' => (bool) env(
            'IRCUB_2FA_ALLOW_STUB',
            in_array(env('APP_ENV', 'production'), ['local', 'testing'], true)
        ),
    ],

    /*
    | OpenAPI / Swagger exposure (disabled outside local/testing unless explicitly enabled).
    */
    'docs_enabled' => (bool) env(
        'IRCUB_DOCS_ENABLED',
        in_array(env('APP_ENV', 'production'), ['local', 'testing'], true)
    ),
];
