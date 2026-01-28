<?php

declare(strict_types=1);

namespace WebklientApp\Core\Middleware;

use WebklientApp\Core\ConfigLoader;
use WebklientApp\Core\Database\Connection;
use WebklientApp\Core\Http\Request;
use WebklientApp\Core\Http\JsonResponse;
use WebklientApp\Core\Logging\ActivityLogger;

class ActivityLogMiddleware implements MiddlewareInterface
{
    public function handle(Request $request, callable $next): JsonResponse
    {
        $startTime = microtime(true);

        $response = $next($request);

        $elapsed = (int) round((microtime(true) - $startTime) * 1000);

        // Determine action type from HTTP method
        $actionType = match ($request->method()) {
            'POST' => 'create',
            'PUT', 'PATCH' => 'update',
            'DELETE' => 'delete',
            default => 'read',
        };

        $userId = $request->getAttribute('user_id');
        $isSudo = $request->getAttribute('is_sudo', false);

        // Skip logging for health check and docs
        $path = $request->path();
        if (str_starts_with($path, '/api/health') || str_starts_with($path, '/api/docs')) {
            return $response;
        }

        // For read operations, only log if sensitive or admin area
        if ($actionType === 'read' && !$isSudo && !str_contains($path, '/activity-log')) {
            return $response;
        }

        try {
            $config = ConfigLoader::getInstance();
            $db = new Connection($config->get('database'));
            $logger = new ActivityLogger($db);

            $payload = null;
            if ($request->isMutation()) {
                $payload = $request->input();
                // Remove sensitive fields
                unset($payload['password'], $payload['password_confirmation'], $payload['refresh_token']);
            }

            $logger->log([
                'user_id' => $userId,
                'action_type' => $actionType,
                'resource_type' => $this->extractResourceType($path),
                'resource_id' => $request->param('id') ?? $request->param('name'),
                'is_admin_action' => $isSudo ? 1 : 0,
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'request_method' => $request->method(),
                'request_path' => $path,
                'request_payload' => $payload,
                'response_status' => $response->getStatusCode(),
                'response_time_ms' => $elapsed,
            ]);
        } catch (\Throwable) {
            // Don't fail the request if logging fails
        }

        return $response;
    }

    private function extractResourceType(string $path): ?string
    {
        // /api/users/5 -> users, /api/admin/modules/foo -> modules
        $parts = explode('/', trim($path, '/'));
        // Skip 'api' and optional 'admin'
        $offset = 1;
        if (isset($parts[1]) && $parts[1] === 'admin') {
            $offset = 2;
        }
        return $parts[$offset] ?? null;
    }
}
