<?php

declare(strict_types=1);

namespace WebklientApp\Tests\Unit;

use PHPUnit\Framework\TestCase;
use WebklientApp\Core\Validation\Validator;

/**
 * Tests the Validator class (no DB needed).
 */
class ValidatorTest extends TestCase
{
    public function testRequiredRule(): void
    {
        $errors = Validator::validate([], ['name' => 'required']);
        $this->assertArrayHasKey('name', $errors);

        $errors = Validator::validate(['name' => 'John'], ['name' => 'required']);
        $this->assertEmpty($errors);
    }

    public function testEmailRule(): void
    {
        $errors = Validator::validate(['email' => 'notanemail'], ['email' => 'email']);
        $this->assertArrayHasKey('email', $errors);

        $errors = Validator::validate(['email' => 'test@example.com'], ['email' => 'email']);
        $this->assertEmpty($errors);
    }

    public function testMinMaxRules(): void
    {
        $errors = Validator::validate(['name' => 'ab'], ['name' => 'min:3']);
        $this->assertArrayHasKey('name', $errors);

        $errors = Validator::validate(['name' => 'abc'], ['name' => 'min:3|max:5']);
        $this->assertEmpty($errors);

        $errors = Validator::validate(['name' => 'abcdef'], ['name' => 'max:5']);
        $this->assertArrayHasKey('name', $errors);
    }

    public function testIntegerRule(): void
    {
        $errors = Validator::validate(['age' => 'abc'], ['age' => 'integer']);
        $this->assertArrayHasKey('age', $errors);

        $errors = Validator::validate(['age' => '25'], ['age' => 'integer']);
        $this->assertEmpty($errors);
    }

    public function testBooleanRule(): void
    {
        $errors = Validator::validate(['active' => 'yes'], ['active' => 'boolean']);
        $this->assertArrayHasKey('active', $errors);

        $errors = Validator::validate(['active' => true], ['active' => 'boolean']);
        $this->assertEmpty($errors);
    }

    public function testOptionalFieldsSkipped(): void
    {
        // Field not present and not required = no error
        $errors = Validator::validate([], ['email' => 'email']);
        $this->assertEmpty($errors);
    }

    public function testMultipleRules(): void
    {
        $errors = Validator::validate(
            ['email' => '', 'name' => 'a'],
            ['email' => 'required|email', 'name' => 'required|min:2']
        );

        $this->assertArrayHasKey('email', $errors);
        $this->assertArrayHasKey('name', $errors);
    }
}
