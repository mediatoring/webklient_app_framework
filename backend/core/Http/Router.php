<?php

declare(strict_types=1);

namespace WebklientApp\Core\Http;

use WebklientApp\Core\Exceptions\NotFoundException;
use WebklientApp\Core\Middleware\MiddlewareInterface;
use WebklientApp\Core\Middleware\MiddlewarePipeline;

class Router
{
    /** @var Route[] */
    private array $routes = [];

    /** @var array<string, class-string<MiddlewareInterface>> Named middleware aliases */
    private array $middlewareAliases = [];

    /** @var MiddlewareInterface[] Global middleware applied to every route */
    private array $globalMiddleware = [];

    private string $prefix = '';
    private array $groupMiddleware = [];

    public function get(string $pattern, mixed $handler): Route
    {
        return $this->addRoute('GET', $pattern, $handler);
    }

    public function post(string $pattern, mixed $handler): Route
    {
        return $this->addRoute('POST', $pattern, $handler);
    }

    public function put(string $pattern, mixed $handler): Route
    {
        return $this->addRoute('PUT', $pattern, $handler);
    }

    public function patch(string $pattern, mixed $handler): Route
    {
        return $this->addRoute('PATCH', $pattern, $handler);
    }

    public function delete(string $pattern, mixed $handler): Route
    {
        return $this->addRoute('DELETE', $pattern, $handler);
    }

    public function options(string $pattern, mixed $handler): Route
    {
        return $this->addRoute('OPTIONS', $pattern, $handler);
    }

    public function group(string $prefix, array $middleware, callable $callback): void
    {
        $previousPrefix = $this->prefix;
        $previousMiddleware = $this->groupMiddleware;

        $this->prefix = $previousPrefix . $prefix;
        $this->groupMiddleware = array_merge($previousMiddleware, $middleware);

        $callback($this);

        $this->prefix = $previousPrefix;
        $this->groupMiddleware = $previousMiddleware;
    }

    public function aliasMiddleware(string $name, string $class): void
    {
        $this->middlewareAliases[$name] = $class;
    }

    public function pushGlobalMiddleware(MiddlewareInterface $middleware): void
    {
        $this->globalMiddleware[] = $middleware;
    }

    public function dispatch(Request $request): JsonResponse
    {
        $method = $request->method();
        $path = $request->path();

        // Handle OPTIONS preflight for any path
        if ($method === 'OPTIONS') {
            return new JsonResponse([], 204);
        }

        foreach ($this->routes as $route) {
            $params = $route->matches($method, $path);
            if ($params !== null) {
                $request->setRouteParams($params);
                return $this->executeRoute($route, $request);
            }
        }

        throw new NotFoundException("Route not found: {$method} {$path}");
    }

    private function addRoute(string $method, string $pattern, mixed $handler): Route
    {
        $fullPattern = $this->prefix . $pattern;
        $route = new Route($method, $fullPattern, $handler);

        if (!empty($this->groupMiddleware)) {
            $route->middleware(...$this->groupMiddleware);
        }

        $this->routes[] = $route;
        return $route;
    }

    private function executeRoute(Route $route, Request $request): JsonResponse
    {
        $pipeline = new MiddlewarePipeline();

        // Global middleware first
        foreach ($this->globalMiddleware as $mw) {
            $pipeline->pipe($mw);
        }

        // Route-specific middleware
        foreach ($route->getMiddleware() as $name) {
            if (isset($this->middlewareAliases[$name])) {
                $class = $this->middlewareAliases[$name];
                $pipeline->pipe(new $class());
            } elseif ($name instanceof MiddlewareInterface) {
                $pipeline->pipe($name);
            }
        }

        // Store permissions on request for PermissionMiddleware
        if (!empty($route->getPermissions())) {
            $request->setAttribute('required_permissions', $route->getPermissions());
        }
        $request->setAttribute('rate_group', $route->getRateGroup());

        return $pipeline->process($request, function (Request $req) use ($route) {
            return $this->callHandler($route->getHandler(), $req);
        });
    }

    private function callHandler(mixed $handler, Request $request): JsonResponse
    {
        // [Controller::class, 'method']
        if (is_array($handler) && count($handler) === 2) {
            [$class, $method] = $handler;
            $controller = is_string($class) ? new $class() : $class;
            $result = $controller->$method($request);
        } elseif (is_callable($handler)) {
            $result = $handler($request);
        } else {
            throw new \RuntimeException('Invalid route handler');
        }

        if ($result instanceof JsonResponse) {
            return $result;
        }

        return JsonResponse::success($result);
    }

    public function getRoutes(): array
    {
        return $this->routes;
    }
}
