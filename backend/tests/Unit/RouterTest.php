<?php

declare(strict_types=1);

namespace WebklientApp\Tests\Unit;

use PHPUnit\Framework\TestCase;
use WebklientApp\Core\Http\JsonResponse;
use WebklientApp\Core\Http\Request;
use WebklientApp\Core\Http\Router;
use WebklientApp\Core\Exceptions\NotFoundException;

class RouterTest extends TestCase
{
    private Router $router;

    protected function setUp(): void
    {
        $this->router = new Router();
    }

    public function testGetRoute(): void
    {
        $this->router->get('/test', fn(Request $r) => JsonResponse::success('ok'));

        $response = $this->router->dispatch(new Request('GET', '/test'));
        $this->assertSame(200, $response->getStatusCode());
    }

    public function testPostRoute(): void
    {
        $this->router->post('/test', fn(Request $r) => JsonResponse::created(['id' => 1]));

        $response = $this->router->dispatch(new Request('POST', '/test'));
        $this->assertSame(201, $response->getStatusCode());
    }

    public function testPutRoute(): void
    {
        $this->router->put('/test/{id}', fn(Request $r) => JsonResponse::success('updated'));

        $response = $this->router->dispatch(new Request('PUT', '/test/5'));
        $this->assertSame(200, $response->getStatusCode());
    }

    public function testPatchRoute(): void
    {
        $this->router->patch('/test/{id}', fn(Request $r) => JsonResponse::success('patched'));

        $response = $this->router->dispatch(new Request('PATCH', '/test/5'));
        $this->assertSame(200, $response->getStatusCode());
    }

    public function testDeleteRoute(): void
    {
        $this->router->delete('/test/{id}', fn(Request $r) => JsonResponse::noContent());

        $response = $this->router->dispatch(new Request('DELETE', '/test/5'));
        $this->assertSame(204, $response->getStatusCode());
    }

    public function testOptionsRoutePreflight(): void
    {
        $response = $this->router->dispatch(new Request('OPTIONS', '/any-path'));
        $this->assertSame(204, $response->getStatusCode());
    }

    public function testRouteNotFoundThrows(): void
    {
        $this->expectException(NotFoundException::class);
        $this->router->dispatch(new Request('GET', '/nonexistent'));
    }

    public function testRouteParametersPassedToHandler(): void
    {
        $this->router->get('/users/{id}', function (Request $r) {
            return JsonResponse::success(['user_id' => $r->param('id')]);
        });

        $response = $this->router->dispatch(new Request('GET', '/users/42'));
        $this->assertSame('42', $response->getBody()['data']['user_id']);
    }

    public function testGroupPrefix(): void
    {
        $this->router->group('/api/v1', [], function (Router $r) {
            $r->get('/users', fn(Request $req) => JsonResponse::success('users'));
            $r->get('/roles', fn(Request $req) => JsonResponse::success('roles'));
        });

        $response = $this->router->dispatch(new Request('GET', '/api/v1/users'));
        $this->assertSame('users', $response->getBody()['data']);

        $response = $this->router->dispatch(new Request('GET', '/api/v1/roles'));
        $this->assertSame('roles', $response->getBody()['data']);
    }

    public function testNestedGroups(): void
    {
        $this->router->group('/api', [], function (Router $r) {
            $r->group('/v1', [], function (Router $r) {
                $r->get('/users', fn(Request $req) => JsonResponse::success('v1-users'));
            });
        });

        $response = $this->router->dispatch(new Request('GET', '/api/v1/users'));
        $this->assertSame('v1-users', $response->getBody()['data']);
    }

    public function testGroupPrefixDoesNotAffectOutsideRoutes(): void
    {
        $this->router->group('/api', [], function (Router $r) {
            $r->get('/inside', fn(Request $req) => JsonResponse::success('inside'));
        });
        $this->router->get('/outside', fn(Request $req) => JsonResponse::success('outside'));

        $response = $this->router->dispatch(new Request('GET', '/outside'));
        $this->assertSame('outside', $response->getBody()['data']);
    }

    public function testHandlerAsCallableArray(): void
    {
        $controller = new class {
            public function index(Request $r): JsonResponse
            {
                return JsonResponse::success('from-controller');
            }
        };

        $this->router->get('/test', [$controller, 'index']);
        $response = $this->router->dispatch(new Request('GET', '/test'));
        $this->assertSame('from-controller', $response->getBody()['data']);
    }

    public function testHandlerReturningNonJsonResponseWrapped(): void
    {
        $this->router->get('/test', fn(Request $r) => ['some' => 'data']);

        $response = $this->router->dispatch(new Request('GET', '/test'));
        $this->assertTrue($response->getBody()['success']);
        $this->assertSame(['some' => 'data'], $response->getBody()['data']);
    }

    public function testGetRoutesReturnsAllRegistered(): void
    {
        $this->router->get('/a', fn() => null);
        $this->router->post('/b', fn() => null);
        $this->router->put('/c', fn() => null);

        $this->assertCount(3, $this->router->getRoutes());
    }

    public function testFirstMatchingRouteWins(): void
    {
        $this->router->get('/test', fn(Request $r) => JsonResponse::success('first'));
        $this->router->get('/test', fn(Request $r) => JsonResponse::success('second'));

        $response = $this->router->dispatch(new Request('GET', '/test'));
        $this->assertSame('first', $response->getBody()['data']);
    }

    public function testMethodNotFoundForWrongMethod(): void
    {
        $this->router->get('/test', fn(Request $r) => JsonResponse::success('ok'));

        $this->expectException(NotFoundException::class);
        $this->router->dispatch(new Request('POST', '/test'));
    }
}
