<?php

declare(strict_types=1);

namespace WebklientApp\Tests\Security;

use PHPUnit\Framework\TestCase;
use WebklientApp\Core\Auth\JWTService;
use WebklientApp\Core\Http\JsonResponse;
use WebklientApp\Core\Http\Request;
use WebklientApp\Core\Http\Router;
use WebklientApp\Core\Middleware\MiddlewareInterface;
use WebklientApp\Core\Middleware\MiddlewarePipeline;
use WebklientApp\Core\Exceptions\AuthenticationException;

/**
 * Security tests: Authentication bypass attempts, token handling, authorization patterns.
 */
class AuthenticationFlowTest extends TestCase
{
    private JWTService $jwt;

    protected function setUp(): void
    {
        $this->jwt = new JWTService([
            'secret' => 'secure-test-secret-key-minimum-32-chars!',
            'access_ttl' => 900,
            'algo' => 'HS256',
        ]);
    }

    public function testRequestWithoutTokenIsRejected(): void
    {
        $pipeline = $this->createAuthPipeline();

        $response = $pipeline->process(
            new Request('GET', '/secure'),
            fn(Request $r) => JsonResponse::success('protected')
        );

        $this->assertSame(401, $response->getStatusCode());
    }

    public function testRequestWithInvalidTokenIsRejected(): void
    {
        $pipeline = $this->createAuthPipeline();

        $request = new Request('GET', '/secure', [], [], [
            'authorization' => 'Bearer invalid-token',
        ]);

        $response = $pipeline->process(
            $request,
            fn(Request $r) => JsonResponse::success('protected')
        );

        $this->assertSame(401, $response->getStatusCode());
    }

    public function testRequestWithValidTokenIsAccepted(): void
    {
        $pipeline = $this->createAuthPipeline();
        $token = $this->jwt->createAccessToken(1);

        $request = new Request('GET', '/secure', [], [], [
            'authorization' => "Bearer {$token}",
        ]);

        $response = $pipeline->process(
            $request,
            fn(Request $r) => JsonResponse::success(['user_id' => $r->getAttribute('user_id')])
        );

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame(1, $response->getBody()['data']['user_id']);
    }

    public function testRefreshTokenCannotBeUsedAsAccessToken(): void
    {
        $pipeline = $this->createAuthPipeline();
        $token = $this->jwt->createRefreshToken(1);

        $request = new Request('GET', '/secure', [], [], [
            'authorization' => "Bearer {$token}",
        ]);

        $response = $pipeline->process(
            $request,
            fn(Request $r) => JsonResponse::success('protected')
        );

        $this->assertSame(401, $response->getStatusCode());
    }

    public function testBasicAuthIsNotAccepted(): void
    {
        $pipeline = $this->createAuthPipeline();

        $request = new Request('GET', '/secure', [], [], [
            'authorization' => 'Basic dXNlcjpwYXNz',
        ]);

        $response = $pipeline->process(
            $request,
            fn(Request $r) => JsonResponse::success('protected')
        );

        $this->assertSame(401, $response->getStatusCode());
    }

    public function testExpiredTokenIsRejected(): void
    {
        $expiredJwt = new JWTService([
            'secret' => 'secure-test-secret-key-minimum-32-chars!',
            'access_ttl' => -1,
            'algo' => 'HS256',
        ]);

        $token = $expiredJwt->createAccessToken(1);
        $pipeline = $this->createAuthPipeline();

        $request = new Request('GET', '/secure', [], [], [
            'authorization' => "Bearer {$token}",
        ]);

        $response = $pipeline->process(
            $request,
            fn(Request $r) => JsonResponse::success('protected')
        );

        $this->assertSame(401, $response->getStatusCode());
    }

    public function testAuthAndPermissionChain(): void
    {
        $pipeline = new MiddlewarePipeline();

        $pipeline->pipe($this->createJwtAuthMiddleware());
        $pipeline->pipe($this->createPermissionMiddleware(['admin']));

        $token = $this->jwt->createAccessToken(1, ['roles' => ['admin']]);
        $request = new Request('GET', '/admin', [], [], [
            'authorization' => "Bearer {$token}",
        ]);

        $response = $pipeline->process(
            $request,
            fn(Request $r) => JsonResponse::success('admin-panel')
        );

        $this->assertSame(200, $response->getStatusCode());
    }

    public function testNonAdminCannotAccessAdminRoute(): void
    {
        $pipeline = new MiddlewarePipeline();

        $pipeline->pipe($this->createJwtAuthMiddleware());
        $pipeline->pipe($this->createPermissionMiddleware(['admin']));

        $token = $this->jwt->createAccessToken(2, ['roles' => ['user']]);
        $request = new Request('GET', '/admin', [], [], [
            'authorization' => "Bearer {$token}",
        ]);

        $response = $pipeline->process(
            $request,
            fn(Request $r) => JsonResponse::success('admin-panel')
        );

        $this->assertSame(403, $response->getStatusCode());
    }

    public function testProtectedRouteInRouter(): void
    {
        $router = new Router();
        $router->pushGlobalMiddleware($this->createJwtAuthMiddleware());

        $router->get('/api/me', function (Request $r) {
            return JsonResponse::success(['user_id' => $r->getAttribute('user_id')]);
        });

        $token = $this->jwt->createAccessToken(42);
        $request = new Request('GET', '/api/me', [], [], [
            'authorization' => "Bearer {$token}",
        ]);

        $response = $router->dispatch($request);
        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame(42, $response->getBody()['data']['user_id']);
    }

    public function testUnauthenticatedRouterRequest(): void
    {
        $router = new Router();
        $router->pushGlobalMiddleware($this->createJwtAuthMiddleware());

        $router->get('/api/me', fn(Request $r) => JsonResponse::success());

        $response = $router->dispatch(new Request('GET', '/api/me'));
        $this->assertSame(401, $response->getStatusCode());
    }

    public function testTamperedTokenInFullFlow(): void
    {
        $router = new Router();
        $router->pushGlobalMiddleware($this->createJwtAuthMiddleware());

        $router->get('/api/data', fn(Request $r) => JsonResponse::success('secret'));

        $token = $this->jwt->createAccessToken(1);
        $parts = explode('.', $token);
        $payload = json_decode(base64_decode($parts[1]), true);
        $payload['sub'] = 999;
        $parts[1] = rtrim(base64_encode(json_encode($payload)), '=');
        $tampered = implode('.', $parts);

        $request = new Request('GET', '/api/data', [], [], [
            'authorization' => "Bearer {$tampered}",
        ]);

        $response = $router->dispatch($request);
        $this->assertSame(401, $response->getStatusCode());
    }

    public function testImpersonationTokenCarriesMetadata(): void
    {
        $token = $this->jwt->createAccessToken(5, ['impersonated_by' => 1]);
        $payload = $this->jwt->decode($token);

        $this->assertSame(5, $payload->sub);
        $this->assertSame(1, $payload->impersonated_by);
    }

    private function createAuthPipeline(): MiddlewarePipeline
    {
        $pipeline = new MiddlewarePipeline();
        $pipeline->pipe($this->createJwtAuthMiddleware());
        return $pipeline;
    }

    private function createJwtAuthMiddleware(): MiddlewareInterface
    {
        $jwt = $this->jwt;
        return new class($jwt) implements MiddlewareInterface {
            public function __construct(private readonly JWTService $jwt) {}

            public function handle(Request $request, callable $next): JsonResponse
            {
                $token = $request->bearerToken();
                if (!$token) {
                    return JsonResponse::error('AUTH', 'Token required', [], 401);
                }

                try {
                    $payload = $this->jwt->decode($token);
                    if (($payload->type ?? '') !== 'access') {
                        return JsonResponse::error('AUTH', 'Invalid token type', [], 401);
                    }
                    $request->setAttribute('user_id', $payload->sub);
                    $request->setAttribute('jwt_payload', $payload);
                    return $next($request);
                } catch (AuthenticationException $e) {
                    return JsonResponse::error('AUTH', $e->getMessage(), [], 401);
                }
            }
        };
    }

    private function createPermissionMiddleware(array $requiredRoles): MiddlewareInterface
    {
        return new class($requiredRoles) implements MiddlewareInterface {
            public function __construct(private readonly array $roles) {}

            public function handle(Request $request, callable $next): JsonResponse
            {
                $payload = $request->getAttribute('jwt_payload');
                $userRoles = (array) ($payload->roles ?? []);

                foreach ($this->roles as $role) {
                    if (!in_array($role, $userRoles)) {
                        return JsonResponse::error('FORBIDDEN', 'Insufficient role', [], 403);
                    }
                }

                return $next($request);
            }
        };
    }
}
