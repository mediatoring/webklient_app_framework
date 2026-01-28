<?php

declare(strict_types=1);

namespace WebklientApp\Core\Middleware;

use WebklientApp\Core\Http\Request;
use WebklientApp\Core\Http\JsonResponse;

class JsonMiddleware implements MiddlewareInterface
{
    public function handle(Request $request, callable $next): JsonResponse
    {
        $response = $next($request);

        return $response
            ->withHeader('X-API-Version', '1.0')
            ->withHeader('X-Request-Id', bin2hex(random_bytes(8)));
    }
}
