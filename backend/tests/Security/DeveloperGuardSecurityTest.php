<?php

declare(strict_types=1);

namespace WebklientApp\Tests\Security;

use PHPUnit\Framework\TestCase;
use WebklientApp\Core\Auth\PermissionService;

/**
 * Security tests: Developer role always has all permissions,
 * wildcard access, and sudo behavior.
 */
class DeveloperGuardSecurityTest extends TestCase
{
    public function testDeveloperRoleHasWildcard(): void
    {
        $db = $this->createMockDb(isDeveloper: true);
        $service = new PermissionService($db);

        $perms = $service->getUserPermissions(1);
        $this->assertContains('*', $perms, 'Developer should have wildcard permission');
    }

    public function testDeveloperPassesAnyPermissionCheck(): void
    {
        $db = $this->createMockDb(isDeveloper: true);
        $service = new PermissionService($db);

        $this->assertTrue($service->userHasPermission(1, 'users.delete'));
        $this->assertTrue($service->userHasPermission(1, 'any.random.permission'));
        $this->assertTrue($service->userHasPermission(1, 'nonexistent.slug'));
    }

    public function testDeveloperPassesAllPermissionsCheck(): void
    {
        $db = $this->createMockDb(isDeveloper: true);
        $service = new PermissionService($db);

        $this->assertTrue($service->userHasAllPermissions(1, [
            'users.create', 'users.delete', 'roles.manage', 'admin.full',
        ]));
    }

    public function testDeveloperIsSudo(): void
    {
        $db = $this->createMockDb(isDeveloper: true);
        $service = new PermissionService($db);

        $this->assertTrue($service->isSudo(1));
    }

    public function testNonDeveloperIsNotSudo(): void
    {
        $db = $this->createMockDb(isDeveloper: false, permissions: ['users.list', 'users.view']);
        $service = new PermissionService($db);

        $this->assertFalse($service->isSudo(2));
    }

    public function testNonDeveloperDoesNotHaveWildcard(): void
    {
        $db = $this->createMockDb(isDeveloper: false, permissions: ['users.list']);
        $service = new PermissionService($db);

        $perms = $service->getUserPermissions(2);
        $this->assertNotContains('*', $perms);
    }

    public function testNonDeveloperCannotAccessArbitraryPermission(): void
    {
        $db = $this->createMockDb(isDeveloper: false, permissions: ['users.list']);
        $service = new PermissionService($db);

        $this->assertTrue($service->userHasPermission(2, 'users.list'));
        $this->assertFalse($service->userHasPermission(2, 'users.delete'));
        $this->assertFalse($service->userHasPermission(2, 'admin.full'));
    }

    public function testNonDeveloperFailsAllPermissionsIfMissing(): void
    {
        $db = $this->createMockDb(isDeveloper: false, permissions: ['users.list']);
        $service = new PermissionService($db);

        $this->assertFalse($service->userHasAllPermissions(2, ['users.list', 'users.delete']));
    }

    public function testPermissionCacheIsolation(): void
    {
        $db = $this->createMockDb(isDeveloper: true);
        $service = new PermissionService($db);

        $perms1 = $service->getUserPermissions(1);
        $this->assertContains('*', $perms1);

        PermissionService::clearCache();

        $db2 = $this->createMockDb(isDeveloper: false, permissions: ['files.upload']);
        $service2 = new PermissionService($db2);

        $perms2 = $service2->getUserPermissions(2);
        $this->assertNotContains('*', $perms2);
        $this->assertContains('files.upload', $perms2);
    }

    public function testClearCacheForSpecificUser(): void
    {
        PermissionService::clearCache();

        $db = $this->createMockDb(isDeveloper: true);
        $service = new PermissionService($db);
        $service->getUserPermissions(1);

        PermissionService::clearCache(1);

        $perms = $service->getUserPermissions(1);
        $this->assertContains('*', $perms);
    }

    public function testClearAllCache(): void
    {
        PermissionService::clearCache();
        $this->assertTrue(true, 'Full cache clear should not throw');
    }

    private function createMockDb(bool $isDeveloper = false, array $permissions = []): \WebklientApp\Core\Database\Connection
    {
        PermissionService::clearCache();

        $mock = $this->createMock(\WebklientApp\Core\Database\Connection::class);

        $callIndex = 0;

        $mock->method('fetchOne')->willReturnCallback(
            function (string $sql, array $params = []) use ($isDeveloper, &$callIndex) {
                if (str_contains($sql, 'developer')) {
                    return $isDeveloper ? ['1' => 1] : null;
                }
                return null;
            }
        );

        $mock->method('fetchAll')->willReturnCallback(
            function (string $sql) use ($isDeveloper, $permissions) {
                if ($isDeveloper && str_contains($sql, 'SELECT `slug` FROM `permissions`')) {
                    return array_map(fn($s) => ['slug' => $s], ['users.list', 'users.view', 'users.create', 'users.delete', 'roles.manage']);
                }
                if (!$isDeveloper && str_contains($sql, 'DISTINCT p.slug')) {
                    return array_map(fn($s) => ['slug' => $s], $permissions);
                }
                return [];
            }
        );

        return $mock;
    }
}
