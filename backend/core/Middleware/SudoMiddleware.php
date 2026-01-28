<?php

declare(strict_types=1);

namespace WebklientApp\Core\Middleware;

use WebklientApp\Core\ConfigLoader;
use WebklientApp\Core\Database\Connection;
use WebklientApp\Core\Exceptions\AuthorizationException;
use WebklientApp\Core\Http\Request;
use WebklientApp\Core\Http\JsonResponse;

class SudoMiddleware implements MiddlewareInterface
{
    public function handle(Request $request, callable $next): JsonResponse
    {
        $userId = $request->getAttribute('user_id');
        if (!$userId) {
            throw new AuthorizationException('Authentication required before sudo check.');
        }

        $config = ConfigLoader::getInstance();
        $db = new Connection($config->get('database'));

        $hasSudo = $db->fetchOne(
            "SELECT 1 FROM `user_roles` ur
             JOIN `roles` r ON r.id = ur.role_id
             WHERE ur.user_id = ? AND r.slug = 'developer'",
            [$userId]
        );

        if (!$hasSudo) {
            throw new AuthorizationException('This action requires developer (sudo) privileges.');
        }

        $request->setAttribute('is_sudo', true);

        return $next($request);
    }
}
