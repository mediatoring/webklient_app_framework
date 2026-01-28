<?php

declare(strict_types=1);

namespace WebklientApp\Core\Http\Controllers;

use WebklientApp\Core\Http\Request;
use WebklientApp\Core\Http\JsonResponse;
use WebklientApp\Core\Auth\JWTService;
use WebklientApp\Core\Exceptions\NotFoundException;
use WebklientApp\Core\Exceptions\ValidationException;
use WebklientApp\Core\Module\ModuleManager;
use WebklientApp\Core\ConfigLoader;

class AdminController extends BaseController
{
    public function systemInfo(Request $request): JsonResponse
    {
        return JsonResponse::success([
            'php_version' => PHP_VERSION,
            'os' => PHP_OS,
            'server_software' => $_SERVER['SERVER_SOFTWARE'] ?? 'CLI',
            'db_version' => $this->db->fetchColumn("SELECT VERSION()"),
            'disk_free' => disk_free_space(dirname(__DIR__, 3)),
            'disk_total' => disk_total_space(dirname(__DIR__, 3)),
            'memory_usage' => memory_get_usage(true),
            'memory_peak' => memory_get_peak_usage(true),
            'uptime' => @file_get_contents('/proc/uptime') ?: null,
        ]);
    }

    public function healthCheck(Request $request): JsonResponse
    {
        $checks = [];
        $overall = 'healthy';

        // Database
        try {
            $this->db->fetchOne("SELECT 1");
            $checks['database'] = ['status' => 'healthy'];
        } catch (\Throwable $e) {
            $checks['database'] = ['status' => 'unhealthy', 'error' => $e->getMessage()];
            $overall = 'unhealthy';
        }

        // Storage writable
        $storagePath = dirname(__DIR__, 3) . '/storage';
        $checks['storage'] = ['status' => is_writable($storagePath) ? 'healthy' : 'unhealthy'];
        if ($checks['storage']['status'] === 'unhealthy') {
            $overall = 'degraded';
        }

        // Disk space (warn if < 1GB)
        $free = disk_free_space(dirname(__DIR__, 3));
        $checks['disk'] = [
            'status' => $free > 1073741824 ? 'healthy' : 'degraded',
            'free_bytes' => $free,
        ];
        if ($checks['disk']['status'] !== 'healthy' && $overall === 'healthy') {
            $overall = 'degraded';
        }

        return JsonResponse::success([
            'status' => $overall,
            'checks' => $checks,
            'timestamp' => date('c'),
        ]);
    }

    public function cacheClear(Request $request): JsonResponse
    {
        $cacheDir = dirname(__DIR__, 3) . '/storage/cache';
        $files = glob($cacheDir . '/*');
        $count = 0;
        foreach ($files as $file) {
            if (is_file($file) && basename($file) !== '.gitkeep') {
                unlink($file);
                $count++;
            }
        }

        return JsonResponse::success(['cleared' => $count], 'Cache cleared.');
    }

    public function optimize(Request $request): JsonResponse
    {
        $tables = $this->db->fetchAll("SHOW TABLES");
        $optimized = [];
        foreach ($tables as $row) {
            $table = reset($row);
            $this->db->execute("OPTIMIZE TABLE `{$table}`");
            $optimized[] = $table;
        }

        return JsonResponse::success(['tables_optimized' => $optimized]);
    }

    public function activityLog(Request $request): JsonResponse
    {
        $p = $this->paginationParams($request);
        $q = $this->query->table('activity_log');

        if ($userId = $request->get('user_id')) {
            $q = $q->where('user_id', (int) $userId);
        }
        if ($actionType = $request->get('action_type')) {
            $q = $q->where('action_type', $actionType);
        }
        if ($resourceType = $request->get('resource_type')) {
            $q = $q->where('resource_type', $resourceType);
        }
        if ($from = $request->get('from')) {
            $q = $q->where('created_at', '>=', $from);
        }
        if ($to = $request->get('to')) {
            $q = $q->where('created_at', '<=', $to);
        }
        if ($request->get('admin_only') === '1') {
            $q = $q->where('is_admin_action', 1);
        }

        $result = $q->orderBy('created_at', 'DESC')->paginate($p['page'], $p['per_page']);
        return JsonResponse::paginated($result['items'], $result['total'], $result['page'], $result['per_page']);
    }

    public function allUsers(Request $request): JsonResponse
    {
        $p = $this->paginationParams($request);
        $result = $this->query->table('users')
            ->select('id', 'username', 'email', 'display_name', 'is_active', 'created_at', 'last_login_at')
            ->orderBy($p['sort'], $p['order'])
            ->paginate($p['page'], $p['per_page']);

        return JsonResponse::paginated($result['items'], $result['total'], $result['page'], $result['per_page']);
    }

    public function impersonate(Request $request): JsonResponse
    {
        $targetUserId = (int) ($request->input()['user_id'] ?? 0);
        $target = $this->query->table('users')->where('id', $targetUserId)->first();
        if (!$target) {
            throw new NotFoundException('Target user not found.');
        }

        $originalUserId = $request->getAttribute('user_id');

        $config = ConfigLoader::getInstance();
        $jwt = new JWTService($config->get('auth.jwt'));

        $accessToken = $jwt->createAccessToken($targetUserId, [
            'impersonated_by' => $originalUserId,
        ]);

        // Store impersonation token
        $this->db->execute(
            "INSERT INTO api_tokens (user_id, token_hash, type, expires_at, ip_address, user_agent) VALUES (?, ?, 'access', DATE_ADD(NOW(), INTERVAL ? SECOND), ?, ?)",
            [
                $targetUserId,
                hash('sha256', $accessToken),
                (int) $config->env('JWT_ACCESS_TTL', 900),
                $request->ip(),
                $request->userAgent(),
            ]
        );

        return JsonResponse::success([
            'access_token' => $accessToken,
            'impersonating' => [
                'id' => $target['id'],
                'username' => $target['username'],
                'display_name' => $target['display_name'],
            ],
            'original_user_id' => $originalUserId,
        ]);
    }

    public function stopImpersonate(Request $request): JsonResponse
    {
        return JsonResponse::success(null, 'Impersonation stopped. Use your original token.');
    }

    public function permissionMatrix(Request $request): JsonResponse
    {
        $ctrl = new PermissionsController();
        return $ctrl->matrix($request);
    }

    public function updatePermissionMatrix(Request $request): JsonResponse
    {
        $data = $request->input();
        if (!isset($data['changes']) || !is_array($data['changes'])) {
            throw new ValidationException('changes array is required.');
        }

        foreach ($data['changes'] as $change) {
            $roleId = (int) ($change['role_id'] ?? 0);
            $permId = (int) ($change['permission_id'] ?? 0);
            $granted = (bool) ($change['granted'] ?? false);

            if ($granted) {
                $exists = $this->db->fetchOne(
                    "SELECT 1 FROM role_permissions WHERE role_id = ? AND permission_id = ?",
                    [$roleId, $permId]
                );
                if (!$exists) {
                    $this->db->execute(
                        "INSERT INTO role_permissions (role_id, permission_id) VALUES (?, ?)",
                        [$roleId, $permId]
                    );
                }
            } else {
                $this->db->execute(
                    "DELETE FROM role_permissions WHERE role_id = ? AND permission_id = ?",
                    [$roleId, $permId]
                );
            }
        }

        return JsonResponse::success(null, 'Permission matrix updated.');
    }

    public function installModule(Request $request): JsonResponse
    {
        $name = $request->param('name');
        $modules = new ModuleManager($this->db, dirname(__DIR__, 3) . '/modules');
        $modules->install($name);

        return JsonResponse::created(null, "/api/modules/{$name}", "Module installed.");
    }

    public function uninstallModule(Request $request): JsonResponse
    {
        $name = $request->param('name');
        $modules = new ModuleManager($this->db, dirname(__DIR__, 3) . '/modules');
        $modules->uninstall($name);

        return JsonResponse::success(null, 'Module uninstalled.');
    }
}
