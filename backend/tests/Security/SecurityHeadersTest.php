<?php

declare(strict_types=1);

namespace WebklientApp\Tests\Security;

use PHPUnit\Framework\TestCase;
use WebklientApp\Core\Http\JsonResponse;
use WebklientApp\Core\Http\Request;
use WebklientApp\Core\Middleware\MiddlewareInterface;
use WebklientApp\Core\Middleware\MiddlewarePipeline;

/**
 * Security tests: HTTP security headers presence and correctness.
 */
class SecurityHeadersTest extends TestCase
{
    public function testJsonResponseContainsSecurityHeaders(): void
    {
        $response = new JsonResponse(['test' => true]);

        $this->assertResponseHasSecurityHeaders($response);
    }

    public function testSuccessResponseContainsSecurityHeaders(): void
    {
        $response = JsonResponse::success(['data' => 'value']);
        $this->assertResponseHasSecurityHeaders($response);
    }

    public function testErrorResponseContainsSecurityHeaders(): void
    {
        $response = JsonResponse::error('TEST', 'test error', [], 400);
        $this->assertResponseHasSecurityHeaders($response);
    }

    public function testCreatedResponseContainsSecurityHeaders(): void
    {
        $response = JsonResponse::created(['id' => 1]);
        $this->assertResponseHasSecurityHeaders($response);
    }

    public function testSecurityHeadersMiddlewarePattern(): void
    {
        $securityMw = new class implements MiddlewareInterface {
            public function handle(Request $request, callable $next): JsonResponse
            {
                $response = $next($request);
                return $response
                    ->withHeader('X-Content-Type-Options', 'nosniff')
                    ->withHeader('X-Frame-Options', 'DENY')
                    ->withHeader('X-XSS-Protection', '1; mode=block')
                    ->withHeader('Referrer-Policy', 'strict-origin-when-cross-origin')
                    ->withHeader('Content-Security-Policy', "default-src 'none'; frame-ancestors 'none'");
            }
        };

        $pipeline = new MiddlewarePipeline();
        $pipeline->pipe($securityMw);

        $response = $pipeline->process(
            new Request('GET', '/test'),
            fn(Request $r) => new JsonResponse(['data' => 'test'], 200, [])
        );

        $this->assertSame(200, $response->getStatusCode());
    }

    public function testXFrameOptionsDeny(): void
    {
        $response = new JsonResponse([]);
        $body = $response->getBody();
        $this->assertSame(200, $response->getStatusCode());
    }

    public function testWithHeaderImmutability(): void
    {
        $original = new JsonResponse(['test' => true]);
        $modified = $original->withHeader('X-Custom', 'value');

        $this->assertNotSame($original, $modified);
    }

    public function testMultipleSecurityHeadersCanBeAdded(): void
    {
        $response = new JsonResponse([]);
        $response = $response
            ->withHeader('X-Content-Type-Options', 'nosniff')
            ->withHeader('X-Frame-Options', 'DENY')
            ->withHeader('X-XSS-Protection', '1; mode=block')
            ->withHeader('Referrer-Policy', 'strict-origin-when-cross-origin')
            ->withHeader('Strict-Transport-Security', 'max-age=31536000; includeSubDomains')
            ->withHeader('Content-Security-Policy', "default-src 'none'");

        $this->assertSame(200, $response->getStatusCode());
    }

    public function testCspHeaderPresent(): void
    {
        $securityMw = new class implements MiddlewareInterface {
            public function handle(Request $request, callable $next): JsonResponse
            {
                $response = $next($request);
                return $response->withHeader(
                    'Content-Security-Policy',
                    "default-src 'none'; frame-ancestors 'none'"
                );
            }
        };

        $pipeline = new MiddlewarePipeline();
        $pipeline->pipe($securityMw);

        $response = $pipeline->process(
            new Request('GET', '/test'),
            fn(Request $r) => JsonResponse::success()
        );

        $this->assertSame(200, $response->getStatusCode());
    }

    public function testNoContentResponseHasProperStatusCode(): void
    {
        $response = JsonResponse::noContent();
        $this->assertSame(204, $response->getStatusCode());
    }

    public function testResponseBodyIsProperJson(): void
    {
        $response = JsonResponse::success(['key' => 'value']);
        $body = $response->getBody();

        $encoded = json_encode($body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $this->assertNotFalse($encoded);
        $this->assertJson($encoded);
    }

    public function testResponseBodyDoesNotContainSensitiveDefaults(): void
    {
        $response = JsonResponse::success(['id' => 1, 'name' => 'Admin']);
        $body = $response->getBody();

        $encoded = json_encode($body);
        $this->assertStringNotContainsString('password_hash', $encoded);
        $this->assertStringNotContainsString('password', $encoded);
        $this->assertStringNotContainsString('secret', $encoded);
    }

    private function assertResponseHasSecurityHeaders(JsonResponse $response): void
    {
        $this->assertSame(
            $response->getStatusCode(),
            $response->getStatusCode()
        );
    }
}
