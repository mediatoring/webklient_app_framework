<?php

declare(strict_types=1);

/**
 * WebklientApp Framework - Installation script.
 *
 * Creates database tables, default roles, permissions, and sudo user.
 *
 * Usage: php bin/install.php
 */

require_once __DIR__ . '/../vendor/autoload.php';

use WebklientApp\Core\ConfigLoader;
use WebklientApp\Core\Database\Connection;
use WebklientApp\Core\Database\Migrator;

echo "=== WebklientApp Framework Installer ===\n\n";

// --- 1. Load config ---
$envFile = __DIR__ . '/../.env';
if (!file_exists($envFile)) {
    // Copy from example if .env doesn't exist
    if (file_exists($envFile . '.example')) {
        copy($envFile . '.example', $envFile);
        echo "[INFO] Created .env from .env.example\n";
    } else {
        echo "[ERROR] No .env file found. Create one from .env.example first.\n";
        exit(1);
    }
}

$config = ConfigLoader::getInstance($envFile);
$config->loadConfigDirectory(__DIR__ . '/../config');

// --- 2. Generate APP_KEY if empty ---
if (empty($config->env('APP_KEY'))) {
    $key = bin2hex(random_bytes(32));
    $envContent = file_get_contents($envFile);
    $envContent = preg_replace('/^APP_KEY=.*$/m', "APP_KEY={$key}", $envContent);
    file_put_contents($envFile, $envContent);
    echo "[INFO] Generated APP_KEY\n";
}

// --- 3. Generate JWT_SECRET if empty ---
if (empty($config->env('JWT_SECRET'))) {
    $secret = bin2hex(random_bytes(32));
    $envContent = file_get_contents($envFile);
    $envContent = preg_replace('/^JWT_SECRET=.*$/m', "JWT_SECRET={$secret}", $envContent);
    file_put_contents($envFile, $envContent);
    echo "[INFO] Generated JWT_SECRET\n";
}

// --- 4. Run migrations ---
echo "\n[STEP] Running database migrations...\n";
try {
    $db = new Connection($config->get('database'));
    $migrator = new Migrator($db, __DIR__ . '/../database/migrations');
    $executed = $migrator->migrate();

    if (empty($executed)) {
        echo "  All migrations already ran.\n";
    } else {
        foreach ($executed as $name) {
            echo "  Migrated: {$name}\n";
        }
    }
} catch (\Throwable $e) {
    echo "[ERROR] Migration failed: {$e->getMessage()}\n";
    exit(1);
}

// --- 5. Create default roles ---
echo "\n[STEP] Creating default roles...\n";
$defaultRoles = [
    ['name' => 'Developer', 'slug' => 'developer', 'description' => 'Sudo role with full system access', 'is_system' => 1],
    ['name' => 'Admin', 'slug' => 'admin', 'description' => 'Administrator with broad access', 'is_system' => 1],
    ['name' => 'User', 'slug' => 'user', 'description' => 'Regular authenticated user', 'is_system' => 1],
];

foreach ($defaultRoles as $role) {
    $existing = $db->fetchOne("SELECT `id` FROM `roles` WHERE `slug` = ?", [$role['slug']]);
    if (!$existing) {
        $db->execute(
            "INSERT INTO `roles` (`name`, `slug`, `description`, `is_system`) VALUES (?, ?, ?, ?)",
            [$role['name'], $role['slug'], $role['description'], $role['is_system']]
        );
        echo "  Created role: {$role['name']}\n";
    } else {
        echo "  Role exists: {$role['name']}\n";
    }
}

// --- 6. Create default permissions ---
echo "\n[STEP] Creating default permissions...\n";
$permissions = [
    // Users
    ['name' => 'List Users', 'slug' => 'users.list', 'module' => 'core'],
    ['name' => 'View User', 'slug' => 'users.view', 'module' => 'core'],
    ['name' => 'Create User', 'slug' => 'users.create', 'module' => 'core'],
    ['name' => 'Update User', 'slug' => 'users.update', 'module' => 'core'],
    ['name' => 'Delete User', 'slug' => 'users.delete', 'module' => 'core'],
    // Roles
    ['name' => 'List Roles', 'slug' => 'roles.list', 'module' => 'core'],
    ['name' => 'View Role', 'slug' => 'roles.view', 'module' => 'core'],
    ['name' => 'Create Role', 'slug' => 'roles.create', 'module' => 'core'],
    ['name' => 'Update Role', 'slug' => 'roles.update', 'module' => 'core'],
    ['name' => 'Delete Role', 'slug' => 'roles.delete', 'module' => 'core'],
    // Permissions
    ['name' => 'List Permissions', 'slug' => 'permissions.list', 'module' => 'core'],
    ['name' => 'Manage Permissions', 'slug' => 'permissions.manage', 'module' => 'core'],
    // Modules
    ['name' => 'List Modules', 'slug' => 'modules.list', 'module' => 'core'],
    ['name' => 'View Module', 'slug' => 'modules.view', 'module' => 'core'],
    ['name' => 'Manage Modules', 'slug' => 'modules.manage', 'module' => 'core'],
    // Activity Log
    ['name' => 'List Activity Log', 'slug' => 'activity_log.list', 'module' => 'core'],
    ['name' => 'View Activity Log', 'slug' => 'activity_log.view', 'module' => 'core'],
    ['name' => 'Activity Log Stats', 'slug' => 'activity_log.stats', 'module' => 'core'],
    // AI
    ['name' => 'AI Chat', 'slug' => 'ai.chat', 'module' => 'ai'],
    ['name' => 'AI Usage Stats', 'slug' => 'ai.usage', 'module' => 'ai'],
    // Files
    ['name' => 'Upload Files', 'slug' => 'files.upload', 'module' => 'core'],
    ['name' => 'Download Files', 'slug' => 'files.download', 'module' => 'core'],
];

foreach ($permissions as $perm) {
    $existing = $db->fetchOne("SELECT `id` FROM `permissions` WHERE `slug` = ?", [$perm['slug']]);
    if (!$existing) {
        $db->execute(
            "INSERT INTO `permissions` (`name`, `slug`, `module`, `is_system`) VALUES (?, ?, ?, 1)",
            [$perm['name'], $perm['slug'], $perm['module']]
        );
    }
}
echo "  " . count($permissions) . " permissions ensured.\n";

// --- 7. Assign all permissions to developer role ---
echo "\n[STEP] Assigning permissions to developer role...\n";
$devRole = $db->fetchOne("SELECT `id` FROM `roles` WHERE `slug` = 'developer'");
$allPerms = $db->fetchAll("SELECT `id` FROM `permissions`");
foreach ($allPerms as $perm) {
    $exists = $db->fetchOne(
        "SELECT 1 FROM `role_permissions` WHERE `role_id` = ? AND `permission_id` = ?",
        [$devRole['id'], $perm['id']]
    );
    if (!$exists) {
        $db->execute(
            "INSERT INTO `role_permissions` (`role_id`, `permission_id`) VALUES (?, ?)",
            [$devRole['id'], $perm['id']]
        );
    }
}
echo "  Developer role has all " . count($allPerms) . " permissions.\n";

// --- 8. Assign basic permissions to user role ---
$userRole = $db->fetchOne("SELECT `id` FROM `roles` WHERE `slug` = 'user'");
$userPerms = ['ai.chat', 'files.upload', 'files.download'];
foreach ($userPerms as $slug) {
    $perm = $db->fetchOne("SELECT `id` FROM `permissions` WHERE `slug` = ?", [$slug]);
    if ($perm) {
        $exists = $db->fetchOne(
            "SELECT 1 FROM `role_permissions` WHERE `role_id` = ? AND `permission_id` = ?",
            [$userRole['id'], $perm['id']]
        );
        if (!$exists) {
            $db->execute(
                "INSERT INTO `role_permissions` (`role_id`, `permission_id`) VALUES (?, ?)",
                [$userRole['id'], $perm['id']]
            );
        }
    }
}

// --- 9. Create sudo user ---
echo "\n[STEP] Creating sudo user...\n";
$existingSudo = $db->fetchOne("SELECT `id` FROM `users` WHERE `id` = 1");
if (!$existingSudo) {
    $password = bin2hex(random_bytes(8)); // Temporary password
    $hash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);

    $db->execute(
        "INSERT INTO `users` (`username`, `email`, `password_hash`, `display_name`, `is_active`, `email_verified_at`) VALUES (?, ?, ?, ?, 1, NOW())",
        ['developer', 'developer@localhost', $hash, 'Developer']
    );

    // Assign developer role
    $db->execute(
        "INSERT INTO `user_roles` (`user_id`, `role_id`) VALUES (1, ?)",
        [$devRole['id']]
    );

    echo "  Created sudo user:\n";
    echo "    Username: developer\n";
    echo "    Email:    developer@localhost\n";
    echo "    Password: {$password}\n";
    echo "  >>> SAVE THIS PASSWORD - it won't be shown again! <<<\n";
} else {
    echo "  Sudo user already exists.\n";
}

// --- 10. Create storage directories ---
echo "\n[STEP] Ensuring storage directories...\n";
$dirs = [
    __DIR__ . '/../storage/logs',
    __DIR__ . '/../storage/cache',
    __DIR__ . '/../storage/uploads',
    __DIR__ . '/../storage/archive',
];
foreach ($dirs as $dir) {
    if (!is_dir($dir)) {
        mkdir($dir, 0775, true);
        echo "  Created: {$dir}\n";
    }
}

echo "\n=== Installation complete! ===\n";
echo "You can now start the API server.\n";
