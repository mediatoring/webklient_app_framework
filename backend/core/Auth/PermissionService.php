<?php

declare(strict_types=1);

namespace WebklientApp\Core\Auth;

use WebklientApp\Core\Database\Connection;

class PermissionService
{
    private Connection $db;

    /** @var array<int, string[]> Cached user permissions by user_id */
    private static array $cache = [];

    public function __construct(Connection $db)
    {
        $this->db = $db;
    }

    /**
     * Get all permission slugs for a user.
     */
    public function getUserPermissions(int $userId): array
    {
        if (isset(self::$cache[$userId])) {
            return self::$cache[$userId];
        }

        // Check if user has developer role (all permissions)
        $isDev = $this->db->fetchOne(
            "SELECT 1 FROM `user_roles` ur JOIN `roles` r ON r.id = ur.role_id WHERE ur.user_id = ? AND r.slug = 'developer'",
            [$userId]
        );

        if ($isDev) {
            $all = $this->db->fetchAll("SELECT `slug` FROM `permissions`");
            self::$cache[$userId] = array_column($all, 'slug');
            // Add wildcard so checks always pass
            self::$cache[$userId][] = '*';
            return self::$cache[$userId];
        }

        $rows = $this->db->fetchAll(
            "SELECT DISTINCT p.slug
             FROM `permissions` p
             JOIN `role_permissions` rp ON rp.permission_id = p.id
             JOIN `user_roles` ur ON ur.role_id = rp.role_id
             WHERE ur.user_id = ?",
            [$userId]
        );

        self::$cache[$userId] = array_column($rows, 'slug');
        return self::$cache[$userId];
    }

    public function userHasPermission(int $userId, string $permission): bool
    {
        $perms = $this->getUserPermissions($userId);
        return in_array('*', $perms) || in_array($permission, $perms);
    }

    public function userHasAllPermissions(int $userId, array $permissions): bool
    {
        $perms = $this->getUserPermissions($userId);
        if (in_array('*', $perms)) {
            return true;
        }
        foreach ($permissions as $p) {
            if (!in_array($p, $perms)) {
                return false;
            }
        }
        return true;
    }

    public function isSudo(int $userId): bool
    {
        $perms = $this->getUserPermissions($userId);
        return in_array('*', $perms);
    }

    public function getPermissionMatrix(): array
    {
        $roles = $this->db->fetchAll("SELECT `id`, `name`, `slug` FROM `roles` ORDER BY `id`");
        $permissions = $this->db->fetchAll("SELECT `id`, `name`, `slug`, `module` FROM `permissions` ORDER BY `module`, `slug`");
        $assigned = $this->db->fetchAll("SELECT `role_id`, `permission_id` FROM `role_permissions`");

        $assignedMap = [];
        foreach ($assigned as $a) {
            $assignedMap[$a['role_id']][$a['permission_id']] = true;
        }

        return [
            'roles' => $roles,
            'permissions' => $permissions,
            'matrix' => $assignedMap,
        ];
    }

    public static function clearCache(?int $userId = null): void
    {
        if ($userId !== null) {
            unset(self::$cache[$userId]);
        } else {
            self::$cache = [];
        }
    }
}
