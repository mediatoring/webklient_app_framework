<?php

declare(strict_types=1);

namespace WebklientApp\Database\Migrations;

use WebklientApp\Core\Database\Migration;
use WebklientApp\Core\Database\Connection;

class CreateRateLimitsTable extends Migration
{
    public function up(Connection $db): void
    {
        $db->execute("
            CREATE TABLE `rate_limits` (
                `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                `key` VARCHAR(255) NOT NULL,
                `hits` INT UNSIGNED NOT NULL DEFAULT 1,
                `window_start` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                `expires_at` TIMESTAMP NOT NULL,
                UNIQUE INDEX `idx_rate_key` (`key`),
                INDEX `idx_rate_expires` (`expires_at`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        $db->execute("
            CREATE TABLE `ip_blocks` (
                `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                `ip_address` VARCHAR(45) NOT NULL,
                `reason` VARCHAR(255) DEFAULT NULL,
                `failed_attempts` INT UNSIGNED NOT NULL DEFAULT 0,
                `blocked_until` TIMESTAMP NULL DEFAULT NULL,
                `is_whitelisted` TINYINT(1) NOT NULL DEFAULT 0,
                `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                UNIQUE INDEX `idx_ip` (`ip_address`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
    }

    public function down(Connection $db): void
    {
        $db->execute("DROP TABLE IF EXISTS `ip_blocks`");
        $db->execute("DROP TABLE IF EXISTS `rate_limits`");
    }
}
