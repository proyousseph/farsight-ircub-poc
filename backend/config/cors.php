<?php

$frontend = env('FRONTEND_URL', 'http://localhost:5173');
$localOrigins = [
    'http://localhost:5173',
    'http://127.0.0.1:5173',
    'http://localhost:5174',
    'http://127.0.0.1:5174',
];

$allowed = array_filter([$frontend]);

// Only widen localhost CORS in local/testing — not when FRONTEND_URL is a production host.
if (in_array(env('APP_ENV', 'production'), ['local', 'testing'], true)) {
    $allowed = array_values(array_unique(array_merge($allowed, $localOrigins)));
}

return [
    'paths' => ['api/*', 'sanctum/csrf-cookie'],
    'allowed_methods' => ['*'],
    'allowed_origins' => $allowed,
    'allowed_origins_patterns' => [],
    'allowed_headers' => ['*'],
    'exposed_headers' => [],
    'max_age' => 0,
    'supports_credentials' => true,
];
