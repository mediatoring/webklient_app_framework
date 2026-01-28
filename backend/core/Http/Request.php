<?php

declare(strict_types=1);

namespace WebklientApp\Core\Http;

class Request
{
    private string $method;
    private string $uri;
    private string $path;
    private array $query;
    private array $body;
    private array $headers;
    private array $server;
    private array $files;
    private array $attributes = [];

    public function __construct(
        string $method,
        string $uri,
        array $query = [],
        array $body = [],
        array $headers = [],
        array $server = [],
        array $files = []
    ) {
        $this->method = strtoupper($method);
        $this->uri = $uri;
        $this->path = parse_url($uri, PHP_URL_PATH) ?: '/';
        $this->query = $query;
        $this->body = $body;
        $this->headers = $headers;
        $this->server = $server;
        $this->files = $files;
    }

    public static function capture(): self
    {
        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        $uri = $_SERVER['REQUEST_URI'] ?? '/';

        $headers = [];
        foreach ($_SERVER as $key => $value) {
            if (str_starts_with($key, 'HTTP_')) {
                $name = str_replace('_', '-', strtolower(substr($key, 5)));
                $headers[$name] = $value;
            }
        }
        if (isset($_SERVER['CONTENT_TYPE'])) {
            $headers['content-type'] = $_SERVER['CONTENT_TYPE'];
        }

        $body = [];
        $rawBody = file_get_contents('php://input');
        if ($rawBody !== '' && $rawBody !== false) {
            $contentType = $headers['content-type'] ?? '';
            if (str_contains($contentType, 'application/json')) {
                $decoded = json_decode($rawBody, true);
                if (is_array($decoded)) {
                    $body = $decoded;
                }
            } else {
                $body = $_POST;
            }
        }

        return new self($method, $uri, $_GET, $body, $headers, $_SERVER, $_FILES);
    }

    public function method(): string
    {
        return $this->method;
    }

    public function path(): string
    {
        return $this->path;
    }

    public function uri(): string
    {
        return $this->uri;
    }

    public function query(string $key = null, mixed $default = null): mixed
    {
        if ($key === null) {
            return $this->query;
        }
        return $this->query[$key] ?? $default;
    }

    /**
     * Alias for query() - get a query string parameter.
     */
    public function get(string $key, mixed $default = null): mixed
    {
        return $this->query[$key] ?? $default;
    }

    public function input(string $key = null, mixed $default = null): mixed
    {
        if ($key === null) {
            return $this->body;
        }
        return $this->body[$key] ?? $default;
    }

    public function all(): array
    {
        return array_merge($this->query, $this->body);
    }

    public function header(string $key, mixed $default = null): mixed
    {
        return $this->headers[strtolower($key)] ?? $default;
    }

    public function headers(): array
    {
        return $this->headers;
    }

    public function bearerToken(): ?string
    {
        $auth = $this->header('authorization', '');
        if (str_starts_with($auth, 'Bearer ')) {
            return substr($auth, 7);
        }
        return null;
    }

    public function ip(): string
    {
        return $this->server['HTTP_X_FORWARDED_FOR']
            ?? $this->server['HTTP_X_REAL_IP']
            ?? $this->server['REMOTE_ADDR']
            ?? '0.0.0.0';
    }

    public function userAgent(): string
    {
        return $this->server['HTTP_USER_AGENT'] ?? '';
    }

    public function isMethod(string $method): bool
    {
        return $this->method === strtoupper($method);
    }

    public function isMutation(): bool
    {
        return in_array($this->method, ['POST', 'PUT', 'PATCH', 'DELETE']);
    }

    public function file(string $key): ?array
    {
        return $this->files[$key] ?? null;
    }

    public function setAttribute(string $key, mixed $value): void
    {
        $this->attributes[$key] = $value;
    }

    public function getAttribute(string $key, mixed $default = null): mixed
    {
        return $this->attributes[$key] ?? $default;
    }

    public function user(): ?array
    {
        return $this->getAttribute('user');
    }

    public function setRouteParams(array $params): void
    {
        $this->setAttribute('route_params', $params);
    }

    public function param(string $key, mixed $default = null): mixed
    {
        $params = $this->getAttribute('route_params', []);
        return $params[$key] ?? $default;
    }
}
