<?php

declare(strict_types=1);

namespace WebklientApp\Tests\Security;

use PHPUnit\Framework\TestCase;
use WebklientApp\Core\Security\Hash;

/**
 * Security tests: Password hashing, strength validation, bcrypt behavior.
 */
class PasswordSecurityTest extends TestCase
{
    private Hash $hash;

    protected function setUp(): void
    {
        $this->hash = new Hash(4);
    }

    public function testPasswordNeverStoredInPlainText(): void
    {
        $password = 'MySecureP@ss123';
        $hashed = $this->hash->make($password);

        $this->assertNotSame($password, $hashed);
        $this->assertStringNotContainsString($password, $hashed);
    }

    public function testBcryptHashFormat(): void
    {
        $hashed = $this->hash->make('test');
        $this->assertMatchesRegularExpression('/^\$2y\$\d{2}\$[\.\/A-Za-z0-9]{53}$/', $hashed);
    }

    public function testSamePasswordProducesDifferentHashes(): void
    {
        $password = 'SamePassword!1';
        $hashes = [];
        for ($i = 0; $i < 5; $i++) {
            $hashes[] = $this->hash->make($password);
        }

        $unique = array_unique($hashes);
        $this->assertCount(5, $unique);
    }

    public function testTimingAttackResistance(): void
    {
        $hashed = $this->hash->make('correct-password');

        $start1 = hrtime(true);
        $this->hash->verify('correct-password', $hashed);
        $time1 = hrtime(true) - $start1;

        $start2 = hrtime(true);
        $this->hash->verify('wrong-password-completely-different', $hashed);
        $time2 = hrtime(true) - $start2;

        $ratio = max($time1, $time2) / max(min($time1, $time2), 1);
        $this->assertLessThan(10, $ratio, 'Verification time ratio too high - possible timing leak');
    }

    public function testEmptyPasswordCanBeHashed(): void
    {
        $hashed = $this->hash->make('');
        $this->assertTrue($this->hash->verify('', $hashed));
        $this->assertFalse($this->hash->verify('notempty', $hashed));
    }

    public function testUnicodePasswordHandled(): void
    {
        $password = 'Příliš_žluťoučký_Kůň1!';
        $hashed = $this->hash->make($password);
        $this->assertTrue($this->hash->verify($password, $hashed));
    }

    public function testLongPasswordHandled(): void
    {
        $password = str_repeat('A', 72) . 'B1!a';
        $hashed = $this->hash->make($password);
        $this->assertTrue($this->hash->verify($password, $hashed));
    }

    /**
     * Bcrypt truncates at 72 bytes - this test documents the behavior.
     */
    public function testBcryptTruncationBehavior(): void
    {
        $base = str_repeat('A', 72);
        $pass1 = $base . 'X';
        $pass2 = $base . 'Y';

        $hashed = $this->hash->make($pass1);
        $this->assertTrue($this->hash->verify($pass2, $hashed));
    }

    public function testPasswordStrengthAllCriteria(): void
    {
        $this->assertEmpty(Hash::checkStrength('StrongP@ss1'));
    }

    public function testPasswordStrengthWeakPasswords(): void
    {
        $weak = [
            'password',
            '12345678',
            'UPPERCASE',
            'lowercase',
            'NoSpecial1',
            'nodigit!A',
            'Short1!',
        ];

        foreach ($weak as $pass) {
            $failures = Hash::checkStrength($pass);
            $this->assertNotEmpty($failures, "Password '{$pass}' should have failures");
        }
    }

    public function testCommonPasswordsFailStrengthCheck(): void
    {
        $common = ['password', 'qwerty', 'abc123', 'letmein', 'admin'];

        foreach ($common as $pass) {
            $failures = Hash::checkStrength($pass);
            $this->assertNotEmpty($failures, "Common password '{$pass}' should fail");
        }
    }

    public function testPasswordStrengthMinLengthConfigurable(): void
    {
        $password = 'Ab1!5678';
        $this->assertEmpty(Hash::checkStrength($password, 8));
        $failures = Hash::checkStrength($password, 10);
        $this->assertNotEmpty($failures);
    }

    public function testPasswordWithOnlySpecialChars(): void
    {
        $failures = Hash::checkStrength('!@#$%^&*');
        $this->assertContains('Must contain at least one uppercase letter.', $failures);
        $this->assertContains('Must contain at least one lowercase letter.', $failures);
        $this->assertContains('Must contain at least one digit.', $failures);
    }

    public function testRehashDetectsWeakerCost(): void
    {
        $weakHash = new Hash(4);
        $hashed = $weakHash->make('test');

        $strongHash = new Hash(12);
        $this->assertTrue($strongHash->needsRehash($hashed));
    }

    public function testRehashNotNeededForSameCost(): void
    {
        $hashed = $this->hash->make('test');
        $this->assertFalse($this->hash->needsRehash($hashed));
    }

    public function testVerifyWithInvalidHashReturnsFalse(): void
    {
        $this->assertFalse($this->hash->verify('password', 'not-a-valid-hash'));
    }

    public function testVerifyWithEmptyHashReturnsFalse(): void
    {
        $this->assertFalse($this->hash->verify('password', ''));
    }
}
