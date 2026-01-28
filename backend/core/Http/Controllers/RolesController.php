<?php

declare(strict_types=1);

namespace WebklientApp\Core\Http\Controllers;

use WebklientApp\Core\Http\Request;
use WebklientApp\Core\Http\JsonResponse;
use WebklientApp\Core\Exceptions\NotFoundException;
use WebklientApp\Core\Exceptions\ValidationException;
use WebklientApp\Core\Validation\Validator;

class RolesController extends BaseController
{
    public function index(Request $request): JsonResponse
    {
        $roles = $this->query->table('roles')->orderBy('name')->get();
        return JsonResponse::success($roles);
    }

    public function show(Request $request): JsonResponse
    {
        $role = $this->findRole((int) $request->param('id'));

        $permissions = $this->db->fetchAll(
            "SELECT p.id, p.name, p.slug, p.module FROM permissions p JOIN role_permissions rp ON rp.permission_id = p.id WHERE rp.role_id = ?",
            [$role['id']]
        );
        $role['permissions'] = $permissions;

        return JsonResponse::success($role);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->input();
        $errors = Validator::validate($data, [
            'name' => 'required|min:2|max:100',
            'slug' => 'required|min:2|max:100',
            'description' => 'max:255',
        ]);
        if (!empty($errors)) {
            throw new ValidationException('Validation failed.', $errors);
        }

        $exists = $this->query->table('roles')->where('slug', $data['slug'])->exists();
        if ($exists) {
            throw new ValidationException('Validation failed.', ['slug' => ['Slug already exists.']]);
        }

        $id = $this->query->table('roles')->insert([
            'name' => $data['name'],
            'slug' => $data['slug'],
            'description' => $data['description'] ?? '',
            'is_system' => 0,
        ]);

        return JsonResponse::created($this->findRole((int) $id), "/api/roles/{$id}");
    }

    public function update(Request $request): JsonResponse
    {
        $id = (int) $request->param('id');
        $role = $this->findRole($id);

        if ($role['is_system']) {
            throw new ValidationException('Cannot modify system role.');
        }

        $data = $request->input();
        $update = [];
        foreach (['name', 'description'] as $field) {
            if (isset($data[$field])) {
                $update[$field] = $data[$field];
            }
        }

        if (!empty($update)) {
            $this->query->table('roles')->where('id', $id)->update($update);
        }

        return JsonResponse::success($this->findRole($id));
    }

    public function destroy(Request $request): JsonResponse
    {
        $id = (int) $request->param('id');
        $role = $this->findRole($id);

        if ($role['is_system']) {
            throw new ValidationException('Cannot delete system role.');
        }

        $this->db->execute("DELETE FROM role_permissions WHERE role_id = ?", [$id]);
        $this->db->execute("DELETE FROM user_roles WHERE role_id = ?", [$id]);
        $this->query->table('roles')->where('id', $id)->delete();

        return JsonResponse::success(null, 'Role deleted.');
    }

    public function permissions(Request $request): JsonResponse
    {
        $id = (int) $request->param('id');
        $this->findRole($id);

        $permissions = $this->db->fetchAll(
            "SELECT p.id, p.name, p.slug, p.module FROM permissions p JOIN role_permissions rp ON rp.permission_id = p.id WHERE rp.role_id = ? ORDER BY p.module, p.slug",
            [$id]
        );

        return JsonResponse::success($permissions);
    }

    public function assignPermission(Request $request): JsonResponse
    {
        $roleId = (int) $request->param('id');
        $this->findRole($roleId);

        $permId = (int) ($request->input()['permission_id'] ?? 0);
        $perm = $this->query->table('permissions')->where('id', $permId)->first();
        if (!$perm) {
            throw new NotFoundException('Permission not found.');
        }

        $exists = $this->db->fetchOne(
            "SELECT 1 FROM role_permissions WHERE role_id = ? AND permission_id = ?",
            [$roleId, $permId]
        );
        if (!$exists) {
            $this->db->execute(
                "INSERT INTO role_permissions (role_id, permission_id) VALUES (?, ?)",
                [$roleId, $permId]
            );
        }

        return JsonResponse::success(null, 'Permission assigned.');
    }

    public function removePermission(Request $request): JsonResponse
    {
        $roleId = (int) $request->param('id');
        $permId = (int) $request->param('permissionId');

        $this->db->execute(
            "DELETE FROM role_permissions WHERE role_id = ? AND permission_id = ?",
            [$roleId, $permId]
        );

        return JsonResponse::success(null, 'Permission removed.');
    }

    public function users(Request $request): JsonResponse
    {
        $id = (int) $request->param('id');
        $this->findRole($id);

        $users = $this->db->fetchAll(
            "SELECT u.id, u.username, u.email, u.display_name FROM users u JOIN user_roles ur ON ur.user_id = u.id WHERE ur.role_id = ? AND u.is_active = 1",
            [$id]
        );

        return JsonResponse::success($users);
    }

    private function findRole(int $id): array
    {
        $role = $this->query->table('roles')->where('id', $id)->first();
        if (!$role) {
            throw new NotFoundException('Role not found.');
        }
        return $role;
    }
}
