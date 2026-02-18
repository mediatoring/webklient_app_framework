<?php

declare(strict_types=1);

$config = WebklientApp\Core\ConfigLoader::getInstance();

return [
    'host' => $config->env('MAIL_HOST', 'localhost'),
    'port' => (int) $config->env('MAIL_PORT', 587),
    'encryption' => $config->env('MAIL_ENCRYPTION', 'tls'),
    'username' => $config->env('MAIL_USERNAME', ''),
    'password' => $config->env('MAIL_PASSWORD', ''),
    'from_address' => $config->env('MAIL_FROM_ADDRESS', 'noreply@localhost'),
    'from_name' => $config->env('MAIL_FROM_NAME', $config->env('APP_NAME', 'WebklientApp')),
    'timeout' => (int) $config->env('MAIL_TIMEOUT', 30),
];
