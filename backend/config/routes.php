<?php

declare(strict_types=1);

/**
 * API Route definitions.
 *
 * $router is an instance of \WebklientApp\Core\Http\Router
 */

use WebklientApp\Core\Http\JsonResponse;

// Health check (public)
$router->get('/api/health', function () {
    return JsonResponse::success([
        'status' => 'healthy',
        'timestamp' => date('c'),
        'version' => '1.0.0',
    ]);
});

// Auth routes
$router->group('/api/auth', [], function ($router) {
    $router->post('/login', [\WebklientApp\Core\Auth\AuthController::class, 'login']);
    $router->post('/logout', [\WebklientApp\Core\Auth\AuthController::class, 'logout'])
        ->middleware('auth');
    $router->post('/refresh', [\WebklientApp\Core\Auth\AuthController::class, 'refresh']);
    $router->post('/forgot-password', [\WebklientApp\Core\Http\Controllers\PasswordResetController::class, 'forgotPassword']);
    $router->post('/reset-password', [\WebklientApp\Core\Http\Controllers\PasswordResetController::class, 'resetPassword']);
});

// User routes (authenticated)
$router->group('/api', ['auth'], function ($router) {
    // Users
    $router->get('/users/me', [\WebklientApp\Core\Http\Controllers\UsersController::class, 'me']);
    $router->put('/users/me', [\WebklientApp\Core\Http\Controllers\UsersController::class, 'updateMe']);
    $router->put('/users/me/password', [\WebklientApp\Core\Http\Controllers\UsersController::class, 'changePassword']);
    $router->get('/users', [\WebklientApp\Core\Http\Controllers\UsersController::class, 'index'])
        ->permission('users.list');
    $router->get('/users/{id}', [\WebklientApp\Core\Http\Controllers\UsersController::class, 'show'])
        ->permission('users.view');
    $router->post('/users', [\WebklientApp\Core\Http\Controllers\UsersController::class, 'store'])
        ->permission('users.create');
    $router->put('/users/{id}', [\WebklientApp\Core\Http\Controllers\UsersController::class, 'update'])
        ->permission('users.update');
    $router->delete('/users/{id}', [\WebklientApp\Core\Http\Controllers\UsersController::class, 'destroy'])
        ->permission('users.delete');
    $router->get('/users/{id}/roles', [\WebklientApp\Core\Http\Controllers\UsersController::class, 'roles'])
        ->permission('users.view');
    $router->post('/users/{id}/roles', [\WebklientApp\Core\Http\Controllers\UsersController::class, 'assignRole'])
        ->permission('users.update');
    $router->delete('/users/{id}/roles/{roleId}', [\WebklientApp\Core\Http\Controllers\UsersController::class, 'removeRole'])
        ->permission('users.update');

    // Roles
    $router->get('/roles', [\WebklientApp\Core\Http\Controllers\RolesController::class, 'index'])
        ->permission('roles.list');
    $router->get('/roles/{id}', [\WebklientApp\Core\Http\Controllers\RolesController::class, 'show'])
        ->permission('roles.view');
    $router->post('/roles', [\WebklientApp\Core\Http\Controllers\RolesController::class, 'store'])
        ->permission('roles.create');
    $router->put('/roles/{id}', [\WebklientApp\Core\Http\Controllers\RolesController::class, 'update'])
        ->permission('roles.update');
    $router->delete('/roles/{id}', [\WebklientApp\Core\Http\Controllers\RolesController::class, 'destroy'])
        ->permission('roles.delete');
    $router->get('/roles/{id}/permissions', [\WebklientApp\Core\Http\Controllers\RolesController::class, 'permissions'])
        ->permission('roles.view');
    $router->post('/roles/{id}/permissions', [\WebklientApp\Core\Http\Controllers\RolesController::class, 'assignPermission'])
        ->permission('roles.update');
    $router->delete('/roles/{id}/permissions/{permissionId}', [\WebklientApp\Core\Http\Controllers\RolesController::class, 'removePermission'])
        ->permission('roles.update');

    // Permissions
    $router->get('/permissions', [\WebklientApp\Core\Http\Controllers\PermissionsController::class, 'index'])
        ->permission('permissions.list');
    $router->get('/permissions/matrix', [\WebklientApp\Core\Http\Controllers\PermissionsController::class, 'matrix'])
        ->permission('permissions.list');
    $router->get('/permissions/check', [\WebklientApp\Core\Http\Controllers\PermissionsController::class, 'check']);

    // Modules
    $router->get('/modules', [\WebklientApp\Core\Http\Controllers\ModulesController::class, 'index'])
        ->permission('modules.list');
    $router->get('/modules/{name}', [\WebklientApp\Core\Http\Controllers\ModulesController::class, 'show'])
        ->permission('modules.view');
    $router->post('/modules/{name}/enable', [\WebklientApp\Core\Http\Controllers\ModulesController::class, 'enable'])
        ->permission('modules.manage');
    $router->post('/modules/{name}/disable', [\WebklientApp\Core\Http\Controllers\ModulesController::class, 'disable'])
        ->permission('modules.manage');

    // Activity Log
    $router->get('/activity-log', [\WebklientApp\Core\Http\Controllers\ActivityLogController::class, 'index'])
        ->permission('activity_log.list');
    $router->get('/activity-log/my', [\WebklientApp\Core\Http\Controllers\ActivityLogController::class, 'my']);
    $router->get('/activity-log/stats', [\WebklientApp\Core\Http\Controllers\ActivityLogController::class, 'stats'])
        ->permission('activity_log.stats');
    $router->get('/activity-log/{id}', [\WebklientApp\Core\Http\Controllers\ActivityLogController::class, 'show'])
        ->permission('activity_log.view');

    // AI
    $router->post('/ai/chat', [\WebklientApp\Core\Http\Controllers\AIController::class, 'chat'])
        ->permission('ai.chat')->rateGroup('ai');
    $router->post('/ai/chat/stream', [\WebklientApp\Core\Http\Controllers\AIController::class, 'stream'])
        ->permission('ai.chat')->rateGroup('ai');
    $router->get('/ai/conversations', [\WebklientApp\Core\Http\Controllers\AIController::class, 'conversations'])
        ->permission('ai.chat');
    $router->get('/ai/conversations/{id}', [\WebklientApp\Core\Http\Controllers\AIController::class, 'showConversation'])
        ->permission('ai.chat');
    $router->delete('/ai/conversations/{id}', [\WebklientApp\Core\Http\Controllers\AIController::class, 'deleteConversation'])
        ->permission('ai.chat');
    $router->get('/ai/usage', [\WebklientApp\Core\Http\Controllers\AIController::class, 'usage'])
        ->permission('ai.usage');
    $router->get('/ai/models', [\WebklientApp\Core\Http\Controllers\AIController::class, 'models'])
        ->permission('ai.chat');

    // Files
    $router->post('/upload', [\WebklientApp\Core\Http\Controllers\FilesController::class, 'upload'])
        ->permission('files.upload');
    $router->get('/files/{id}', [\WebklientApp\Core\Http\Controllers\FilesController::class, 'download'])
        ->permission('files.download');
});

// Admin routes (sudo only)
$router->group('/api/admin', ['auth', 'sudo'], function ($router) {
    $router->get('/system/info', [\WebklientApp\Core\Http\Controllers\AdminController::class, 'systemInfo']);
    $router->get('/system/health', [\WebklientApp\Core\Http\Controllers\AdminController::class, 'healthCheck']);
    $router->post('/system/cache-clear', [\WebklientApp\Core\Http\Controllers\AdminController::class, 'cacheClear']);
    $router->post('/system/optimize', [\WebklientApp\Core\Http\Controllers\AdminController::class, 'optimize']);
    $router->get('/activity-log', [\WebklientApp\Core\Http\Controllers\AdminController::class, 'activityLog']);
    $router->get('/users', [\WebklientApp\Core\Http\Controllers\AdminController::class, 'allUsers']);
    $router->post('/impersonate', [\WebklientApp\Core\Http\Controllers\AdminController::class, 'impersonate']);
    $router->post('/stop-impersonate', [\WebklientApp\Core\Http\Controllers\AdminController::class, 'stopImpersonate']);
    $router->get('/permissions/matrix', [\WebklientApp\Core\Http\Controllers\AdminController::class, 'permissionMatrix']);
    $router->put('/permissions/matrix', [\WebklientApp\Core\Http\Controllers\AdminController::class, 'updatePermissionMatrix']);
    $router->post('/modules/{name}/install', [\WebklientApp\Core\Http\Controllers\AdminController::class, 'installModule']);
    $router->delete('/modules/{name}', [\WebklientApp\Core\Http\Controllers\AdminController::class, 'uninstallModule']);
    $router->post('/permissions', [\WebklientApp\Core\Http\Controllers\PermissionsController::class, 'store']);
    $router->put('/permissions/{id}', [\WebklientApp\Core\Http\Controllers\PermissionsController::class, 'update']);
    $router->delete('/permissions/{id}', [\WebklientApp\Core\Http\Controllers\PermissionsController::class, 'destroy']);
});
