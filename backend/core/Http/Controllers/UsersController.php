<?php

declare(strict_types=1);

namespace WebklientApp\Core\Http\Controllers;

use WebklientApp\Core\Http\Request;
use WebklientApp\Core\Http\JsonResponse;
use WebklientApp\Core\Exceptions\NotFoundException;
use WebklientApp\Core\Exceptions\ValidationException;
use WebklientApp\Core\Security\Hash;
use WebklientApp\Core\Validation\Validator;

class UsersController extends BaseController
{
    public function index(Request $request): JsonResponse
    {
        $p = $this->paginationParams($request);
        $q = $this->query->table('users')->select('id', 'username', 'email', 'display_name', 'is_active', 'created_at');

        if ($search = $request->get('search')) {
            $q = $q->where('username', 'LIKE', "%{$search}%")
                    ->orWhere('email', 'LIKE', "%{$search}%")
                    ->orWhere('display_name', 'LIKE', "%{$search}%");
        }

        $result = $q->orderBy($p['sort'], $p['order'])->paginate($p['page'], $p['per_page']);

        return JsonResponse::paginated($result['items'], $result['total'], $result['page'], $result['per_page']);
    }

    public function show(Request $request): JsonResponse
    {
        $user = $this->findUser((int) $request->param('id'));
        unset($user['password_hash']);
        return JsonResponse::success($user);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->input();

        $errors = Validator::validate($data, [
            'username' => 'required|min:3|max:50',
            'email' => 'required|email|max:255',
            'password' => 'required|min:8|max:255',
            'display_name' => 'max:100',
        ]);

        if (!empty($errors)) {
            throw new ValidationException('Validation failed.', $errors);
        }

        // Check uniqueness
        $exists = $this->query->table('users')->where('email', $data['email'])->exists();
        if ($exists) {
            throw new ValidationException('Validation failed.', ['email' => ['Email already exists.']]);
        }
        $exists = $this->query->table('users')->where('username', $data['username'])->exists();
        if ($exists) {
            throw new ValidationException('Validation failed.', ['username' => ['Username already exists.']]);
        }

        $id = $this->query->table('users')->insert([
            'username' => $data['username'],
            'email' => strtolower($data['email']),
            'password_hash' => Hash::make($data['password']),
            'display_name' => $data['display_name'] ?? $data['username'],
            'is_active' => 1,
        ]);

        $user = $this->findUser((int) $id);
        unset($user['password_hash']);

        return JsonResponse::created($user, "/api/users/{$id}");
    }

    public function update(Request $request): JsonResponse
    {
        $id = (int) $request->param('id');
        $this->findUser($id);

        $data = $request->input();
        $errors = Validator::validate($data, [
            'username' => 'min:3|max:50',
            'email' => 'email|max:255',
            'display_name' => 'max:100',
            'is_active' => 'boolean',
        ]);

        if (!empty($errors)) {
            throw new ValidationException('Validation failed.', $errors);
        }

        $update = [];
        foreach (['username', 'email', 'display_name', 'is_active'] as $field) {
            if (isset($data[$field])) {
                $update[$field] = $field === 'email' ? strtolower($data[$field]) : $data[$field];
            }
        }

        if (!empty($update)) {
            $this->query->table('users')->where('id', $id)->update($update);
        }

        $user = $this->findUser($id);
        unset($user['password_hash']);
        return JsonResponse::success($user);
    }

    public function destroy(Request $request): JsonResponse
    {
        $id = (int) $request->param('id');
        $this->findUser($id);

        // Soft delete
        $this->query->table('users')->where('id', $id)->update(['is_active' => 0]);

        return JsonResponse::success(null, 'User deactivated.');
    }

    public function me(Request $request): JsonResponse
    {
        $userId = $request->getAttribute('user_id');
        $user = $this->findUser($userId);
        unset($user['password_hash']);

        // Include roles
        $roles = $this->db->fetchAll(
            "SELECT r.id, r.name, r.slug FROM roles r JOIN user_roles ur ON ur.role_id = r.id WHERE ur.user_id = ?",
            [$userId]
        );
        $user['roles'] = $roles;

        return JsonResponse::success($user);
    }

    public function updateMe(Request $request): JsonResponse
    {
        $userId = $request->getAttribute('user_id');
        $data = $request->input();

        $update = [];
        if (isset($data['display_name'])) {
            $update['display_name'] = $data['display_name'];
        }
        if (isset($data['email'])) {
            $update['email'] = strtolower($data['email']);
        }

        if (!empty($update)) {
            $this->query->table('users')->where('id', $userId)->update($update);
        }

        $user = $this->findUser($userId);
        unset($user['password_hash']);
        return JsonResponse::success($user);
    }

    public function changePassword(Request $request): JsonResponse
    {
        $userId = $request->getAttribute('user_id');
        $data = $request->input();

        $errors = Validator::validate($data, [
            'current_password' => 'required',
            'password' => 'required|min:8|max:255',
        ]);
        if (!empty($errors)) {
            throw new ValidationException('Validation failed.', $errors);
        }

        $user = $this->findUser($userId);
        if (!Hash::verify($data['current_password'], $user['password_hash'])) {
            throw new ValidationException('Validation failed.', ['current_password' => ['Current password is incorrect.']]);
        }

        $this->query->table('users')->where('id', $userId)->update([
            'password_hash' => Hash::make($data['password']),
        ]);

        return JsonResponse::success(null, 'Password changed.');
    }

    public function roles(Request $request): JsonResponse
    {
        $id = (int) $request->param('id');
        $this->findUser($id);

        $roles = $this->db->fetchAll(
            "SELECT r.id, r.name, r.slug, r.description FROM roles r JOIN user_roles ur ON ur.role_id = r.id WHERE ur.user_id = ?",
            [$id]
        );

        return JsonResponse::success($roles);
    }

    public function assignRole(Request $request): JsonResponse
    {
        $userId = (int) $request->param('id');
        $this->findUser($userId);

        $roleId = (int) ($request->input()['role_id'] ?? 0);
        $role = $this->query->table('roles')->where('id', $roleId)->first();
        if (!$role) {
            throw new NotFoundException('Role not found.');
        }

        $exists = $this->db->fetchOne(
            "SELECT 1 FROM user_roles WHERE user_id = ? AND role_id = ?",
            [$userId, $roleId]
        );
        if (!$exists) {
            $this->db->execute(
                "INSERT INTO user_roles (user_id, role_id) VALUES (?, ?)",
                [$userId, $roleId]
            );
        }

        return JsonResponse::success(null, 'Role assigned.');
    }

    public function removeRole(Request $request): JsonResponse
    {
        $userId = (int) $request->param('id');
        $roleId = (int) $request->param('roleId');

        $this->db->execute(
            "DELETE FROM user_roles WHERE user_id = ? AND role_id = ?",
            [$userId, $roleId]
        );

        return JsonResponse::success(null, 'Role removed.');
    }

    private function findUser(int $id): array
    {
        $user = $this->query->table('users')->where('id', $id)->first();
        if (!$user) {
            throw new NotFoundException('User not found.');
        }
        return $user;
    }
}
