<?php

declare(strict_types=1);

namespace WebklientApp\Core\Middleware;

use WebklientApp\Core\Http\Request;
use WebklientApp\Core\Http\JsonResponse;

interface MiddlewareInterface
{
    /**
     * Process the request. Call $next($request) to pass to the next middleware.
     */
    public function handle(Request $request, callable $next): JsonResponse;
}
