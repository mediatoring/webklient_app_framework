<?php

declare(strict_types=1);

namespace WebklientApp\Database\Migrations;

use WebklientApp\Core\Database\Migration;
use WebklientApp\Core\Database\Connection;

class CreateUsersTable extends Migration
{
    public function up(Connection $db): void
    {
        $db->execute("
            CREATE TABLE `users` (
                `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                `username` VARCHAR(100) NOT NULL UNIQUE,
                `email` VARCHAR(255) NOT NULL UNIQUE,
                `password_hash` VARCHAR(255) NOT NULL,
                `display_name` VARCHAR(255) DEFAULT NULL,
                `is_active` TINYINT(1) NOT NULL DEFAULT 1,
                `email_verified_at` TIMESTAMP NULL DEFAULT NULL,
                `last_login_at` TIMESTAMP NULL DEFAULT NULL,
                `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                INDEX `idx_users_email` (`email`),
                INDEX `idx_users_active` (`is_active`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
    }

    public function down(Connection $db): void
    {
        $db->execute("DROP TABLE IF EXISTS `users`");
    }
}
