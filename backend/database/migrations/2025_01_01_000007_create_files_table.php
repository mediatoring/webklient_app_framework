<?php

declare(strict_types=1);

namespace WebklientApp\Database\Migrations;

use WebklientApp\Core\Database\Migration;
use WebklientApp\Core\Database\Connection;

class CreateFilesTable extends Migration
{
    public function up(Connection $db): void
    {
        $db->execute("
            CREATE TABLE `files` (
                `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                `user_id` INT UNSIGNED DEFAULT NULL,
                `original_filename` VARCHAR(500) NOT NULL,
                `stored_filename` VARCHAR(255) NOT NULL UNIQUE,
                `mime_type` VARCHAR(100) NOT NULL,
                `size_bytes` BIGINT UNSIGNED NOT NULL,
                `upload_path` VARCHAR(500) NOT NULL,
                `is_public` TINYINT(1) NOT NULL DEFAULT 0,
                `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                INDEX `idx_files_user` (`user_id`),
                FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE SET NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
    }

    public function down(Connection $db): void
    {
        $db->execute("DROP TABLE IF EXISTS `files`");
    }
}
