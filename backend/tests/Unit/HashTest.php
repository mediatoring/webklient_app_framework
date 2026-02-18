<?php

declare(strict_types=1);

namespace WebklientApp\Tests\Unit;

use PHPUnit\Framework\TestCase;
use WebklientApp\Core\Security\Hash;

class HashTest extends TestCase
{
    private Hash $hash;

    protected function setUp(): void
    {
        $this->hash = new Hash(4);
    }

    public function testMakeProducesHash(): void
    {
        $hashed = $this->hash->make('password123');
        $this->assertNotSame('password123', $hashed);
        $this->assertStringStartsWith('$2y$', $hashed);
    }

    public function testMakeProducesUniqueHashes(): void
    {
        $hash1 = $this->hash->make('password123');
        $hash2 = $this->hash->make('password123');
        $this->assertNotSame($hash1, $hash2);
    }

    public function testVerifyCorrectPassword(): void
    {
        $hashed = $this->hash->make('MySecretPass!');
        $this->assertTrue($this->hash->verify('MySecretPass!', $hashed));
    }

    public function testVerifyWrongPassword(): void
    {
        $hashed = $this->hash->make('correctpassword');
        $this->assertFalse($this->hash->verify('wrongpassword', $hashed));
    }

    public function testVerifyEmptyPassword(): void
    {
        $hashed = $this->hash->make('something');
        $this->assertFalse($this->hash->verify('', $hashed));
    }

    public function testNeedsRehashWithDifferentCost(): void
    {
        $lowCost = new Hash(4);
        $hashed = $lowCost->make('test');

        $highCost = new Hash(10);
        $this->assertTrue($highCost->needsRehash($hashed));
    }

    public function testNeedsRehashReturnsFalseForSameCost(): void
    {
        $hashed = $this->hash->make('test');
        $this->assertFalse($this->hash->needsRehash($hashed));
    }

    public function testCheckStrengthStrongPassword(): void
    {
        $failures = Hash::checkStrength('MyStr0ng!Pass');
        $this->assertEmpty($failures);
    }

    public function testCheckStrengthTooShort(): void
    {
        $failures = Hash::checkStrength('Ab1!');
        $this->assertNotEmpty($failures);
        $this->assertContains('Must be at least 8 characters.', $failures);
    }

    public function testCheckStrengthNoUppercase(): void
    {
        $failures = Hash::checkStrength('mypassword1!');
        $this->assertContains('Must contain at least one uppercase letter.', $failures);
    }

    public function testCheckStrengthNoLowercase(): void
    {
        $failures = Hash::checkStrength('MYPASSWORD1!');
        $this->assertContains('Must contain at least one lowercase letter.', $failures);
    }

    public function testCheckStrengthNoDigit(): void
    {
        $failures = Hash::checkStrength('MyPassword!');
        $this->assertContains('Must contain at least one digit.', $failures);
    }

    public function testCheckStrengthNoSpecialChar(): void
    {
        $failures = Hash::checkStrength('MyPassword1');
        $this->assertContains('Must contain at least one special character.', $failures);
    }

    public function testCheckStrengthCustomMinLength(): void
    {
        $failures = Hash::checkStrength('Ab1!5678', 10);
        $this->assertContains('Must be at least 10 characters.', $failures);
    }

    public function testCheckStrengthAllFailures(): void
    {
        $failures = Hash::checkStrength('abc');
        $this->assertCount(4, $failures);
    }

    public function testDefaultBcryptRounds(): void
    {
        $hash = new Hash();
        $hashed = $hash->make('test');
        $info = password_get_info($hashed);
        $this->assertSame(12, $info['options']['cost']);
    }

    public function testCustomBcryptRounds(): void
    {
        $hashed = $this->hash->make('test');
        $info = password_get_info($hashed);
        $this->assertSame(4, $info['options']['cost']);
    }
}
