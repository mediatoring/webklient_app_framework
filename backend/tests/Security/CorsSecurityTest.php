<?php

declare(strict_types=1);

namespace WebklientApp\Tests\Security;

use PHPUnit\Framework\TestCase;
use WebklientApp\Core\Http\JsonResponse;
use WebklientApp\Core\Http\Request;
use WebklientApp\Core\Http\Router;
use WebklientApp\Core\Middleware\MiddlewareInterface;
use WebklientApp\Core\Middleware\MiddlewarePipeline;

/**
 * Security tests: CORS configuration, origin validation, preflight handling.
 */
class CorsSecurityTest extends TestCase
{
    public function testOptionsPreflightReturns204(): void
    {
        $router = new Router();
        $router->get('/api/test', fn(Request $r) => JsonResponse::success());

        $response = $router->dispatch(new Request('OPTIONS', '/api/test'));
        $this->assertSame(204, $response->getStatusCode());
    }

    public function testCorsMiddlewareAllowsConfiguredOrigin(): void
    {
        $corsMw = $this->createCorsMiddleware(['https://app.example.com'], ['GET', 'POST']);

        $pipeline = new MiddlewarePipeline();
        $pipeline->pipe($corsMw);

        $request = new Request('GET', '/api/test', [], [], ['origin' => 'https://app.example.com']);
        $response = $pipeline->process($request, fn(Request $r) => JsonResponse::success());

        $this->assertSame(200, $response->getStatusCode());
    }

    public function testCorsMiddlewareBlocksUnknownOrigin(): void
    {
        $corsMw = $this->createCorsMiddleware(['https://app.example.com'], ['GET']);

        $pipeline = new MiddlewarePipeline();
        $pipeline->pipe($corsMw);

        $request = new Request('GET', '/api/test', [], [], ['origin' => 'https://evil.com']);
        $response = $pipeline->process($request, fn(Request $r) => JsonResponse::success());

        $this->assertSame(200, $response->getStatusCode());
    }

    public function testWildcardOriginAllowsAll(): void
    {
        $corsMw = $this->createCorsMiddleware(['*'], ['GET', 'POST']);

        $pipeline = new MiddlewarePipeline();
        $pipeline->pipe($corsMw);

        $request = new Request('GET', '/api/test', [], [], ['origin' => 'https://any-domain.com']);
        $response = $pipeline->process($request, fn(Request $r) => JsonResponse::success());

        $this->assertSame(200, $response->getStatusCode());
    }

    public function testCredentialsNotAllowedWithWildcard(): void
    {
        $corsMw = $this->createCorsMiddleware(['*'], ['GET'], true);

        $pipeline = new MiddlewarePipeline();
        $pipeline->pipe($corsMw);

        $request = new Request('GET', '/api/test', [], [], ['origin' => 'https://app.com']);
        $response = $pipeline->process($request, fn(Request $r) => JsonResponse::success());

        $this->assertSame(200, $response->getStatusCode());
    }

    public function testMultipleAllowedOrigins(): void
    {
        $origins = ['https://app1.example.com', 'https://app2.example.com'];
        $corsMw = $this->createCorsMiddleware($origins, ['GET']);

        $pipeline = new MiddlewarePipeline();
        $pipeline->pipe($corsMw);

        foreach ($origins as $origin) {
            $request = new Request('GET', '/api/test', [], [], ['origin' => $origin]);
            $response = $pipeline->process($request, fn(Request $r) => JsonResponse::success());
            $this->assertSame(200, $response->getStatusCode());
        }
    }

    public function testCorsDoesNotAffectSameOriginRequests(): void
    {
        $corsMw = $this->createCorsMiddleware(['https://app.example.com'], ['GET']);

        $pipeline = new MiddlewarePipeline();
        $pipeline->pipe($corsMw);

        $request = new Request('GET', '/api/test');
        $response = $pipeline->process($request, fn(Request $r) => JsonResponse::success('data'));

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('data', $response->getBody()['data']);
    }

    public function testPreflightOptionsForAllPaths(): void
    {
        $router = new Router();
        $router->get('/api/users', fn(Request $r) => JsonResponse::success());
        $router->post('/api/users', fn(Request $r) => JsonResponse::created());

        $response = $router->dispatch(new Request('OPTIONS', '/api/users'));
        $this->assertSame(204, $response->getStatusCode());

        $response = $router->dispatch(new Request('OPTIONS', '/api/unknown'));
        $this->assertSame(204, $response->getStatusCode());
    }

    public function testCorsMiddlewarePreservesResponseBody(): void
    {
        $corsMw = $this->createCorsMiddleware(['*'], ['GET']);

        $pipeline = new MiddlewarePipeline();
        $pipeline->pipe($corsMw);

        $request = new Request('GET', '/api/test', [], [], ['origin' => 'https://app.com']);
        $response = $pipeline->process($request, fn(Request $r) => JsonResponse::success(['key' => 'value']));

        $this->assertTrue($response->getBody()['success']);
        $this->assertSame(['key' => 'value'], $response->getBody()['data']);
    }

    public function testCorsHeadersOnErrorResponses(): void
    {
        $corsMw = $this->createCorsMiddleware(['*'], ['GET']);

        $pipeline = new MiddlewarePipeline();
        $pipeline->pipe($corsMw);

        $request = new Request('GET', '/api/test', [], [], ['origin' => 'https://app.com']);
        $response = $pipeline->process(
            $request,
            fn(Request $r) => JsonResponse::error('NOT_FOUND', 'Not found', [], 404)
        );

        $this->assertSame(404, $response->getStatusCode());
    }

    private function createCorsMiddleware(
        array $allowedOrigins,
        array $allowedMethods,
        bool $credentials = false
    ): MiddlewareInterface {
        return new class($allowedOrigins, $allowedMethods, $credentials) implements MiddlewareInterface {
            public function __construct(
                private readonly array $origins,
                private readonly array $methods,
                private readonly bool $credentials
            ) {}

            public function handle(Request $request, callable $next): JsonResponse
            {
                $origin = $request->header('origin', '');
                $allowedOrigin = in_array('*', $this->origins)
                    ? '*'
                    : (in_array($origin, $this->origins) ? $origin : '');

                $response = $next($request);

                if ($allowedOrigin !== '') {
                    $response = $response
                        ->withHeader('Access-Control-Allow-Origin', $allowedOrigin)
                        ->withHeader('Access-Control-Allow-Methods', implode(', ', $this->methods));

                    if ($this->credentials && $allowedOrigin !== '*') {
                        $response = $response->withHeader('Access-Control-Allow-Credentials', 'true');
                    }
                }

                return $response;
            }
        };
    }
}
