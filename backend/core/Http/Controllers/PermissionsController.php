<?php

declare(strict_types=1);

namespace WebklientApp\Core\Http\Controllers;

use WebklientApp\Core\Http\Request;
use WebklientApp\Core\Http\JsonResponse;
use WebklientApp\Core\Exceptions\NotFoundException;
use WebklientApp\Core\Exceptions\ValidationException;
use WebklientApp\Core\Validation\Validator;

class PermissionsController extends BaseController
{
    public function index(Request $request): JsonResponse
    {
        $permissions = $this->query->table('permissions')->orderBy('module')->orderBy('slug')->get();

        // Group by module
        $grouped = [];
        foreach ($permissions as $perm) {
            $grouped[$perm['module']][] = $perm;
        }

        return JsonResponse::success($grouped);
    }

    public function matrix(Request $request): JsonResponse
    {
        $roles = $this->query->table('roles')->orderBy('name')->get();
        $permissions = $this->query->table('permissions')->orderBy('module')->orderBy('slug')->get();

        $rolePermissions = $this->db->fetchAll("SELECT role_id, permission_id FROM role_permissions");
        $map = [];
        foreach ($rolePermissions as $rp) {
            $map[$rp['role_id']][$rp['permission_id']] = true;
        }

        $matrix = [];
        foreach ($roles as $role) {
            $row = [
                'role' => $role,
                'permissions' => [],
            ];
            foreach ($permissions as $perm) {
                $row['permissions'][] = [
                    'id' => $perm['id'],
                    'slug' => $perm['slug'],
                    'granted' => isset($map[$role['id']][$perm['id']]),
                ];
            }
            $matrix[] = $row;
        }

        return JsonResponse::success($matrix);
    }

    public function check(Request $request): JsonResponse
    {
        $userId = $request->getAttribute('user_id');
        $permission = $request->get('permission', '');

        if ($permission === '') {
            throw new ValidationException('permission query parameter is required.');
        }

        $has = $this->db->fetchOne(
            "SELECT 1 FROM role_permissions rp
             JOIN user_roles ur ON ur.role_id = rp.role_id
             JOIN permissions p ON p.id = rp.permission_id
             WHERE ur.user_id = ? AND p.slug = ?",
            [$userId, $permission]
        );

        // Also check if user is developer (sudo)
        $isSudo = (bool) $this->db->fetchOne(
            "SELECT 1 FROM user_roles ur JOIN roles r ON r.id = ur.role_id WHERE ur.user_id = ? AND r.slug = 'developer'",
            [$userId]
        );

        return JsonResponse::success([
            'permission' => $permission,
            'granted' => $isSudo || (bool) $has,
        ]);
    }

    // Sudo-only endpoints
    public function store(Request $request): JsonResponse
    {
        $data = $request->input();
        $errors = Validator::validate($data, [
            'name' => 'required|min:2|max:100',
            'slug' => 'required|min:2|max:100',
            'module' => 'required|max:50',
        ]);
        if (!empty($errors)) {
            throw new ValidationException('Validation failed.', $errors);
        }

        $exists = $this->query->table('permissions')->where('slug', $data['slug'])->exists();
        if ($exists) {
            throw new ValidationException('Validation failed.', ['slug' => ['Permission slug already exists.']]);
        }

        $id = $this->query->table('permissions')->insert([
            'name' => $data['name'],
            'slug' => $data['slug'],
            'module' => $data['module'],
            'is_system' => 0,
        ]);

        $perm = $this->query->table('permissions')->where('id', (int) $id)->first();
        return JsonResponse::created($perm);
    }

    public function update(Request $request): JsonResponse
    {
        $id = (int) $request->param('id');
        $perm = $this->query->table('permissions')->where('id', $id)->first();
        if (!$perm) {
            throw new NotFoundException('Permission not found.');
        }
        if ($perm['is_system']) {
            throw new ValidationException('Cannot modify system permission.');
        }

        $data = $request->input();
        $update = [];
        foreach (['name', 'description'] as $field) {
            if (isset($data[$field])) {
                $update[$field] = $data[$field];
            }
        }

        if (!empty($update)) {
            $this->query->table('permissions')->where('id', $id)->update($update);
        }

        return JsonResponse::success($this->query->table('permissions')->where('id', $id)->first());
    }

    public function destroy(Request $request): JsonResponse
    {
        $id = (int) $request->param('id');
        $perm = $this->query->table('permissions')->where('id', $id)->first();
        if (!$perm) {
            throw new NotFoundException('Permission not found.');
        }
        if ($perm['is_system']) {
            throw new ValidationException('Cannot delete system permission.');
        }

        $this->db->execute("DELETE FROM role_permissions WHERE permission_id = ?", [$id]);
        $this->query->table('permissions')->where('id', $id)->delete();

        return JsonResponse::success(null, 'Permission deleted.');
    }
}
