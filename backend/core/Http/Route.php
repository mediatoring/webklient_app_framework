<?php

declare(strict_types=1);

namespace WebklientApp\Core\Http;

class Route
{
    private string $method;
    private string $pattern;
    private mixed $handler;
    private array $middleware = [];
    private array $permissions = [];
    private string $name = '';
    private string $rateGroup = 'authenticated';

    /** @var string[] Parameter names extracted from pattern */
    private array $paramNames = [];

    /** @var string Compiled regex */
    private string $regex;

    public function __construct(string $method, string $pattern, mixed $handler)
    {
        $this->method = strtoupper($method);
        $this->pattern = $pattern;
        $this->handler = $handler;
        $this->compile();
    }

    private function compile(): void
    {
        $regex = preg_replace_callback('/\{(\w+)\}/', function ($matches) {
            $this->paramNames[] = $matches[1];
            return '([^/]+)';
        }, $this->pattern);

        $this->regex = '#^' . $regex . '$#';
    }

    public function matches(string $method, string $path): ?array
    {
        if ($this->method !== $method && $this->method !== 'ANY') {
            return null;
        }

        if (preg_match($this->regex, $path, $matches)) {
            array_shift($matches);
            return array_combine($this->paramNames, $matches) ?: [];
        }

        return null;
    }

    public function middleware(string ...$middleware): self
    {
        $this->middleware = array_merge($this->middleware, $middleware);
        return $this;
    }

    public function permission(string ...$permissions): self
    {
        $this->permissions = array_merge($this->permissions, $permissions);
        return $this;
    }

    public function name(string $name): self
    {
        $this->name = $name;
        return $this;
    }

    public function rateGroup(string $group): self
    {
        $this->rateGroup = $group;
        return $this;
    }

    public function getMethod(): string
    {
        return $this->method;
    }

    public function getPattern(): string
    {
        return $this->pattern;
    }

    public function getHandler(): mixed
    {
        return $this->handler;
    }

    public function getMiddleware(): array
    {
        return $this->middleware;
    }

    public function getPermissions(): array
    {
        return $this->permissions;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getRateGroup(): string
    {
        return $this->rateGroup;
    }
}
