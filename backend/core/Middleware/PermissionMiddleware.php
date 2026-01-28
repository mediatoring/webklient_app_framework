<?php

declare(strict_types=1);

namespace WebklientApp\Core\Middleware;

use WebklientApp\Core\Auth\PermissionService;
use WebklientApp\Core\ConfigLoader;
use WebklientApp\Core\Database\Connection;
use WebklientApp\Core\Exceptions\AuthorizationException;
use WebklientApp\Core\Http\Request;
use WebklientApp\Core\Http\JsonResponse;

class PermissionMiddleware implements MiddlewareInterface
{
    public function handle(Request $request, callable $next): JsonResponse
    {
        $required = $request->getAttribute('required_permissions', []);

        if (empty($required)) {
            return $next($request);
        }

        $userId = $request->getAttribute('user_id');
        if (!$userId) {
            throw new AuthorizationException('Authentication required.');
        }

        $config = ConfigLoader::getInstance();
        $db = new Connection($config->get('database'));
        $service = new PermissionService($db);

        if (!$service->userHasAllPermissions($userId, $required)) {
            throw new AuthorizationException(
                'Insufficient permissions. Required: ' . implode(', ', $required)
            );
        }

        return $next($request);
    }
}
