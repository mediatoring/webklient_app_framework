<?php

declare(strict_types=1);

namespace WebklientApp\Core\Auth;

use WebklientApp\Core\Database\Connection;

/**
 * Ensures the first user account (id=1) always has the developer role
 * with full permissions. Automatically syncs all permissions to the
 * developer role on every call, so new permissions from modules
 * or updates are always included.
 *
 * Called during bootstrap and after permission/module changes.
 */
class DeveloperGuard
{
    private Connection $db;

    public function __construct(Connection $db)
    {
        $this->db = $db;
    }

    /**
     * Ensure developer role exists, has all permissions,
     * and user #1 is assigned to it.
     */
    public function enforce(): void
    {
        $devRole = $this->ensureDeveloperRole();
        $this->syncAllPermissions($devRole);
        $this->ensureUserHasRole(1, $devRole);
    }

    /**
     * Sync all existing permissions to the developer role.
     * Safe to call repeatedly — skips already assigned permissions.
     */
    public function syncAllPermissions(int $roleId): void
    {
        $this->db->execute("
            INSERT IGNORE INTO `role_permissions` (`role_id`, `permission_id`)
            SELECT ?, `id` FROM `permissions`
            WHERE `id` NOT IN (
                SELECT `permission_id` FROM `role_permissions` WHERE `role_id` = ?
            )
        ", [$roleId, $roleId]);
    }

    /**
     * Get or create the developer role, returns role ID.
     */
    private function ensureDeveloperRole(): int
    {
        $role = $this->db->fetchOne(
            "SELECT `id` FROM `roles` WHERE `slug` = 'developer'"
        );

        if ($role) {
            return (int) $role['id'];
        }

        $this->db->execute(
            "INSERT INTO `roles` (`name`, `slug`, `description`, `is_system`) VALUES ('Developer', 'developer', 'Sudo role with full system access', 1)"
        );

        return (int) $this->db->lastInsertId();
    }

    private function ensureUserHasRole(int $userId, int $roleId): void
    {
        $user = $this->db->fetchOne("SELECT `id` FROM `users` WHERE `id` = ?", [$userId]);
        if (!$user) {
            return;
        }

        $exists = $this->db->fetchOne(
            "SELECT 1 FROM `user_roles` WHERE `user_id` = ? AND `role_id` = ?",
            [$userId, $roleId]
        );

        if (!$exists) {
            $this->db->execute(
                "INSERT INTO `user_roles` (`user_id`, `role_id`) VALUES (?, ?)",
                [$userId, $roleId]
            );
        }
    }

    /**
     * Ensure a newly created permission is immediately
     * granted to the developer role.
     */
    public function grantNewPermission(int $permissionId): void
    {
        $devRole = $this->db->fetchOne("SELECT `id` FROM `roles` WHERE `slug` = 'developer'");
        if (!$devRole) {
            return;
        }

        $exists = $this->db->fetchOne(
            "SELECT 1 FROM `role_permissions` WHERE `role_id` = ? AND `permission_id` = ?",
            [$devRole['id'], $permissionId]
        );

        if (!$exists) {
            $this->db->execute(
                "INSERT INTO `role_permissions` (`role_id`, `permission_id`) VALUES (?, ?)",
                [$devRole['id'], $permissionId]
            );
        }
    }
}
