<?php

declare(strict_types=1);

namespace WebklientApp\Core\Middleware;

use WebklientApp\Core\ConfigLoader;
use WebklientApp\Core\Http\Request;
use WebklientApp\Core\Http\JsonResponse;

class SecurityHeadersMiddleware implements MiddlewareInterface
{
    public function handle(Request $request, callable $next): JsonResponse
    {
        $response = $next($request);

        $response = $response
            ->withHeader('X-Content-Type-Options', 'nosniff')
            ->withHeader('X-Frame-Options', 'DENY')
            ->withHeader('X-XSS-Protection', '1; mode=block')
            ->withHeader('Referrer-Policy', 'strict-origin-when-cross-origin')
            ->withHeader('Content-Security-Policy', "default-src 'none'; frame-ancestors 'none'");

        $config = ConfigLoader::getInstance();
        if (filter_var($config->env('HSTS_ENABLED', false), FILTER_VALIDATE_BOOLEAN)) {
            $response = $response->withHeader('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
        }

        return $response;
    }
}
