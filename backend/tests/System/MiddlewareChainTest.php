<?php

declare(strict_types=1);

namespace WebklientApp\Tests\System;

use PHPUnit\Framework\TestCase;
use WebklientApp\Core\Http\JsonResponse;
use WebklientApp\Core\Http\Request;
use WebklientApp\Core\Http\Router;
use WebklientApp\Core\Middleware\MiddlewareInterface;
use WebklientApp\Core\Middleware\MiddlewarePipeline;

/**
 * System tests: Middleware chain scenarios (auth + permission + logging patterns).
 */
class MiddlewareChainTest extends TestCase
{
    public function testAuthThenPermissionChain(): void
    {
        $pipeline = new MiddlewarePipeline();

        $authMw = new class implements MiddlewareInterface {
            public function handle(Request $request, callable $next): JsonResponse
            {
                $token = $request->bearerToken();
                if (!$token) {
                    return JsonResponse::error('AUTH', 'Not authenticated', [], 401);
                }
                $request->setAttribute('user_id', 1);
                $request->setAttribute('user', ['id' => 1, 'role' => 'admin']);
                return $next($request);
            }
        };

        $permMw = new class implements MiddlewareInterface {
            public function handle(Request $request, callable $next): JsonResponse
            {
                $user = $request->getAttribute('user');
                if (!$user || $user['role'] !== 'admin') {
                    return JsonResponse::error('FORBIDDEN', 'Insufficient permissions', [], 403);
                }
                return $next($request);
            }
        };

        $pipeline->pipe($authMw)->pipe($permMw);

        $request = new Request('GET', '/admin', [], [], ['authorization' => 'Bearer valid']);
        $response = $pipeline->process($request, fn(Request $r) => JsonResponse::success('admin-panel'));

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('admin-panel', $response->getBody()['data']);
    }

    public function testAuthFailsBeforePermission(): void
    {
        $pipeline = new MiddlewarePipeline();

        $authMw = new class implements MiddlewareInterface {
            public function handle(Request $request, callable $next): JsonResponse
            {
                if (!$request->bearerToken()) {
                    return JsonResponse::error('AUTH', 'Not authenticated', [], 401);
                }
                return $next($request);
            }
        };

        $permMw = new class implements MiddlewareInterface {
            public function handle(Request $request, callable $next): JsonResponse
            {
                $user = $request->getAttribute('user');
                if (!$user) {
                    return JsonResponse::error('FORBIDDEN', 'No user', [], 403);
                }
                return $next($request);
            }
        };

        $pipeline->pipe($authMw)->pipe($permMw);

        $request = new Request('GET', '/admin');
        $response = $pipeline->process($request, fn(Request $r) => JsonResponse::success('secret'));

        $this->assertSame(401, $response->getStatusCode());
    }

    public function testResponseModifyingMiddlewareChain(): void
    {
        $pipeline = new MiddlewarePipeline();

        $corsMw = new class implements MiddlewareInterface {
            public function handle(Request $request, callable $next): JsonResponse
            {
                $response = $next($request);
                return $response->withHeader('Access-Control-Allow-Origin', '*');
            }
        };

        $securityMw = new class implements MiddlewareInterface {
            public function handle(Request $request, callable $next): JsonResponse
            {
                $response = $next($request);
                return $response
                    ->withHeader('X-Content-Type-Options', 'nosniff')
                    ->withHeader('X-Frame-Options', 'DENY');
            }
        };

        $pipeline->pipe($corsMw)->pipe($securityMw);

        $response = $pipeline->process(
            new Request('GET', '/test'),
            fn(Request $r) => JsonResponse::success('data')
        );

        $this->assertSame(200, $response->getStatusCode());
    }

    public function testLoggingMiddlewareRecordsTimingInfo(): void
    {
        $log = new \ArrayObject();
        $pipeline = new MiddlewarePipeline();

        $logMw = new class($log) implements MiddlewareInterface {
            public function __construct(private readonly \ArrayObject $log) {}

            public function handle(Request $request, callable $next): JsonResponse
            {
                $start = microtime(true);
                $response = $next($request);
                $this->log[] = [
                    'method' => $request->method(),
                    'path' => $request->path(),
                    'status' => $response->getStatusCode(),
                    'duration_ms' => round((microtime(true) - $start) * 1000, 2),
                ];
                return $response;
            }
        };

        $pipeline->pipe($logMw);

        $pipeline->process(
            new Request('GET', '/api/test'),
            fn(Request $r) => JsonResponse::success('ok')
        );

        $this->assertCount(1, $log);
        $this->assertSame('GET', $log[0]['method']);
        $this->assertSame('/api/test', $log[0]['path']);
        $this->assertSame(200, $log[0]['status']);
        $this->assertIsFloat($log[0]['duration_ms']);
    }

    public function testRateLimitPatternMiddleware(): void
    {
        $counter = new \ArrayObject(['count' => 0]);
        $maxRequests = 3;

        $rateLimitMw = new class($counter, $maxRequests) implements MiddlewareInterface {
            public function __construct(
                private readonly \ArrayObject $counter,
                private readonly int $max
            ) {}

            public function handle(Request $request, callable $next): JsonResponse
            {
                $this->counter['count']++;
                if ($this->counter['count'] > $this->max) {
                    return JsonResponse::error('RATE_LIMIT', 'Too many requests', [], 429);
                }
                $response = $next($request);
                return $response
                    ->withHeader('X-RateLimit-Limit', (string)$this->max)
                    ->withHeader('X-RateLimit-Remaining', (string)max(0, $this->max - $this->counter['count']));
            }
        };

        $pipeline = new MiddlewarePipeline();
        $pipeline->pipe($rateLimitMw);

        for ($i = 0; $i < 3; $i++) {
            $response = $pipeline->process(
                new Request('GET', '/api/data'),
                fn(Request $r) => JsonResponse::success('ok')
            );
            $this->assertSame(200, $response->getStatusCode());
        }

        $response = $pipeline->process(
            new Request('GET', '/api/data'),
            fn(Request $r) => JsonResponse::success('ok')
        );
        $this->assertSame(429, $response->getStatusCode());
    }

    public function testMiddlewareExceptionPropagation(): void
    {
        $pipeline = new MiddlewarePipeline();

        $throwingMw = new class implements MiddlewareInterface {
            public function handle(Request $request, callable $next): JsonResponse
            {
                throw new \WebklientApp\Core\Exceptions\AuthenticationException('Token expired');
            }
        };

        $pipeline->pipe($throwingMw);

        $this->expectException(\WebklientApp\Core\Exceptions\AuthenticationException::class);
        $this->expectExceptionMessage('Token expired');

        $pipeline->process(
            new Request('GET', '/test'),
            fn(Request $r) => JsonResponse::success()
        );
    }

    public function testRouterWithGlobalAndRouteMiddleware(): void
    {
        $executionLog = new \ArrayObject();
        $router = new Router();

        $globalMw = new class($executionLog) implements MiddlewareInterface {
            public function __construct(private readonly \ArrayObject $log) {}
            public function handle(Request $request, callable $next): JsonResponse
            {
                $this->log[] = 'global';
                return $next($request);
            }
        };

        $router->pushGlobalMiddleware($globalMw);

        $router->get('/test', function (Request $r) use ($executionLog) {
            $executionLog[] = 'handler';
            return JsonResponse::success();
        });

        $router->dispatch(new Request('GET', '/test'));

        $arr = $executionLog->getArrayCopy();
        $this->assertSame('global', $arr[0]);
        $this->assertSame('handler', end($arr));
    }
}
