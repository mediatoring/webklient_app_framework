<?php

declare(strict_types=1);

namespace WebklientApp\Database\Migrations;

use WebklientApp\Core\Database\Migration;
use WebklientApp\Core\Database\Connection;

class CreateAiConversationsTable extends Migration
{
    public function up(Connection $db): void
    {
        $db->execute("
            CREATE TABLE `ai_conversations` (
                `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                `user_id` INT UNSIGNED NOT NULL,
                `title` VARCHAR(255) DEFAULT NULL,
                `provider` VARCHAR(50) NOT NULL,
                `model` VARCHAR(100) NOT NULL,
                `total_tokens` INT UNSIGNED NOT NULL DEFAULT 0,
                `estimated_cost` DECIMAL(10, 6) NOT NULL DEFAULT 0,
                `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                INDEX `idx_conv_user` (`user_id`),
                FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        $db->execute("
            CREATE TABLE `ai_messages` (
                `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                `conversation_id` INT UNSIGNED NOT NULL,
                `role` ENUM('user', 'assistant', 'system') NOT NULL,
                `content` LONGTEXT NOT NULL,
                `tokens` INT UNSIGNED DEFAULT 0,
                `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                INDEX `idx_msg_conv` (`conversation_id`),
                FOREIGN KEY (`conversation_id`) REFERENCES `ai_conversations`(`id`) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
    }

    public function down(Connection $db): void
    {
        $db->execute("DROP TABLE IF EXISTS `ai_messages`");
        $db->execute("DROP TABLE IF EXISTS `ai_conversations`");
    }
}
