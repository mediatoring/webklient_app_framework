<?php

declare(strict_types=1);

namespace WebklientApp\Database\Migrations;

use WebklientApp\Core\Database\Migration;
use WebklientApp\Core\Database\Connection;

class CreateModulesTable extends Migration
{
    public function up(Connection $db): void
    {
        $db->execute("
            CREATE TABLE `modules` (
                `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                `name` VARCHAR(100) NOT NULL UNIQUE,
                `display_name` VARCHAR(255) DEFAULT NULL,
                `description` TEXT DEFAULT NULL,
                `version` VARCHAR(20) DEFAULT '1.0.0',
                `is_enabled` TINYINT(1) NOT NULL DEFAULT 0,
                `is_core` TINYINT(1) NOT NULL DEFAULT 0,
                `settings` JSON DEFAULT NULL,
                `installed_at` TIMESTAMP NULL DEFAULT NULL,
                `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
    }

    public function down(Connection $db): void
    {
        $db->execute("DROP TABLE IF EXISTS `modules`");
    }
}
