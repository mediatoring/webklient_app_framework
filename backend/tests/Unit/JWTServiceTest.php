<?php

declare(strict_types=1);

namespace WebklientApp\Tests\Unit;

use PHPUnit\Framework\TestCase;
use WebklientApp\Core\Auth\JWTService;
use WebklientApp\Core\Exceptions\AuthenticationException;

class JWTServiceTest extends TestCase
{
    private JWTService $jwt;

    protected function setUp(): void
    {
        $this->jwt = new JWTService([
            'secret' => 'test-secret-key-for-jwt-unit-tests-32chars!',
            'access_ttl' => 900,
            'refresh_ttl' => 2592000,
            'algo' => 'HS256',
        ]);
    }

    public function testThrowsOnEmptySecret(): void
    {
        $this->expectException(\RuntimeException::class);
        new JWTService(['secret' => '']);
    }

    public function testThrowsOnMissingSecret(): void
    {
        $this->expectException(\RuntimeException::class);
        new JWTService([]);
    }

    public function testCreateAccessToken(): void
    {
        $token = $this->jwt->createAccessToken(1);
        $this->assertNotEmpty($token);
        $parts = explode('.', $token);
        $this->assertCount(3, $parts);
    }

    public function testCreateRefreshToken(): void
    {
        $token = $this->jwt->createRefreshToken(1);
        $this->assertNotEmpty($token);
        $parts = explode('.', $token);
        $this->assertCount(3, $parts);
    }

    public function testDecodeAccessToken(): void
    {
        $token = $this->jwt->createAccessToken(42);
        $payload = $this->jwt->decode($token);

        $this->assertSame(42, $payload->sub);
        $this->assertSame('access', $payload->type);
        $this->assertObjectHasProperty('iat', $payload);
        $this->assertObjectHasProperty('exp', $payload);
        $this->assertObjectHasProperty('jti', $payload);
    }

    public function testDecodeRefreshToken(): void
    {
        $token = $this->jwt->createRefreshToken(7);
        $payload = $this->jwt->decode($token);

        $this->assertSame(7, $payload->sub);
        $this->assertSame('refresh', $payload->type);
    }

    public function testAccessTokenExpiry(): void
    {
        $token = $this->jwt->createAccessToken(1);
        $payload = $this->jwt->decode($token);

        $this->assertSame($payload->iat + 900, $payload->exp);
    }

    public function testRefreshTokenExpiry(): void
    {
        $token = $this->jwt->createRefreshToken(1);
        $payload = $this->jwt->decode($token);

        $this->assertSame($payload->iat + 2592000, $payload->exp);
    }

    public function testExtraClaimsInAccessToken(): void
    {
        $token = $this->jwt->createAccessToken(1, ['impersonated_by' => 99]);
        $payload = $this->jwt->decode($token);

        $this->assertSame(99, $payload->impersonated_by);
    }

    public function testDecodeInvalidTokenThrows(): void
    {
        $this->expectException(AuthenticationException::class);
        $this->jwt->decode('invalid.token.here');
    }

    public function testDecodeTamperedTokenThrows(): void
    {
        $token = $this->jwt->createAccessToken(1);
        $tampered = $token . 'x';

        $this->expectException(AuthenticationException::class);
        $this->jwt->decode($tampered);
    }

    public function testDecodeWithWrongSecretThrows(): void
    {
        $token = $this->jwt->createAccessToken(1);

        $otherJwt = new JWTService([
            'secret' => 'completely-different-secret-key!!!!!',
            'algo' => 'HS256',
        ]);

        $this->expectException(AuthenticationException::class);
        $otherJwt->decode($token);
    }

    public function testHashToken(): void
    {
        $token = 'my-jwt-token';
        $hash = $this->jwt->hashToken($token);

        $this->assertSame(64, strlen($hash));
        $this->assertSame(hash('sha256', $token), $hash);
    }

    public function testHashTokenDeterministic(): void
    {
        $token = 'same-token';
        $this->assertSame($this->jwt->hashToken($token), $this->jwt->hashToken($token));
    }

    public function testUniqueJtiPerToken(): void
    {
        $token1 = $this->jwt->createAccessToken(1);
        $token2 = $this->jwt->createAccessToken(1);

        $payload1 = $this->jwt->decode($token1);
        $payload2 = $this->jwt->decode($token2);

        $this->assertNotSame($payload1->jti, $payload2->jti);
    }

    public function testGetAccessTtl(): void
    {
        $this->assertSame(900, $this->jwt->getAccessTtl());
    }

    public function testGetRefreshTtl(): void
    {
        $this->assertSame(2592000, $this->jwt->getRefreshTtl());
    }

    public function testDefaultTtlValues(): void
    {
        $jwt = new JWTService(['secret' => 'my-secret']);
        $this->assertSame(900, $jwt->getAccessTtl());
        $this->assertSame(2592000, $jwt->getRefreshTtl());
    }
}
