<?php

declare(strict_types=1);

namespace WebklientApp\Database\Migrations;

use WebklientApp\Core\Database\Migration;
use WebklientApp\Core\Database\Connection;

class CreateApiTokensTable extends Migration
{
    public function up(Connection $db): void
    {
        $db->execute("
            CREATE TABLE `api_tokens` (
                `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                `user_id` INT UNSIGNED NOT NULL,
                `token_hash` VARCHAR(255) NOT NULL UNIQUE,
                `type` ENUM('access', 'refresh') NOT NULL,
                `expires_at` TIMESTAMP NOT NULL,
                `last_used_at` TIMESTAMP NULL DEFAULT NULL,
                `revoked_at` TIMESTAMP NULL DEFAULT NULL,
                `ip_address` VARCHAR(45) DEFAULT NULL,
                `user_agent` VARCHAR(500) DEFAULT NULL,
                `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                INDEX `idx_tokens_user` (`user_id`),
                INDEX `idx_tokens_hash` (`token_hash`),
                INDEX `idx_tokens_expires` (`expires_at`),
                FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
    }

    public function down(Connection $db): void
    {
        $db->execute("DROP TABLE IF EXISTS `api_tokens`");
    }
}
