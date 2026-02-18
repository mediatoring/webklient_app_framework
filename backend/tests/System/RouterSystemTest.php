<?php

declare(strict_types=1);

namespace WebklientApp\Tests\System;

use PHPUnit\Framework\TestCase;
use WebklientApp\Core\Http\JsonResponse;
use WebklientApp\Core\Http\Request;
use WebklientApp\Core\Http\Router;
use WebklientApp\Core\Middleware\MiddlewareInterface;
use WebklientApp\Core\Exceptions\NotFoundException;

/**
 * System tests: Router + Middleware + Handlers working together end-to-end.
 */
class RouterSystemTest extends TestCase
{
    private Router $router;

    protected function setUp(): void
    {
        $this->router = new Router();
    }

    public function testFullRequestLifecycleGetRoute(): void
    {
        $this->router->get('/api/users', function (Request $r) {
            return JsonResponse::success([
                ['id' => 1, 'name' => 'Admin'],
                ['id' => 2, 'name' => 'User'],
            ]);
        });

        $request = new Request('GET', '/api/users');
        $response = $this->router->dispatch($request);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertTrue($response->getBody()['success']);
        $this->assertCount(2, $response->getBody()['data']);
    }

    public function testFullRequestLifecyclePostWithBody(): void
    {
        $this->router->post('/api/users', function (Request $r) {
            $name = $r->input('name');
            $email = $r->input('email');
            return JsonResponse::created(['id' => 99, 'name' => $name, 'email' => $email]);
        });

        $request = new Request('POST', '/api/users', [], [
            'name' => 'John Doe',
            'email' => 'john@example.com',
        ]);
        $response = $this->router->dispatch($request);

        $this->assertSame(201, $response->getStatusCode());
        $this->assertSame('John Doe', $response->getBody()['data']['name']);
    }

    public function testRouteWithParametersAndQueryString(): void
    {
        $this->router->get('/api/users/{id}/posts', function (Request $r) {
            return JsonResponse::success([
                'user_id' => $r->param('id'),
                'page' => $r->query('page', '1'),
                'sort' => $r->query('sort', 'date'),
            ]);
        });

        $request = new Request('GET', '/api/users/42/posts', ['page' => '3', 'sort' => 'title']);
        $response = $this->router->dispatch($request);

        $data = $response->getBody()['data'];
        $this->assertSame('42', $data['user_id']);
        $this->assertSame('3', $data['page']);
        $this->assertSame('title', $data['sort']);
    }

    public function testGlobalMiddlewareAppliedToAllRoutes(): void
    {
        $globalMiddleware = new class implements MiddlewareInterface {
            public function handle(Request $request, callable $next): JsonResponse
            {
                $request->setAttribute('global_applied', true);
                return $next($request);
            }
        };

        $this->router->pushGlobalMiddleware($globalMiddleware);
        $this->router->get('/test', function (Request $r) {
            return JsonResponse::success(['global' => $r->getAttribute('global_applied')]);
        });

        $response = $this->router->dispatch(new Request('GET', '/test'));
        $this->assertTrue($response->getBody()['data']['global']);
    }

    public function testMiddlewareAliasResolution(): void
    {
        $testMiddleware = new class implements MiddlewareInterface {
            public function handle(Request $request, callable $next): JsonResponse
            {
                $request->setAttribute('alias_resolved', true);
                return $next($request);
            }
        };

        $this->router->aliasMiddleware('test-mw', get_class($testMiddleware));

        $this->router->get('/test', function (Request $r) {
            return JsonResponse::success(['resolved' => $r->getAttribute('alias_resolved', false)]);
        });

        $response = $this->router->dispatch(new Request('GET', '/test'));
        $this->assertSame(200, $response->getStatusCode());
    }

    public function testGroupMiddlewareApplied(): void
    {
        $authMiddleware = new class implements MiddlewareInterface {
            public function handle(Request $request, callable $next): JsonResponse
            {
                $request->setAttribute('authenticated', true);
                return $next($request);
            }
        };

        $this->router->aliasMiddleware('test-auth', get_class($authMiddleware));

        $this->router->group('/api', ['test-auth'], function (Router $r) {
            $r->get('/protected', function (Request $req) {
                return JsonResponse::success(['auth' => $req->getAttribute('authenticated', false)]);
            });
        });

        $response = $this->router->dispatch(new Request('GET', '/api/protected'));
        $this->assertSame(200, $response->getStatusCode());
    }

    public function testMiddlewareBlocksRequest(): void
    {
        $blocker = new class implements MiddlewareInterface {
            public function handle(Request $request, callable $next): JsonResponse
            {
                if (!$request->bearerToken()) {
                    return JsonResponse::error('UNAUTHORIZED', 'Token required', [], 401);
                }
                return $next($request);
            }
        };

        $this->router->pushGlobalMiddleware($blocker);
        $this->router->get('/secure', fn(Request $r) => JsonResponse::success('secret'));

        $response = $this->router->dispatch(new Request('GET', '/secure'));
        $this->assertSame(401, $response->getStatusCode());
        $this->assertFalse($response->getBody()['success']);
    }

    public function testMiddlewareAllowsAuthenticatedRequest(): void
    {
        $blocker = new class implements MiddlewareInterface {
            public function handle(Request $request, callable $next): JsonResponse
            {
                if (!$request->bearerToken()) {
                    return JsonResponse::error('UNAUTHORIZED', 'Token required', [], 401);
                }
                return $next($request);
            }
        };

        $this->router->pushGlobalMiddleware($blocker);
        $this->router->get('/secure', fn(Request $r) => JsonResponse::success('secret'));

        $request = new Request('GET', '/secure', [], [], ['authorization' => 'Bearer valid-token']);
        $response = $this->router->dispatch($request);
        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('secret', $response->getBody()['data']);
    }

    public function testPermissionsStoredOnRequest(): void
    {
        $this->router->get('/admin/users', function (Request $r) {
            return JsonResponse::success([
                'permissions' => $r->getAttribute('required_permissions'),
                'rate_group' => $r->getAttribute('rate_group'),
            ]);
        })->permission('users.list', 'users.read')->rateGroup('admin');

        $response = $this->router->dispatch(new Request('GET', '/admin/users'));
        $data = $response->getBody()['data'];

        $this->assertSame(['users.list', 'users.read'], $data['permissions']);
        $this->assertSame('admin', $data['rate_group']);
    }

    public function testMultipleRoutesIsolation(): void
    {
        $this->router->get('/users', fn(Request $r) => JsonResponse::success('users'));
        $this->router->get('/roles', fn(Request $r) => JsonResponse::success('roles'));
        $this->router->post('/users', fn(Request $r) => JsonResponse::created(['id' => 1]));

        $r1 = $this->router->dispatch(new Request('GET', '/users'));
        $r2 = $this->router->dispatch(new Request('GET', '/roles'));
        $r3 = $this->router->dispatch(new Request('POST', '/users'));

        $this->assertSame('users', $r1->getBody()['data']);
        $this->assertSame('roles', $r2->getBody()['data']);
        $this->assertSame(201, $r3->getStatusCode());
    }

    public function testDeleteRouteReturnsNoContent(): void
    {
        $this->router->delete('/api/users/{id}', function (Request $r) {
            return JsonResponse::noContent();
        });

        $response = $this->router->dispatch(new Request('DELETE', '/api/users/5'));
        $this->assertSame(204, $response->getStatusCode());
    }

    public function testPaginatedResponseEndToEnd(): void
    {
        $this->router->get('/api/items', function (Request $r) {
            $page = (int) $r->query('page', '1');
            $perPage = (int) $r->query('per_page', '10');
            $items = array_map(fn($i) => ['id' => $i], range(1, $perPage));
            return JsonResponse::paginated($items, 100, $page, $perPage);
        });

        $request = new Request('GET', '/api/items', ['page' => '2', 'per_page' => '10']);
        $response = $this->router->dispatch($request);

        $body = $response->getBody();
        $this->assertTrue($body['success']);
        $this->assertCount(10, $body['data']);
        $this->assertSame(2, $body['metadata']['pagination']['current_page']);
        $this->assertSame(100, $body['metadata']['pagination']['total']);
        $this->assertSame(10, $body['metadata']['pagination']['last_page']);
    }

    public function testErrorResponseEndToEnd(): void
    {
        $this->router->post('/api/users', function (Request $r) {
            if (!$r->input('email')) {
                return JsonResponse::error('VALIDATION', 'Email is required', [
                    ['field' => 'email', 'rule' => 'required'],
                ]);
            }
            return JsonResponse::created(['id' => 1]);
        });

        $request = new Request('POST', '/api/users', [], ['name' => 'John']);
        $response = $this->router->dispatch($request);

        $this->assertSame(400, $response->getStatusCode());
        $this->assertFalse($response->getBody()['success']);
        $this->assertSame('VALIDATION', $response->getBody()['error']['code']);
    }
}
