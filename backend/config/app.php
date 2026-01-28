<?php

declare(strict_types=1);

$env = fn(string $key, mixed $default = null) => getenv($key) ?: $default;

return [
    'name' => $env('APP_NAME', 'WebklientApp'),
    'env' => $env('APP_ENV', 'production'),
    'debug' => filter_var($env('APP_DEBUG', false), FILTER_VALIDATE_BOOLEAN),
    'url' => $env('APP_URL', 'http://localhost:8080'),
    'key' => $env('APP_KEY', ''),
    'timezone' => 'UTC',
    'version' => '1.0.0',
];
