<?php

declare(strict_types=1);

namespace WebklientApp\Core\Middleware;

use WebklientApp\Core\Auth\AuthService;
use WebklientApp\Core\Auth\JWTService;
use WebklientApp\Core\ConfigLoader;
use WebklientApp\Core\Database\Connection;
use WebklientApp\Core\Exceptions\AuthenticationException;
use WebklientApp\Core\Http\Request;
use WebklientApp\Core\Http\JsonResponse;

class AuthMiddleware implements MiddlewareInterface
{
    public function handle(Request $request, callable $next): JsonResponse
    {
        $token = $request->bearerToken();
        if (!$token) {
            throw new AuthenticationException('Authentication required. Provide Bearer token in Authorization header.');
        }

        $config = ConfigLoader::getInstance();
        $db = new Connection($config->get('database'));
        $jwt = new JWTService($config->get('auth.jwt'));
        $auth = new AuthService($db, $jwt);

        $user = $auth->validateAccessToken($token);
        $request->setAttribute('user', $user);
        $request->setAttribute('user_id', (int) $user['id']);

        return $next($request);
    }
}
