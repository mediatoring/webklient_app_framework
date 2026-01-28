<?php

declare(strict_types=1);

namespace WebklientApp\Core\Middleware;

use WebklientApp\Core\ConfigLoader;
use WebklientApp\Core\Http\Request;
use WebklientApp\Core\Http\JsonResponse;

class CorsMiddleware implements MiddlewareInterface
{
    public function handle(Request $request, callable $next): JsonResponse
    {
        $config = ConfigLoader::getInstance();
        $cors = $config->get('cors', []);

        $origins = $cors['allowed_origins'] ?? ['*'];
        $methods = $cors['allowed_methods'] ?? ['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS'];
        $headers = $cors['allowed_headers'] ?? ['Content-Type', 'Authorization', 'X-Requested-With'];
        $maxAge = $cors['max_age'] ?? 86400;

        $origin = $request->header('origin', '*');

        $allowedOrigin = in_array('*', $origins) ? '*' : (in_array($origin, $origins) ? $origin : '');

        $response = $next($request);

        if ($allowedOrigin !== '') {
            $response = $response
                ->withHeader('Access-Control-Allow-Origin', $allowedOrigin)
                ->withHeader('Access-Control-Allow-Methods', implode(', ', $methods))
                ->withHeader('Access-Control-Allow-Headers', implode(', ', $headers))
                ->withHeader('Access-Control-Max-Age', (string) $maxAge);

            if (($cors['allow_credentials'] ?? false) && $allowedOrigin !== '*') {
                $response = $response->withHeader('Access-Control-Allow-Credentials', 'true');
            }
        }

        return $response;
    }
}
