<?php

declare(strict_types=1);

namespace WebklientApp\Database\Migrations;

use WebklientApp\Core\Database\Migration;
use WebklientApp\Core\Database\Connection;

class CreatePasswordResetsTable extends Migration
{
    public function up(Connection $db): void
    {
        $db->execute("
            CREATE TABLE `password_resets` (
                `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                `user_id` INT UNSIGNED NOT NULL,
                `token_hash` VARCHAR(255) NOT NULL,
                `expires_at` TIMESTAMP NOT NULL,
                `used_at` TIMESTAMP NULL DEFAULT NULL,
                `ip_address` VARCHAR(45) DEFAULT NULL,
                `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                UNIQUE INDEX `idx_pr_token` (`token_hash`),
                INDEX `idx_pr_user` (`user_id`),
                INDEX `idx_pr_expires` (`expires_at`),
                CONSTRAINT `fk_pr_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
    }

    public function down(Connection $db): void
    {
        $db->execute("DROP TABLE IF EXISTS `password_resets`");
    }
}
