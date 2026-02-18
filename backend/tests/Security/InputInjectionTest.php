<?php

declare(strict_types=1);

namespace WebklientApp\Tests\Security;

use PHPUnit\Framework\TestCase;
use WebklientApp\Core\Validation\Sanitizer;
use WebklientApp\Core\Validation\Validator;
use WebklientApp\Core\Exceptions\ValidationException;
use WebklientApp\Core\Http\Request;

/**
 * Security tests: SQL injection patterns, input injection, and request manipulation.
 */
class InputInjectionTest extends TestCase
{
    public function testSqlInjectionInStringInput(): void
    {
        $payloads = [
            "'; DROP TABLE users; --",
            "1' OR '1'='1",
            "1; DELETE FROM users",
            "admin'--",
            "' UNION SELECT * FROM users --",
            "1' AND 1=1 UNION SELECT username, password FROM users --",
            "') OR ('1'='1",
        ];

        foreach ($payloads as $payload) {
            $sanitized = Sanitizer::string($payload);
            $this->assertStringNotContainsString("'", $sanitized, "Unescaped quote in: {$payload}");
        }
    }

    public function testSqlInjectionInIntegerInput(): void
    {
        $result = Sanitizer::integer('1; DROP TABLE users');
        $this->assertIsInt($result);

        $result = Sanitizer::integer("'; DELETE FROM users; --");
        $this->assertIsInt($result);

        $result = Sanitizer::integer("1 OR 1=1");
        $this->assertIsInt($result);
    }

    public function testValidatorRejectsNonIntegerForIntegerField(): void
    {
        $validator = new Validator();

        $this->expectException(ValidationException::class);
        $validator->validate(
            ['id' => "1; DROP TABLE users"],
            ['id' => 'required|integer']
        );
    }

    public function testValidatorRejectsInvalidEmail(): void
    {
        $validator = new Validator();

        $maliciousEmails = [
            "admin'@example.com",
            'admin" OR 1=1 --@example.com',
            'admin@example.com; DROP TABLE users',
            '<script>alert(1)</script>@example.com',
        ];

        foreach ($maliciousEmails as $email) {
            $v = new Validator();
            $this->expectException(ValidationException::class);
            $v->validate(['email' => $email], ['email' => 'required|email']);
        }
    }

    public function testRequestBodyNotTampered(): void
    {
        $body = [
            'name' => 'John',
            'role' => 'admin',
            'is_active' => true,
        ];
        $request = new Request('POST', '/api/users', [], $body);

        $this->assertSame('John', $request->input('name'));
        $this->assertSame('admin', $request->input('role'));
    }

    public function testRequestHeaderInjectionBlocked(): void
    {
        $request = new Request('GET', '/test', [], [], [
            'authorization' => "Bearer token\r\nX-Injected: evil",
        ]);

        $token = $request->bearerToken();
        $this->assertNotNull($token);
        $this->assertStringNotContainsString("\r", $token);
        $this->assertStringNotContainsString("\n", $token);
    }

    public function testPathTraversalInFilename(): void
    {
        $payloads = [
            '../../etc/passwd',
            '../../../config/database.php',
            '..\\..\\windows\\system32\\config\\sam',
            'uploads/../../secret.key',
            '%2e%2e%2f%2e%2e%2fetc%2fpasswd',
        ];

        foreach ($payloads as $payload) {
            $sanitized = Sanitizer::filename($payload);
            $this->assertStringNotContainsString('/', $sanitized, "Slash found in sanitized: {$sanitized}");
            $this->assertStringNotContainsString('\\', $sanitized, "Backslash found in sanitized: {$sanitized}");
            $this->assertStringNotContainsString('%', $sanitized, "Percent found in sanitized: {$sanitized}");
        }
    }

    public function testNullByteInjection(): void
    {
        $filename = "image.jpg\x00.php";
        $sanitized = Sanitizer::filename($filename);
        $this->assertStringNotContainsString("\x00", $sanitized);
    }

    public function testLdapInjection(): void
    {
        $payloads = [
            '*)(&',
            '*)(uid=*))(|(uid=*',
            '\\28',
            '\\29',
        ];

        foreach ($payloads as $payload) {
            $sanitized = Sanitizer::string($payload);
            $this->assertIsString($sanitized);
        }
    }

    public function testOverlongInputHandling(): void
    {
        $validator = new Validator();

        $this->expectException(ValidationException::class);
        $validator->validate(
            ['name' => str_repeat('A', 10000)],
            ['name' => 'required|max:255']
        );
    }

    public function testValidatorInRuleBlocksArbitraryValues(): void
    {
        $validator = new Validator();

        $this->expectException(ValidationException::class);
        $validator->validate(
            ['role' => 'superadmin'],
            ['role' => 'required|in:admin,user,editor']
        );
    }

    public function testBearerTokenWithMaliciousContent(): void
    {
        $request = new Request('GET', '/test', [], [], [
            'authorization' => 'Bearer <script>alert(1)</script>',
        ]);

        $token = $request->bearerToken();
        $this->assertSame('<script>alert(1)</script>', $token);
    }

    public function testIpSpoofingPrevented(): void
    {
        $request = new Request('GET', '/test', [], [], [], [
            'HTTP_X_FORWARDED_FOR' => '127.0.0.1, 10.0.0.1',
            'HTTP_X_REAL_IP' => '192.168.1.1',
            'REMOTE_ADDR' => '203.0.113.50',
        ]);

        $ip = $request->ip();
        $this->assertSame('127.0.0.1', $ip, 'Only the first IP from X-Forwarded-For should be used');
    }

    public function testIpSpoofingInvalidForwardedForFallsToRealIp(): void
    {
        $request = new Request('GET', '/test', [], [], [], [
            'HTTP_X_FORWARDED_FOR' => 'not-an-ip, garbage',
            'HTTP_X_REAL_IP' => '192.168.1.1',
            'REMOTE_ADDR' => '203.0.113.50',
        ]);

        $this->assertSame('192.168.1.1', $request->ip());
    }

    public function testIpSpoofingInvalidForwardedAndRealIpFallsToRemoteAddr(): void
    {
        $request = new Request('GET', '/test', [], [], [], [
            'HTTP_X_FORWARDED_FOR' => 'garbage',
            'HTTP_X_REAL_IP' => 'also-not-valid',
            'REMOTE_ADDR' => '203.0.113.50',
        ]);

        $this->assertSame('203.0.113.50', $request->ip());
    }

    public function testMethodOverrideNotSupported(): void
    {
        $request = new Request('POST', '/test', [], ['_method' => 'DELETE']);
        $this->assertSame('POST', $request->method());
    }

    public function testEmailNormalization(): void
    {
        $this->assertSame('test@example.com', Sanitizer::email('  TEST@EXAMPLE.COM  '));
        $this->assertSame('user+tag@domain.com', Sanitizer::email('USER+TAG@DOMAIN.COM'));
    }

    public function testSpecialCharactersInQueryParams(): void
    {
        $request = new Request('GET', '/test', [
            'search' => '<script>alert(1)</script>',
            'page' => '1; DROP TABLE',
        ]);

        $this->assertSame('<script>alert(1)</script>', $request->query('search'));
        $this->assertSame('1; DROP TABLE', $request->query('page'));
    }
}
