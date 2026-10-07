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
        'description' => 'Min 10 chars with upper, lower, number, and symbol (e.g. Password@123).',
    ],

    /*
    | Optional 2FA stub — when enabled on a user, login requires otp.
    | Demo OTP for the POC mock challenge.
    */
    'two_factor' => [
        'enabled_globally' => true,
        'demo_otp' => env('IRCUB_DEMO_OTP', '123456'),
    ],
];
