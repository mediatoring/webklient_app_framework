<?php

declare(strict_types=1);

namespace WebklientApp\Core;

class ConfigLoader
{
    private static ?ConfigLoader $instance = null;
    private array $env = [];
    private array $config = [];

    private function __construct(string $envPath)
    {
        $this->loadEnv($envPath);
    }

    public static function getInstance(?string $envPath = null): self
    {
        if (self::$instance === null) {
            $path = $envPath ?? dirname(__DIR__) . '/.env';
            self::$instance = new self($path);
        }
        return self::$instance;
    }

    public static function reset(): void
    {
        self::$instance = null;
    }

    private function loadEnv(string $path): void
    {
        if (!file_exists($path)) {
            throw new \RuntimeException("Environment file not found: {$path}");
        }

        $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '' || str_starts_with($line, '#')) {
                continue;
            }

            $pos = strpos($line, '=');
            if ($pos === false) {
                continue;
            }

            $key = trim(substr($line, 0, $pos));
            $value = trim(substr($line, $pos + 1));

            // Remove surrounding quotes
            if (
                (str_starts_with($value, '"') && str_ends_with($value, '"')) ||
                (str_starts_with($value, "'") && str_ends_with($value, "'"))
            ) {
                $value = substr($value, 1, -1);
            }

            $this->env[$key] = $value;

            // Set in environment if not already set
            if (getenv($key) === false) {
                putenv("{$key}={$value}");
            }
        }
    }

    public function env(string $key, mixed $default = null): mixed
    {
        return $this->env[$key] ?? $default;
    }

    public function get(string $key, mixed $default = null): mixed
    {
        $keys = explode('.', $key);
        $value = $this->config;

        foreach ($keys as $segment) {
            if (!is_array($value) || !array_key_exists($segment, $value)) {
                return $default;
            }
            $value = $value[$segment];
        }

        return $value;
    }

    public function loadConfigFile(string $name, string $path): void
    {
        if (file_exists($path)) {
            $this->config[$name] = require $path;
        }
    }

    public function loadConfigDirectory(string $directory): void
    {
        if (!is_dir($directory)) {
            return;
        }

        $files = glob($directory . '/*.php');
        foreach ($files as $file) {
            $name = basename($file, '.php');
            $this->config[$name] = require $file;
        }
    }

    public function all(): array
    {
        return $this->config;
    }

    public function envAll(): array
    {
        return $this->env;
    }

    public function set(string $key, mixed $value): void
    {
        $keys = explode('.', $key);
        $config = &$this->config;

        foreach (array_slice($keys, 0, -1) as $segment) {
            if (!isset($config[$segment]) || !is_array($config[$segment])) {
                $config[$segment] = [];
            }
            $config = &$config[$segment];
        }

        $config[end($keys)] = $value;
    }

    public function require(string ...$keys): void
    {
        $missing = [];
        foreach ($keys as $key) {
            if (!isset($this->env[$key]) || $this->env[$key] === '') {
                $missing[] = $key;
            }
        }

        if (!empty($missing)) {
            throw new \RuntimeException(
                'Missing required environment variables: ' . implode(', ', $missing)
            );
        }
    }

    public function isProduction(): bool
    {
        return $this->env('APP_ENV', 'production') === 'production';
    }

    public function isDebug(): bool
    {
        return filter_var($this->env('APP_DEBUG', false), FILTER_VALIDATE_BOOLEAN);
    }
}
