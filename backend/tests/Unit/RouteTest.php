<?php

declare(strict_types=1);

namespace WebklientApp\Tests\Unit;

use PHPUnit\Framework\TestCase;
use WebklientApp\Core\Http\Route;

class RouteTest extends TestCase
{
    public function testStaticRouteMatches(): void
    {
        $route = new Route('GET', '/api/users', fn() => null);
        $params = $route->matches('GET', '/api/users');
        $this->assertSame([], $params);
    }

    public function testStaticRouteDoesNotMatchDifferentPath(): void
    {
        $route = new Route('GET', '/api/users', fn() => null);
        $this->assertNull($route->matches('GET', '/api/roles'));
    }

    public function testMethodMismatchReturnsNull(): void
    {
        $route = new Route('POST', '/api/users', fn() => null);
        $this->assertNull($route->matches('GET', '/api/users'));
    }

    public function testMethodIsCaseInsensitive(): void
    {
        $route = new Route('get', '/api/users', fn() => null);
        $params = $route->matches('GET', '/api/users');
        $this->assertSame([], $params);
    }

    public function testParameterRouteMatches(): void
    {
        $route = new Route('GET', '/api/users/{id}', fn() => null);
        $params = $route->matches('GET', '/api/users/42');
        $this->assertSame(['id' => '42'], $params);
    }

    public function testMultipleParametersMatch(): void
    {
        $route = new Route('GET', '/api/{module}/{id}', fn() => null);
        $params = $route->matches('GET', '/api/users/5');
        $this->assertSame(['module' => 'users', 'id' => '5'], $params);
    }

    public function testParameterDoesNotMatchSlash(): void
    {
        $route = new Route('GET', '/api/users/{id}', fn() => null);
        $this->assertNull($route->matches('GET', '/api/users/42/edit'));
    }

    public function testMiddleware(): void
    {
        $route = new Route('GET', '/test', fn() => null);
        $route->middleware('auth', 'permission');
        $this->assertSame(['auth', 'permission'], $route->getMiddleware());
    }

    public function testMiddlewareChaining(): void
    {
        $route = new Route('GET', '/test', fn() => null);
        $result = $route->middleware('auth')->middleware('rate');
        $this->assertSame(['auth', 'rate'], $result->getMiddleware());
    }

    public function testPermission(): void
    {
        $route = new Route('GET', '/test', fn() => null);
        $route->permission('users.list', 'users.read');
        $this->assertSame(['users.list', 'users.read'], $route->getPermissions());
    }

    public function testName(): void
    {
        $route = new Route('GET', '/test', fn() => null);
        $route->name('test.index');
        $this->assertSame('test.index', $route->getName());
    }

    public function testRateGroup(): void
    {
        $route = new Route('GET', '/test', fn() => null);
        $route->rateGroup('admin');
        $this->assertSame('admin', $route->getRateGroup());
    }

    public function testDefaultRateGroup(): void
    {
        $route = new Route('GET', '/test', fn() => null);
        $this->assertSame('authenticated', $route->getRateGroup());
    }

    public function testGetMethod(): void
    {
        $route = new Route('POST', '/test', fn() => null);
        $this->assertSame('POST', $route->getMethod());
    }

    public function testGetPattern(): void
    {
        $route = new Route('GET', '/api/users/{id}', fn() => null);
        $this->assertSame('/api/users/{id}', $route->getPattern());
    }

    public function testGetHandler(): void
    {
        $handler = fn() => 'result';
        $route = new Route('GET', '/test', $handler);
        $this->assertSame($handler, $route->getHandler());
    }

    public function testTrailingSlashDoesNotMatch(): void
    {
        $route = new Route('GET', '/api/users', fn() => null);
        $this->assertNull($route->matches('GET', '/api/users/'));
    }

    public function testExactPathRequired(): void
    {
        $route = new Route('GET', '/api', fn() => null);
        $this->assertNull($route->matches('GET', '/api/users'));
    }
}
