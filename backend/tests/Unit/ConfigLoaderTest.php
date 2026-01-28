<?php

declare(strict_types=1);

namespace WebklientApp\Tests\Unit;

use PHPUnit\Framework\TestCase;
use WebklientApp\Core\ConfigLoader;

class ConfigLoaderTest extends TestCase
{
    private string $envFile;

    protected function setUp(): void
    {
        ConfigLoader::reset();

        $this->envFile = sys_get_temp_dir() . '/webklient_test_' . uniqid() . '.env';
        file_put_contents($this->envFile, implode("\n", [
            'APP_NAME=TestApp',
            'APP_ENV=testing',
            'APP_DEBUG=true',
            'APP_KEY=test-key-123',
            '# Comment line',
            '',
            'DB_HOST=localhost',
            'QUOTED_VAR="hello world"',
        ]));
    }

    protected function tearDown(): void
    {
        ConfigLoader::reset();
        if (file_exists($this->envFile)) {
            unlink($this->envFile);
        }
    }

    public function testLoadsEnvFile(): void
    {
        $config = ConfigLoader::getInstance($this->envFile);

        $this->assertSame('TestApp', $config->env('APP_NAME'));
        $this->assertSame('testing', $config->env('APP_ENV'));
        $this->assertSame('localhost', $config->env('DB_HOST'));
    }

    public function testQuotedValues(): void
    {
        $config = ConfigLoader::getInstance($this->envFile);
        $this->assertSame('hello world', $config->env('QUOTED_VAR'));
    }

    public function testDefaultValues(): void
    {
        $config = ConfigLoader::getInstance($this->envFile);
        $this->assertSame('default', $config->env('NONEXISTENT', 'default'));
        $this->assertNull($config->env('NONEXISTENT'));
    }

    public function testIsDebug(): void
    {
        $config = ConfigLoader::getInstance($this->envFile);
        $this->assertTrue($config->isDebug());
    }

    public function testDotNotationConfig(): void
    {
        $config = ConfigLoader::getInstance($this->envFile);
        $config->set('database.host', 'db-server');
        $config->set('database.port', 3306);

        $this->assertSame('db-server', $config->get('database.host'));
        $this->assertSame(3306, $config->get('database.port'));
        $this->assertSame('default', $config->get('database.missing', 'default'));
    }

    public function testRequireThrowsOnMissing(): void
    {
        $config = ConfigLoader::getInstance($this->envFile);
        $this->expectException(\RuntimeException::class);
        $config->require('TOTALLY_MISSING_VAR');
    }

    public function testRequirePassesForExisting(): void
    {
        $config = ConfigLoader::getInstance($this->envFile);
        $config->require('APP_NAME', 'APP_KEY'); // Should not throw
        $this->assertTrue(true);
    }

    public function testMissingEnvFileThrows(): void
    {
        $this->expectException(\RuntimeException::class);
        ConfigLoader::getInstance('/nonexistent/.env');
    }
}
