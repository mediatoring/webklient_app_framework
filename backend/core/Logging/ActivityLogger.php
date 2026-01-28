<?php

declare(strict_types=1);

namespace WebklientApp\Core\Logging;

use WebklientApp\Core\Database\Connection;

class ActivityLogger
{
    private Connection $db;

    public function __construct(Connection $db)
    {
        $this->db = $db;
    }

    public function log(array $data): void
    {
        $this->db->execute(
            "INSERT INTO `activity_log`
                (`user_id`, `action_type`, `resource_type`, `resource_id`, `changes`,
                 `is_admin_action`, `ip_address`, `user_agent`, `request_method`,
                 `request_path`, `request_payload`, `response_status`, `response_time_ms`)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)",
            [
                $data['user_id'] ?? null,
                $data['action_type'],
                $data['resource_type'] ?? null,
                $data['resource_id'] ?? null,
                isset($data['changes']) ? json_encode($data['changes']) : null,
                $data['is_admin_action'] ?? 0,
                $data['ip_address'] ?? null,
                $data['user_agent'] ?? null,
                $data['request_method'] ?? null,
                $data['request_path'] ?? null,
                isset($data['request_payload']) ? json_encode($data['request_payload']) : null,
                $data['response_status'] ?? null,
                $data['response_time_ms'] ?? null,
            ]
        );
    }

    public function logLogin(int $userId, bool $success, string $ip, string $userAgent): void
    {
        $this->log([
            'user_id' => $userId,
            'action_type' => $success ? 'login' : 'login_failed',
            'resource_type' => 'auth',
            'ip_address' => $ip,
            'user_agent' => $userAgent,
        ]);
    }

    public function logLogout(int $userId, string $ip): void
    {
        $this->log([
            'user_id' => $userId,
            'action_type' => 'logout',
            'resource_type' => 'auth',
            'ip_address' => $ip,
        ]);
    }

    public function logPermissionChange(int $userId, string $resourceType, string $resourceId, array $before, array $after, string $ip): void
    {
        $this->log([
            'user_id' => $userId,
            'action_type' => 'permission_change',
            'resource_type' => $resourceType,
            'resource_id' => $resourceId,
            'changes' => ['before' => $before, 'after' => $after],
            'is_admin_action' => 1,
            'ip_address' => $ip,
        ]);
    }

    public function logImpersonate(int $sudoUserId, int $targetUserId, string $ip): void
    {
        $this->log([
            'user_id' => $sudoUserId,
            'action_type' => 'impersonate',
            'resource_type' => 'users',
            'resource_id' => (string) $targetUserId,
            'is_admin_action' => 1,
            'ip_address' => $ip,
        ]);
    }

    public function logConfigChange(int $userId, string $key, mixed $before, mixed $after, string $ip): void
    {
        $this->log([
            'user_id' => $userId,
            'action_type' => 'config_change',
            'resource_type' => 'config',
            'resource_id' => $key,
            'changes' => ['before' => $before, 'after' => $after],
            'is_admin_action' => 1,
            'ip_address' => $ip,
        ]);
    }

    public function logModuleAction(int $userId, string $action, string $moduleName, string $ip): void
    {
        $this->log([
            'user_id' => $userId,
            'action_type' => $action, // module_enable or module_disable
            'resource_type' => 'modules',
            'resource_id' => $moduleName,
            'is_admin_action' => 1,
            'ip_address' => $ip,
        ]);
    }

    /**
     * Archive old logs based on retention days per action type.
     */
    public function archiveOldLogs(int $defaultDays = 90, array $retentionOverrides = []): int
    {
        // Admin actions get longer retention
        $adminDays = $retentionOverrides['admin'] ?? 365;
        $defaultRetention = $retentionOverrides['default'] ?? $defaultDays;

        // Archive non-admin logs older than default retention
        $cutoff = date('Y-m-d H:i:s', strtotime("-{$defaultRetention} days"));
        $adminCutoff = date('Y-m-d H:i:s', strtotime("-{$adminDays} days"));

        $count = $this->db->execute(
            "INSERT INTO `archive_activity_log`
                (`id`, `user_id`, `action_type`, `resource_type`, `resource_id`, `changes`,
                 `is_admin_action`, `ip_address`, `user_agent`, `request_method`,
                 `request_path`, `response_status`, `response_time_ms`, `created_at`)
             SELECT `id`, `user_id`, `action_type`, `resource_type`, `resource_id`, `changes`,
                    `is_admin_action`, `ip_address`, `user_agent`, `request_method`,
                    `request_path`, `response_status`, `response_time_ms`, `created_at`
             FROM `activity_log`
             WHERE (`is_admin_action` = 0 AND `created_at` < ?)
                OR (`is_admin_action` = 1 AND `created_at` < ?)",
            [$cutoff, $adminCutoff]
        );

        // Delete archived records from main table
        $this->db->execute(
            "DELETE FROM `activity_log`
             WHERE (`is_admin_action` = 0 AND `created_at` < ?)
                OR (`is_admin_action` = 1 AND `created_at` < ?)",
            [$cutoff, $adminCutoff]
        );

        return $count;
    }
}
