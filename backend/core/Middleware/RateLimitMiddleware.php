<?php

declare(strict_types=1);

namespace WebklientApp\Core\Middleware;

use WebklientApp\Core\ConfigLoader;
use WebklientApp\Core\Database\Connection;
use WebklientApp\Core\Http\Request;
use WebklientApp\Core\Http\JsonResponse;
use WebklientApp\Core\Security\RateLimiter;

class RateLimitMiddleware implements MiddlewareInterface
{
    public function handle(Request $request, callable $next): JsonResponse
    {
        $config = ConfigLoader::getInstance();
        $db = new Connection($config->get('database'));
        $limiter = new RateLimiter($db, $config->get('ratelimit', []));

        $userId = $request->getAttribute('user_id');
        $group = $request->getAttribute('rate_group', $userId ? 'authenticated' : 'public');
        $key = $userId ? "user:{$userId}:{$group}" : "ip:{$request->ip()}:{$group}";

        $result = $limiter->hit($key, $group);

        $response = $next($request);

        return $response
            ->withHeader('X-RateLimit-Limit', (string) $result['limit'])
            ->withHeader('X-RateLimit-Remaining', (string) $result['remaining'])
            ->withHeader('X-RateLimit-Reset', (string) $result['reset']);
    }
}
