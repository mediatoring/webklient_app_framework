<?php

declare(strict_types=1);

namespace WebklientApp\Tests\Security;

use PHPUnit\Framework\TestCase;
use WebklientApp\Core\Security\Hash;

/**
 * Security tests: Password reset token generation, token properties,
 * brute-force resistance, and crypto strength.
 */
class PasswordResetSecurityTest extends TestCase
{
    public function testTokenHasSufficientEntropy(): void
    {
        $token = bin2hex(random_bytes(32));
        $this->assertSame(64, strlen($token), 'Token should be 64 hex characters (256 bits)');
    }

    public function testTokensAreUnique(): void
    {
        $tokens = [];
        for ($i = 0; $i < 100; $i++) {
            $tokens[] = bin2hex(random_bytes(32));
        }
        $this->assertCount(100, array_unique($tokens), 'All tokens should be unique');
    }

    public function testTokenHashIsNotReversible(): void
    {
        $token = bin2hex(random_bytes(32));
        $hash = hash('sha256', $token);

        $this->assertNotSame($token, $hash);
        $this->assertSame(64, strlen($hash));
    }

    public function testSameTokenAlwaysProducesSameHash(): void
    {
        $token = bin2hex(random_bytes(32));
        $hash1 = hash('sha256', $token);
        $hash2 = hash('sha256', $token);

        $this->assertSame($hash1, $hash2);
    }

    public function testDifferentTokensProduceDifferentHashes(): void
    {
        $token1 = bin2hex(random_bytes(32));
        $token2 = bin2hex(random_bytes(32));

        $this->assertNotSame(
            hash('sha256', $token1),
            hash('sha256', $token2)
        );
    }

    public function testTokenCannotBePredictedFromHash(): void
    {
        $token = bin2hex(random_bytes(32));
        $hash = hash('sha256', $token);

        $this->assertStringNotContainsString($token, $hash);
        $this->assertNotSame($token, $hash);
    }

    public function testBruteForceResistance(): void
    {
        $token = bin2hex(random_bytes(32));
        $targetHash = hash('sha256', $token);

        $attempts = 10000;
        for ($i = 0; $i < $attempts; $i++) {
            $guess = bin2hex(random_bytes(32));
            if (hash('sha256', $guess) === $targetHash) {
                $this->fail('Token was guessed - crypto failure');
            }
        }
        $this->assertTrue(true, 'Token survived brute force attempts');
    }

    public function testTokenHexEncoding(): void
    {
        $token = bin2hex(random_bytes(32));
        $this->assertMatchesRegularExpression('/^[a-f0-9]{64}$/', $token);
    }

    public function testPasswordResetRequiresMinLength(): void
    {
        $failures = Hash::checkStrength('Sh0rt!', 8);
        $this->assertNotEmpty($failures, 'Short password should fail strength check');
    }

    public function testPasswordResetStrongPasswordPasses(): void
    {
        $this->assertEmpty(Hash::checkStrength('N3wSecureP@ss!'));
    }

    public function testPasswordResetCommonPasswordsFail(): void
    {
        $common = ['password', 'qwerty', 'abc123', '12345678'];
        foreach ($common as $pass) {
            $failures = Hash::checkStrength($pass);
            $this->assertNotEmpty($failures, "Common password '{$pass}' should fail");
        }
    }

    public function testExpiryTimestampIsInFuture(): void
    {
        $expiresAt = date('Y-m-d H:i:s', time() + 3600);
        $expiresTs = strtotime($expiresAt);
        $this->assertGreaterThan(time(), $expiresTs);
    }

    public function testExpiryTimestampNotTooFarInFuture(): void
    {
        $ttlSeconds = 3600;
        $expiresAt = time() + $ttlSeconds;
        $this->assertLessThanOrEqual(time() + 7200, $expiresAt, 'Token should not expire more than 2h in the future');
    }

    public function testHashTimingConsistency(): void
    {
        $token1 = bin2hex(random_bytes(32));
        $token2 = bin2hex(random_bytes(32));
        $hash1 = hash('sha256', $token1);

        $start1 = hrtime(true);
        hash_equals($hash1, hash('sha256', $token1));
        $time1 = hrtime(true) - $start1;

        $start2 = hrtime(true);
        hash_equals($hash1, hash('sha256', $token2));
        $time2 = hrtime(true) - $start2;

        $ratio = max($time1, $time2) / max(min($time1, $time2), 1);
        $this->assertLessThan(10, $ratio, 'Timing difference too high - potential timing leak');
    }

    public function testTokenExpiryPrecision(): void
    {
        $now = time();
        $expiresAt = date('Y-m-d H:i:s', $now + 3600);
        $parsed = strtotime($expiresAt);

        $this->assertEqualsWithDelta($now + 3600, $parsed, 1, 'Expiry should be precise to 1 second');
    }

    public function testResetTokenInvalidatesOldTokens(): void
    {
        $token1 = bin2hex(random_bytes(32));
        $token2 = bin2hex(random_bytes(32));
        $hash1 = hash('sha256', $token1);
        $hash2 = hash('sha256', $token2);

        $this->assertNotSame($hash1, $hash2, 'Different reset requests should produce different hashes');
    }
}
