<?php

declare(strict_types=1);

namespace WebklientApp\Tests\Unit;

use PHPUnit\Framework\TestCase;
use WebklientApp\Core\Http\JsonResponse;
use WebklientApp\Core\Http\Request;
use WebklientApp\Core\Middleware\MiddlewareInterface;
use WebklientApp\Core\Middleware\MiddlewarePipeline;

class MiddlewarePipelineTest extends TestCase
{
    public function testEmptyPipelineCallsDestination(): void
    {
        $pipeline = new MiddlewarePipeline();
        $called = false;

        $pipeline->process(
            new Request('GET', '/test'),
            function (Request $r) use (&$called) {
                $called = true;
                return JsonResponse::success('done');
            }
        );

        $this->assertTrue($called);
    }

    public function testSingleMiddlewareExecutes(): void
    {
        $pipeline = new MiddlewarePipeline();
        $pipeline->pipe($this->createMiddleware(function (Request $r, callable $next) {
            $r->setAttribute('middleware_ran', true);
            return $next($r);
        }));

        $response = $pipeline->process(
            new Request('GET', '/test'),
            function (Request $r) {
                return JsonResponse::success(['ran' => $r->getAttribute('middleware_ran')]);
            }
        );

        $this->assertTrue($response->getBody()['data']['ran']);
    }

    public function testMiddlewareExecutionOrder(): void
    {
        $order = [];
        $pipeline = new MiddlewarePipeline();

        $pipeline->pipe($this->createMiddleware(function (Request $r, callable $next) use (&$order) {
            $order[] = 'A-before';
            $response = $next($r);
            $order[] = 'A-after';
            return $response;
        }));

        $pipeline->pipe($this->createMiddleware(function (Request $r, callable $next) use (&$order) {
            $order[] = 'B-before';
            $response = $next($r);
            $order[] = 'B-after';
            return $response;
        }));

        $pipeline->process(
            new Request('GET', '/test'),
            function (Request $r) use (&$order) {
                $order[] = 'handler';
                return JsonResponse::success();
            }
        );

        $this->assertSame(['A-before', 'B-before', 'handler', 'B-after', 'A-after'], $order);
    }

    public function testMiddlewareCanShortCircuit(): void
    {
        $pipeline = new MiddlewarePipeline();
        $handlerCalled = false;

        $pipeline->pipe($this->createMiddleware(function (Request $r, callable $next) {
            return JsonResponse::error('BLOCKED', 'Blocked by middleware', [], 403);
        }));

        $response = $pipeline->process(
            new Request('GET', '/test'),
            function (Request $r) use (&$handlerCalled) {
                $handlerCalled = true;
                return JsonResponse::success();
            }
        );

        $this->assertFalse($handlerCalled);
        $this->assertSame(403, $response->getStatusCode());
    }

    public function testMiddlewareCanModifyResponse(): void
    {
        $pipeline = new MiddlewarePipeline();

        $pipeline->pipe($this->createMiddleware(function (Request $r, callable $next) {
            $response = $next($r);
            return $response->withHeader('X-Custom', 'added');
        }));

        $response = $pipeline->process(
            new Request('GET', '/test'),
            fn(Request $r) => JsonResponse::success()
        );

        $this->assertSame(200, $response->getStatusCode());
    }

    public function testMiddlewareCanModifyRequest(): void
    {
        $pipeline = new MiddlewarePipeline();

        $pipeline->pipe($this->createMiddleware(function (Request $r, callable $next) {
            $r->setAttribute('injected', 'value');
            return $next($r);
        }));

        $response = $pipeline->process(
            new Request('GET', '/test'),
            function (Request $r) {
                return JsonResponse::success(['injected' => $r->getAttribute('injected')]);
            }
        );

        $this->assertSame('value', $response->getBody()['data']['injected']);
    }

    public function testPipeReturnsSelf(): void
    {
        $pipeline = new MiddlewarePipeline();
        $result = $pipeline->pipe($this->createMiddleware(function (Request $r, callable $next) {
            return $next($r);
        }));

        $this->assertSame($pipeline, $result);
    }

    public function testThreeMiddlewareChain(): void
    {
        $pipeline = new MiddlewarePipeline();
        $tags = [];

        for ($i = 1; $i <= 3; $i++) {
            $tag = $i;
            $pipeline->pipe($this->createMiddleware(function (Request $r, callable $next) use ($tag, &$tags) {
                $tags[] = $tag;
                return $next($r);
            }));
        }

        $pipeline->process(new Request('GET', '/test'), fn(Request $r) => JsonResponse::success());

        $this->assertSame([1, 2, 3], $tags);
    }

    private function createMiddleware(callable $handler): MiddlewareInterface
    {
        return new class($handler) implements MiddlewareInterface {
            public function __construct(private readonly \Closure $handler) {}

            public function handle(Request $request, callable $next): JsonResponse
            {
                return ($this->handler)($request, $next);
            }
        };
    }
}
