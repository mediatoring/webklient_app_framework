<?php

declare(strict_types=1);

$env = fn(string $key, mixed $default = null) => getenv($key) ?: $default;

return [
    'default_provider' => $env('AI_DEFAULT_PROVIDER', 'openai'),
    'providers' => [
        'openai' => [
            'api_key' => $env('OPENAI_API_KEY', ''),
            'default_model' => $env('OPENAI_DEFAULT_MODEL', 'gpt-4'),
            'base_url' => 'https://api.openai.com/v1',
        ],
        'anthropic' => [
            'api_key' => $env('ANTHROPIC_API_KEY', ''),
            'default_model' => $env('ANTHROPIC_DEFAULT_MODEL', 'claude-sonnet-4-20250514'),
            'base_url' => 'https://api.anthropic.com/v1',
        ],
    ],
];
