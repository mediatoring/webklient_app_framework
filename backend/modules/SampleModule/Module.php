<?php

declare(strict_types=1);

namespace WebklientApp\Modules\SampleModule;

use WebklientApp\Core\Module\ModuleInterface;
use WebklientApp\Core\Http\Router;
use WebklientApp\Core\Http\JsonResponse;

/**
 * Sample module serving as a template for creating new modules.
 *
 * To create a new module:
 * 1. Create a directory under modules/ (e.g., modules/MyModule)
 * 2. Create Module.php implementing ModuleInterface
 * 3. Install via: POST /api/admin/modules/MyModule/install
 * 4. Enable via: POST /api/modules/MyModule/enable
 */
class Module implements ModuleInterface
{
    public function getName(): string
    {
        return 'SampleModule';
    }

    public function getDisplayName(): string
    {
        return 'Sample Module';
    }

    public function getDescription(): string
    {
        return 'A sample module demonstrating the module system. Use as a template for new modules.';
    }

    public function getVersion(): string
    {
        return '1.0.0';
    }

    public function getPermissions(): array
    {
        return [
            ['name' => 'Sample List', 'slug' => 'sample.list'],
            ['name' => 'Sample Create', 'slug' => 'sample.create'],
        ];
    }

    public function registerRoutes(Router $router): void
    {
        $router->group('/api/sample', ['auth'], function (Router $r) {
            $r->get('', function () {
                return JsonResponse::success([
                    'message' => 'Sample module is working!',
                    'items' => [],
                ]);
            })->permission('sample.list');

            $r->post('', function (\WebklientApp\Core\Http\Request $request) {
                return JsonResponse::created([
                    'message' => 'Sample item created',
                    'data' => $request->input(),
                ]);
            })->permission('sample.create');
        });
    }

    public function install(): void
    {
        // Run module-specific migrations or setup here
    }

    public function uninstall(): void
    {
        // Clean up module data here
    }

    public function enable(): void
    {
        // Called when module is enabled
    }

    public function disable(): void
    {
        // Called when module is disabled
    }
}
