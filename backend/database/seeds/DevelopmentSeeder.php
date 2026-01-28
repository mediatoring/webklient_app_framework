<?php

declare(strict_types=1);

/**
 * Development seeder - creates test data for development environment.
 *
 * Usage: php database/seeds/DevelopmentSeeder.php
 */

require_once dirname(__DIR__, 2) . '/vendor/autoload.php';

use WebklientApp\Core\ConfigLoader;
use WebklientApp\Core\Database\Connection;

$config = ConfigLoader::getInstance(dirname(__DIR__, 2) . '/.env');
$config->loadConfigDirectory(dirname(__DIR__, 2) . '/config');
$db = new Connection($config->get('database'));

echo "Seeding development data...\n";

// Test users with different roles
$users = [
    ['username' => 'admin', 'email' => 'admin@example.com', 'display_name' => 'Admin User', 'role' => 'admin'],
    ['username' => 'john', 'email' => 'john@example.com', 'display_name' => 'John Doe', 'role' => 'user'],
    ['username' => 'jane', 'email' => 'jane@example.com', 'display_name' => 'Jane Smith', 'role' => 'user'],
];

$password = password_hash('password123', PASSWORD_BCRYPT, ['cost' => 12]);

foreach ($users as $user) {
    $exists = $db->fetchOne("SELECT id FROM users WHERE username = ?", [$user['username']]);
    if ($exists) {
        echo "  User exists: {$user['username']}\n";
        continue;
    }

    $db->execute(
        "INSERT INTO users (username, email, password_hash, display_name, is_active, email_verified_at) VALUES (?, ?, ?, ?, 1, NOW())",
        [$user['username'], $user['email'], $password, $user['display_name']]
    );

    $userId = $db->lastInsertId();
    $role = $db->fetchOne("SELECT id FROM roles WHERE slug = ?", [$user['role']]);
    if ($role) {
        $db->execute("INSERT INTO user_roles (user_id, role_id) VALUES (?, ?)", [$userId, $role['id']]);
    }

    echo "  Created user: {$user['username']} (role: {$user['role']}, password: password123)\n";
}

// Assign all permissions to admin role
$adminRole = $db->fetchOne("SELECT id FROM roles WHERE slug = 'admin'");
if ($adminRole) {
    $allPerms = $db->fetchAll("SELECT id FROM permissions");
    foreach ($allPerms as $perm) {
        $exists = $db->fetchOne(
            "SELECT 1 FROM role_permissions WHERE role_id = ? AND permission_id = ?",
            [$adminRole['id'], $perm['id']]
        );
        if (!$exists) {
            $db->execute(
                "INSERT INTO role_permissions (role_id, permission_id) VALUES (?, ?)",
                [$adminRole['id'], $perm['id']]
            );
        }
    }
    echo "  Admin role: all permissions assigned\n";
}

echo "\nSeeding complete.\n";
echo "All test users have password: password123\n";
