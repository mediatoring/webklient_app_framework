<?php

declare(strict_types=1);

$env = fn(string $key, mixed $default = null) => getenv($key) ?: $default;

return [
    'public' => (int) $env('RATE_LIMIT_PUBLIC', 100),
    'authenticated' => (int) $env('RATE_LIMIT_AUTHENTICATED', 1000),
    'ai' => (int) $env('RATE_LIMIT_AI', 100),
    'admin' => (int) $env('RATE_LIMIT_ADMIN', 500),
    'window' => (int) $env('RATE_LIMIT_WINDOW', 3600), // seconds
];
