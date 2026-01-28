<?php

declare(strict_types=1);

$env = fn(string $key, mixed $default = null) => getenv($key) ?: $default;

return [
    'driver' => $env('STORAGE_DRIVER', 'local'),
    'local' => [
        'path' => $env('STORAGE_PATH', 'storage/uploads'),
    ],
    'max_upload_size' => (int) $env('MAX_UPLOAD_SIZE', 10485760), // 10MB
    'allowed_types' => [
        'image/jpeg', 'image/png', 'image/gif', 'image/webp',
        'application/pdf',
        'text/plain', 'text/csv',
        'application/json',
        'application/zip',
    ],
];
