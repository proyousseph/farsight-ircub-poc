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
    | 2FA: TOTP is the production path. Stub OTP is local/testing fallback only
    | for users who have two_factor_enabled but have not confirmed a TOTP secret.
    */
    'two_factor' => [
        'enabled_globally' => (bool) env('IRCUB_2FA_ENABLED', true),
        'demo_otp' => env('IRCUB_DEMO_OTP', env('APP_ENV', 'production') === 'local' || env('APP_ENV') === 'testing' ? '123456' : null),
        'allow_stub' => (bool) env(
            'IRCUB_2FA_ALLOW_STUB',
            in_array(env('APP_ENV', 'production'), ['local', 'testing'], true)
        ),
    ],

    'auth_cookie' => [
        'name' => 'ircub_token',
        // lax (default) | strict | none — none requires HTTPS and increases CSRF risk.
        'same_site' => env('IRCUB_AUTH_COOKIE_SAMESITE', 'lax'),
    ],

    'payments' => [
        'max_unlinked_amount' => (float) env('IRCUB_MAX_UNLINKED_PAYMENT', 100000),
    ],

    /*
    | OpenAPI / Swagger exposure (disabled outside local/testing unless explicitly enabled).
    */
    'docs_enabled' => (bool) env(
        'IRCUB_DOCS_ENABLED',
        in_array(env('APP_ENV', 'production'), ['local', 'testing'], true)
    ),
];
