<?php

declare(strict_types=1);

namespace WebklientApp\Core\Http\Controllers;

use WebklientApp\Core\Http\Request;
use WebklientApp\Core\Http\JsonResponse;
use WebklientApp\Core\Module\ModuleManager;

class ModulesController extends BaseController
{
    private ModuleManager $modules;

    public function __construct()
    {
        parent::__construct();
        $this->modules = new ModuleManager($this->db, dirname(__DIR__, 3) . '/modules');
    }

    public function index(Request $request): JsonResponse
    {
        return JsonResponse::success($this->modules->getAllModules());
    }

    public function show(Request $request): JsonResponse
    {
        $name = $request->param('name');
        $module = $this->modules->getModule($name);
        if (!$module) {
            throw new \WebklientApp\Core\Exceptions\NotFoundException("Module not found.");
        }

        // Include permissions
        $permissions = $this->db->fetchAll(
            "SELECT id, name, slug FROM permissions WHERE module = ?",
            [$name]
        );
        $module['permissions'] = $permissions;

        return JsonResponse::success($module);
    }

    public function enable(Request $request): JsonResponse
    {
        $this->modules->enable($request->param('name'));
        return JsonResponse::success(null, 'Module enabled.');
    }

    public function disable(Request $request): JsonResponse
    {
        $this->modules->disable($request->param('name'));
        return JsonResponse::success(null, 'Module disabled.');
    }
}
