<?php

declare(strict_types=1);

namespace WebklientApp\Core\Middleware;

use WebklientApp\Core\Http\Request;
use WebklientApp\Core\Http\JsonResponse;

class MiddlewarePipeline
{
    /** @var MiddlewareInterface[] */
    private array $middleware = [];

    public function pipe(MiddlewareInterface $middleware): self
    {
        $this->middleware[] = $middleware;
        return $this;
    }

    public function process(Request $request, callable $destination): JsonResponse
    {
        $pipeline = array_reduce(
            array_reverse($this->middleware),
            fn(callable $next, MiddlewareInterface $mw) => fn(Request $req) => $mw->handle($req, $next),
            $destination
        );

        return $pipeline($request);
    }
}
