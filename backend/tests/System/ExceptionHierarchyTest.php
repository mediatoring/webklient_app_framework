<?php

declare(strict_types=1);

namespace WebklientApp\Tests\System;

use PHPUnit\Framework\TestCase;
use WebklientApp\Core\Exceptions\AppException;
use WebklientApp\Core\Exceptions\AuthenticationException;
use WebklientApp\Core\Exceptions\AuthorizationException;
use WebklientApp\Core\Exceptions\NotFoundException;
use WebklientApp\Core\Exceptions\RateLimitException;
use WebklientApp\Core\Exceptions\ValidationException;

/**
 * System tests: Exception hierarchy, status codes, and error codes.
 */
class ExceptionHierarchyTest extends TestCase
{
    public function testAllExceptionsExtendAppException(): void
    {
        $exceptions = [
            new AuthenticationException('test'),
            new AuthorizationException('test'),
            new NotFoundException('test'),
            new RateLimitException('test'),
            new ValidationException('test'),
        ];

        foreach ($exceptions as $e) {
            $this->assertInstanceOf(AppException::class, $e);
            $this->assertInstanceOf(\RuntimeException::class, $e);
        }
    }

    public function testAuthenticationExceptionCodes(): void
    {
        $e = new AuthenticationException('Invalid credentials.');
        $this->assertSame(401, $e->getStatusCode());
        $this->assertSame('AUTHENTICATION_ERROR', $e->getErrorCode());
        $this->assertSame('Invalid credentials.', $e->getMessage());
    }

    public function testAuthorizationExceptionCodes(): void
    {
        $e = new AuthorizationException('Insufficient permissions.');
        $this->assertSame(403, $e->getStatusCode());
        $this->assertSame('AUTHORIZATION_ERROR', $e->getErrorCode());
    }

    public function testNotFoundExceptionCodes(): void
    {
        $e = new NotFoundException('Resource not found.');
        $this->assertSame(404, $e->getStatusCode());
        $this->assertSame('NOT_FOUND', $e->getErrorCode());
    }

    public function testRateLimitExceptionCodes(): void
    {
        $e = new RateLimitException('Too many requests.');
        $this->assertSame(429, $e->getStatusCode());
        $this->assertSame('RATE_LIMIT_EXCEEDED', $e->getErrorCode());
    }

    public function testValidationExceptionCodes(): void
    {
        $e = new ValidationException('Validation failed.');
        $this->assertSame(400, $e->getStatusCode());
        $this->assertSame('VALIDATION_ERROR', $e->getErrorCode());
    }

    public function testExceptionWithDetails(): void
    {
        $details = [
            ['field' => 'email', 'rule' => 'required', 'message' => 'Email is required'],
            ['field' => 'name', 'rule' => 'min', 'message' => 'Name too short'],
        ];
        $e = new ValidationException('Validation failed.', $details);

        $this->assertSame($details, $e->getDetails());
        $this->assertCount(2, $e->getDetails());
    }

    public function testExceptionWithEmptyDetails(): void
    {
        $e = new AuthenticationException('Bad token.');
        $this->assertSame([], $e->getDetails());
    }

    public function testExceptionChaining(): void
    {
        $original = new \RuntimeException('DB error');
        $e = new AuthenticationException('Auth failed.', [], 0, $original);

        $this->assertSame($original, $e->getPrevious());
        $this->assertSame('DB error', $e->getPrevious()->getMessage());
    }

    public function testExceptionStatusCodesAreStandard(): void
    {
        $map = [
            AuthenticationException::class => 401,
            AuthorizationException::class => 403,
            NotFoundException::class => 404,
            RateLimitException::class => 429,
            ValidationException::class => 400,
        ];

        foreach ($map as $class => $expectedCode) {
            $e = new $class('test');
            $this->assertSame($expectedCode, $e->getStatusCode(), "Wrong status code for {$class}");
        }
    }

    public function testExceptionCanBeCaughtByAppException(): void
    {
        $caught = false;
        try {
            throw new NotFoundException('Not found');
        } catch (AppException $e) {
            $caught = true;
            $this->assertSame(404, $e->getStatusCode());
        }
        $this->assertTrue($caught);
    }

    public function testExceptionCanBeCaughtByRuntimeException(): void
    {
        $caught = false;
        try {
            throw new RateLimitException('Too fast');
        } catch (\RuntimeException $e) {
            $caught = true;
        }
        $this->assertTrue($caught);
    }
}
