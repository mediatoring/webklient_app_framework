<?php

declare(strict_types=1);

$env = fn(string $key, mixed $default = null) => getenv($key) ?: $default;

return [
    'allowed_origins' => explode(',', $env('CORS_ALLOWED_ORIGINS', '*')),
    'allowed_methods' => explode(',', $env('CORS_ALLOWED_METHODS', 'GET,POST,PUT,PATCH,DELETE,OPTIONS')),
    'allowed_headers' => explode(',', $env('CORS_ALLOWED_HEADERS', 'Content-Type,Authorization,X-Requested-With')),
    'allow_credentials' => true,
    'max_age' => (int) $env('CORS_MAX_AGE', 86400),
];
