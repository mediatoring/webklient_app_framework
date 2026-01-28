<?php

declare(strict_types=1);

namespace WebklientApp\Core\Module;

use WebklientApp\Core\Http\Router;

interface ModuleInterface
{
    /** Unique module identifier */
    public function getName(): string;

    /** Human-readable name */
    public function getDisplayName(): string;

    public function getDescription(): string;

    public function getVersion(): string;

    /** Register module routes */
    public function registerRoutes(Router $router): void;

    /** Return permission definitions: [['name' => ..., 'slug' => ...], ...] */
    public function getPermissions(): array;

    /** Called when module is installed */
    public function install(): void;

    /** Called when module is uninstalled */
    public function uninstall(): void;

    /** Called when module is enabled */
    public function enable(): void;

    /** Called when module is disabled */
    public function disable(): void;
}
