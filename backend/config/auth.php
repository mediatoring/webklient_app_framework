<?php

declare(strict_types=1);

$env = fn(string $key, mixed $default = null) => getenv($key) ?: $default;

return [
    'jwt' => [
        'secret' => $env('JWT_SECRET', ''),
        'access_ttl' => (int) $env('JWT_ACCESS_TTL', 900),       // 15 minutes
        'refresh_ttl' => (int) $env('JWT_REFRESH_TTL', 2592000), // 30 days
        'algo' => $env('JWT_ALGO', 'HS256'),
    ],
    'password' => [
        'bcrypt_rounds' => (int) $env('BCRYPT_ROUNDS', 12),
        'min_length' => 8,
    ],
    'lockout' => [
        'max_attempts' => (int) $env('LOGIN_MAX_ATTEMPTS', 5),
        'lockout_minutes' => (int) $env('LOGIN_LOCKOUT_MINUTES', 15),
    ],
];
