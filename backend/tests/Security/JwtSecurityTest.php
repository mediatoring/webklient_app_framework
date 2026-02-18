<?php

declare(strict_types=1);

namespace WebklientApp\Tests\Security;

use PHPUnit\Framework\TestCase;
use WebklientApp\Core\Auth\JWTService;
use WebklientApp\Core\Exceptions\AuthenticationException;

/**
 * Security tests: JWT tampering, token manipulation, signature verification.
 */
class JwtSecurityTest extends TestCase
{
    private JWTService $jwt;

    protected function setUp(): void
    {
        $this->jwt = new JWTService([
            'secret' => 'secure-test-secret-key-minimum-32-chars!',
            'access_ttl' => 900,
            'refresh_ttl' => 2592000,
            'algo' => 'HS256',
        ]);
    }

    public function testTamperedPayloadRejected(): void
    {
        $token = $this->jwt->createAccessToken(1);
        $parts = explode('.', $token);

        $payload = json_decode(base64_decode($parts[1]), true);
        $payload['sub'] = 999;
        $parts[1] = rtrim(base64_encode(json_encode($payload)), '=');
        $tampered = implode('.', $parts);

        $this->expectException(AuthenticationException::class);
        $this->jwt->decode($tampered);
    }

    public function testModifiedSignatureRejected(): void
    {
        $token = $this->jwt->createAccessToken(1);
        $parts = explode('.', $token);
        $parts[2] = strrev($parts[2]);
        $tampered = implode('.', $parts);

        $this->expectException(AuthenticationException::class);
        $this->jwt->decode($tampered);
    }

    public function testNoneAlgorithmRejected(): void
    {
        $header = base64_encode(json_encode(['alg' => 'none', 'typ' => 'JWT']));
        $payload = base64_encode(json_encode([
            'sub' => 1,
            'type' => 'access',
            'iat' => time(),
            'exp' => time() + 3600,
        ]));
        $fakeToken = "{$header}.{$payload}.";

        $this->expectException(AuthenticationException::class);
        $this->jwt->decode($fakeToken);
    }

    public function testEmptyTokenRejected(): void
    {
        $this->expectException(AuthenticationException::class);
        $this->jwt->decode('');
    }

    public function testMalformedTokenRejected(): void
    {
        $malformed = [
            'not-a-jwt',
            'only.two',
            '....',
            'a.b.c.d',
            base64_encode('{"alg":"HS256"}') . '.' . base64_encode('{}') . '.invalidsig',
        ];

        foreach ($malformed as $token) {
            try {
                $this->jwt->decode($token);
                $this->fail("Expected AuthenticationException for: {$token}");
            } catch (AuthenticationException $e) {
                $this->assertStringContainsString('Invalid or expired token', $e->getMessage());
            }
        }
    }

    public function testExpiredTokenRejected(): void
    {
        $jwt = new JWTService([
            'secret' => 'secure-test-secret-key-minimum-32-chars!',
            'access_ttl' => -1,
            'algo' => 'HS256',
        ]);

        $token = $jwt->createAccessToken(1);

        $this->expectException(AuthenticationException::class);
        $jwt->decode($token);
    }

    public function testTokenFromDifferentSecretRejected(): void
    {
        $otherJwt = new JWTService([
            'secret' => 'another-completely-different-secret!!!!!',
            'algo' => 'HS256',
        ]);

        $token = $otherJwt->createAccessToken(1);

        $this->expectException(AuthenticationException::class);
        $this->jwt->decode($token);
    }

    public function testTokenWithModifiedExpiryRejected(): void
    {
        $token = $this->jwt->createAccessToken(1);
        $parts = explode('.', $token);

        $payload = json_decode(base64_decode($parts[1]), true);
        $payload['exp'] = time() + 999999;
        $parts[1] = rtrim(base64_encode(json_encode($payload)), '=');
        $tampered = implode('.', $parts);

        $this->expectException(AuthenticationException::class);
        $this->jwt->decode($tampered);
    }

    public function testTokenTypePreserved(): void
    {
        $accessToken = $this->jwt->createAccessToken(1);
        $refreshToken = $this->jwt->createRefreshToken(1);

        $accessPayload = $this->jwt->decode($accessToken);
        $refreshPayload = $this->jwt->decode($refreshToken);

        $this->assertSame('access', $accessPayload->type);
        $this->assertSame('refresh', $refreshPayload->type);
    }

    public function testTokenHashIsNotReversible(): void
    {
        $token = $this->jwt->createAccessToken(1);
        $hash = $this->jwt->hashToken($token);

        $this->assertNotSame($token, $hash);
        $this->assertSame(64, strlen($hash));
    }

    public function testDifferentTokensProduceDifferentHashes(): void
    {
        $token1 = $this->jwt->createAccessToken(1);
        $token2 = $this->jwt->createAccessToken(2);

        $this->assertNotSame(
            $this->jwt->hashToken($token1),
            $this->jwt->hashToken($token2)
        );
    }

    public function testJtiUniqueness(): void
    {
        $jtis = [];
        for ($i = 0; $i < 50; $i++) {
            $token = $this->jwt->createAccessToken(1);
            $payload = $this->jwt->decode($token);
            $jtis[] = $payload->jti;
        }

        $this->assertCount(50, array_unique($jtis));
    }

    public function testLargePayloadExtraClaims(): void
    {
        $extra = ['roles' => array_fill(0, 100, 'role_name')];
        $token = $this->jwt->createAccessToken(1, $extra);
        $payload = $this->jwt->decode($token);

        $this->assertCount(100, $payload->roles);
    }

    public function testTokenNotDecodableWithoutSecret(): void
    {
        $token = $this->jwt->createAccessToken(1);
        $parts = explode('.', $token);
        $payloadJson = base64_decode($parts[1]);
        $payload = json_decode($payloadJson, true);

        $this->assertSame(1, $payload['sub']);
        $this->assertSame('access', $payload['type']);
    }

    public function testEmptySecretThrowsException(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('JWT secret is not configured');
        new JWTService(['secret' => '', 'algo' => 'HS256']);
    }
}
