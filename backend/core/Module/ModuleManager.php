<?php

declare(strict_types=1);

namespace WebklientApp\Core\Module;

use WebklientApp\Core\Database\Connection;
use WebklientApp\Core\Http\Router;
use WebklientApp\Core\Exceptions\NotFoundException;

class ModuleManager
{
    private Connection $db;
    private string $modulesPath;

    /** @var ModuleInterface[] */
    private array $loaded = [];

    public function __construct(Connection $db, string $modulesPath)
    {
        $this->db = $db;
        $this->modulesPath = $modulesPath;
    }

    /**
     * Load and register all enabled modules.
     */
    public function bootEnabledModules(Router $router): void
    {
        $enabled = $this->db->fetchAll(
            "SELECT `name` FROM `modules` WHERE `is_enabled` = 1"
        );

        foreach ($enabled as $row) {
            $module = $this->resolve($row['name']);
            if ($module) {
                $module->registerRoutes($router);
                $this->loaded[$row['name']] = $module;
            }
        }
    }

    public function install(string $name): void
    {
        $module = $this->resolve($name);
        if (!$module) {
            throw new NotFoundException("Module '{$name}' not found.");
        }

        $existing = $this->db->fetchOne("SELECT `id` FROM `modules` WHERE `name` = ?", [$name]);
        if ($existing) {
            throw new \RuntimeException("Module '{$name}' is already installed.");
        }

        $module->install();

        $this->db->execute(
            "INSERT INTO `modules` (`name`, `display_name`, `description`, `version`, `is_enabled`, `installed_at`) VALUES (?, ?, ?, ?, 0, NOW())",
            [$name, $module->getDisplayName(), $module->getDescription(), $module->getVersion()]
        );

        // Register permissions
        foreach ($module->getPermissions() as $perm) {
            $exists = $this->db->fetchOne("SELECT `id` FROM `permissions` WHERE `slug` = ?", [$perm['slug']]);
            if (!$exists) {
                $this->db->execute(
                    "INSERT INTO `permissions` (`name`, `slug`, `module`, `is_system`) VALUES (?, ?, ?, 0)",
                    [$perm['name'], $perm['slug'], $name]
                );
            }
        }
    }

    public function uninstall(string $name): void
    {
        $record = $this->db->fetchOne("SELECT * FROM `modules` WHERE `name` = ?", [$name]);
        if (!$record) {
            throw new NotFoundException("Module '{$name}' is not installed.");
        }
        if ($record['is_core']) {
            throw new \RuntimeException("Cannot uninstall core module '{$name}'.");
        }

        $module = $this->resolve($name);
        if ($module) {
            $module->uninstall();
        }

        // Remove module permissions
        $this->db->execute("DELETE rp FROM `role_permissions` rp JOIN `permissions` p ON p.id = rp.permission_id WHERE p.module = ?", [$name]);
        $this->db->execute("DELETE FROM `permissions` WHERE `module` = ?", [$name]);
        $this->db->execute("DELETE FROM `modules` WHERE `name` = ?", [$name]);
    }

    public function enable(string $name): void
    {
        $record = $this->db->fetchOne("SELECT * FROM `modules` WHERE `name` = ?", [$name]);
        if (!$record) {
            throw new NotFoundException("Module '{$name}' is not installed.");
        }

        $module = $this->resolve($name);
        if ($module) {
            $module->enable();
        }

        $this->db->execute("UPDATE `modules` SET `is_enabled` = 1 WHERE `name` = ?", [$name]);
    }

    public function disable(string $name): void
    {
        $record = $this->db->fetchOne("SELECT * FROM `modules` WHERE `name` = ?", [$name]);
        if (!$record) {
            throw new NotFoundException("Module '{$name}' is not installed.");
        }
        if ($record['is_core']) {
            throw new \RuntimeException("Cannot disable core module '{$name}'.");
        }

        $module = $this->resolve($name);
        if ($module) {
            $module->disable();
        }

        $this->db->execute("UPDATE `modules` SET `is_enabled` = 0 WHERE `name` = ?", [$name]);
    }

    public function getAllModules(): array
    {
        return $this->db->fetchAll("SELECT * FROM `modules` ORDER BY `name`");
    }

    public function getModule(string $name): ?array
    {
        return $this->db->fetchOne("SELECT * FROM `modules` WHERE `name` = ?", [$name]);
    }

    private function resolve(string $name): ?ModuleInterface
    {
        if (isset($this->loaded[$name])) {
            return $this->loaded[$name];
        }

        $file = $this->modulesPath . '/' . $name . '/Module.php';
        if (!file_exists($file)) {
            return null;
        }

        require_once $file;

        $className = "WebklientApp\\Modules\\{$name}\\Module";
        if (!class_exists($className)) {
            return null;
        }

        return new $className();
    }
}
