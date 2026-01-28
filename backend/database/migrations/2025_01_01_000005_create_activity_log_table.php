<?php

declare(strict_types=1);

namespace WebklientApp\Database\Migrations;

use WebklientApp\Core\Database\Migration;
use WebklientApp\Core\Database\Connection;

class CreateActivityLogTable extends Migration
{
    public function up(Connection $db): void
    {
        $db->execute("
            CREATE TABLE `activity_log` (
                `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                `user_id` INT UNSIGNED DEFAULT NULL,
                `action_type` ENUM(
                    'create', 'read', 'update', 'delete',
                    'login', 'logout', 'login_failed',
                    'permission_change', 'module_enable', 'module_disable',
                    'config_change', 'impersonate', 'stop_impersonate'
                ) NOT NULL,
                `resource_type` VARCHAR(100) DEFAULT NULL,
                `resource_id` VARCHAR(100) DEFAULT NULL,
                `changes` JSON DEFAULT NULL,
                `is_admin_action` TINYINT(1) NOT NULL DEFAULT 0,
                `ip_address` VARCHAR(45) DEFAULT NULL,
                `user_agent` VARCHAR(500) DEFAULT NULL,
                `request_method` VARCHAR(10) DEFAULT NULL,
                `request_path` VARCHAR(500) DEFAULT NULL,
                `request_payload` JSON DEFAULT NULL,
                `response_status` SMALLINT UNSIGNED DEFAULT NULL,
                `response_time_ms` INT UNSIGNED DEFAULT NULL,
                `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                INDEX `idx_activity_user` (`user_id`),
                INDEX `idx_activity_type` (`action_type`),
                INDEX `idx_activity_resource` (`resource_type`, `resource_id`),
                INDEX `idx_activity_admin` (`is_admin_action`),
                INDEX `idx_activity_created` (`created_at`),
                FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE SET NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        $db->execute("
            CREATE TABLE `archive_activity_log` (
                `id` BIGINT UNSIGNED PRIMARY KEY,
                `user_id` INT UNSIGNED DEFAULT NULL,
                `action_type` VARCHAR(50) NOT NULL,
                `resource_type` VARCHAR(100) DEFAULT NULL,
                `resource_id` VARCHAR(100) DEFAULT NULL,
                `changes` JSON DEFAULT NULL,
                `is_admin_action` TINYINT(1) NOT NULL DEFAULT 0,
                `ip_address` VARCHAR(45) DEFAULT NULL,
                `user_agent` VARCHAR(500) DEFAULT NULL,
                `request_method` VARCHAR(10) DEFAULT NULL,
                `request_path` VARCHAR(500) DEFAULT NULL,
                `response_status` SMALLINT UNSIGNED DEFAULT NULL,
                `response_time_ms` INT UNSIGNED DEFAULT NULL,
                `created_at` TIMESTAMP NOT NULL,
                `archived_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                INDEX `idx_archive_created` (`created_at`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
    }

    public function down(Connection $db): void
    {
        $db->execute("DROP TABLE IF EXISTS `archive_activity_log`");
        $db->execute("DROP TABLE IF EXISTS `activity_log`");
    }
}
