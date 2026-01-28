<?php

declare(strict_types=1);

namespace WebklientApp\Core\Http;

class JsonResponse
{
    private array $body;
    private int $statusCode;
    private array $headers;

    public function __construct(array $body, int $statusCode = 200, array $headers = [])
    {
        $this->body = $body;
        $this->statusCode = $statusCode;
        $this->headers = array_merge([
            'Content-Type' => 'application/json; charset=utf-8',
            'X-Content-Type-Options' => 'nosniff',
            'X-Frame-Options' => 'DENY',
            'X-XSS-Protection' => '1; mode=block',
            'Referrer-Policy' => 'strict-origin-when-cross-origin',
        ], $headers);
    }

    public static function success(mixed $data = null, string $message = '', array $metadata = []): self
    {
        $body = ['success' => true, 'data' => $data];
        if ($message !== '') {
            $body['message'] = $message;
        }
        if (!empty($metadata)) {
            $body['metadata'] = $metadata;
        }
        return new self($body);
    }

    public static function created(mixed $data = null, string $location = '', string $message = 'Resource created.'): self
    {
        $body = ['success' => true, 'data' => $data, 'message' => $message];
        $headers = [];
        if ($location !== '') {
            $headers['Location'] = $location;
        }
        return new self($body, 201, $headers);
    }

    public static function noContent(): self
    {
        return new self([], 204);
    }

    public static function error(string $code, string $message, array $details = [], int $statusCode = 400): self
    {
        return new self([
            'success' => false,
            'error' => [
                'code' => $code,
                'message' => $message,
                'details' => $details,
            ],
        ], $statusCode);
    }

    public static function paginated(array $items, int $total, int $page, int $perPage, array $extra = []): self
    {
        $lastPage = max(1, (int) ceil($total / $perPage));
        return self::success($items, '', array_merge([
            'pagination' => [
                'total' => $total,
                'per_page' => $perPage,
                'current_page' => $page,
                'last_page' => $lastPage,
                'has_more' => $page < $lastPage,
            ],
        ], $extra));
    }

    public function withHeader(string $name, string $value): self
    {
        $clone = clone $this;
        $clone->headers[$name] = $value;
        return $clone;
    }

    public function getStatusCode(): int
    {
        return $this->statusCode;
    }

    public function getBody(): array
    {
        return $this->body;
    }

    public function send(): void
    {
        http_response_code($this->statusCode);

        foreach ($this->headers as $name => $value) {
            header("{$name}: {$value}");
        }

        if ($this->statusCode === 204) {
            return;
        }

        echo json_encode($this->body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
}
