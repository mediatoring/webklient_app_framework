<?php

declare(strict_types=1);

namespace WebklientApp\Tests\Unit;

use PHPUnit\Framework\TestCase;
use WebklientApp\Core\Validation\Validator;
use WebklientApp\Core\Exceptions\ValidationException;

class ValidatorTest extends TestCase
{
    private Validator $validator;

    protected function setUp(): void
    {
        $this->validator = new Validator();
    }

    public function testRequiredFieldPasses(): void
    {
        $result = $this->validator->validate(['name' => 'John'], ['name' => 'required']);
        $this->assertSame('John', $result['name']);
    }

    public function testRequiredFieldFailsWhenMissing(): void
    {
        $this->expectException(ValidationException::class);
        $this->validator->validate([], ['name' => 'required']);
    }

    public function testRequiredFieldFailsWhenEmpty(): void
    {
        $this->expectException(ValidationException::class);
        $this->validator->validate(['name' => ''], ['name' => 'required']);
    }

    public function testEmailRulePasses(): void
    {
        $result = $this->validator->validate(
            ['email' => 'test@example.com'],
            ['email' => 'required|email']
        );
        $this->assertSame('test@example.com', $result['email']);
    }

    public function testEmailRuleFailsOnInvalid(): void
    {
        $this->expectException(ValidationException::class);
        $this->validator->validate(['email' => 'not-an-email'], ['email' => 'required|email']);
    }

    public function testMinRulePasses(): void
    {
        $result = $this->validator->validate(['name' => 'abc'], ['name' => 'min:3']);
        $this->assertSame('abc', $result['name']);
    }

    public function testMinRuleFailsOnShortString(): void
    {
        $this->expectException(ValidationException::class);
        $this->validator->validate(['name' => 'ab'], ['name' => 'required|min:3']);
    }

    public function testMaxRulePasses(): void
    {
        $result = $this->validator->validate(['name' => 'abc'], ['name' => 'max:5']);
        $this->assertSame('abc', $result['name']);
    }

    public function testMaxRuleFailsOnLongString(): void
    {
        $this->expectException(ValidationException::class);
        $this->validator->validate(['name' => 'abcdef'], ['name' => 'required|max:5']);
    }

    public function testMinMaxCombined(): void
    {
        $result = $this->validator->validate(['name' => 'abcd'], ['name' => 'min:3|max:5']);
        $this->assertSame('abcd', $result['name']);
    }

    public function testIntegerRulePasses(): void
    {
        $result = $this->validator->validate(['age' => '25'], ['age' => 'integer']);
        $this->assertSame('25', $result['age']);
    }

    public function testIntegerRulePassesWithInt(): void
    {
        $result = $this->validator->validate(['age' => 25], ['age' => 'integer']);
        $this->assertSame(25, $result['age']);
    }

    public function testIntegerRuleFails(): void
    {
        $this->expectException(ValidationException::class);
        $this->validator->validate(['age' => 'abc'], ['age' => 'required|integer']);
    }

    public function testBooleanRulePasses(): void
    {
        foreach ([true, false, 0, 1, '0', '1', 'true', 'false'] as $val) {
            $v = new Validator();
            $result = $v->validate(['active' => $val], ['active' => 'boolean']);
            $this->assertArrayHasKey('active', $result);
        }
    }

    public function testBooleanRuleFails(): void
    {
        $this->expectException(ValidationException::class);
        $this->validator->validate(['active' => 'yes'], ['active' => 'required|boolean']);
    }

    public function testStringRule(): void
    {
        $result = $this->validator->validate(['name' => 'hello'], ['name' => 'string']);
        $this->assertSame('hello', $result['name']);
    }

    public function testStringRuleFails(): void
    {
        $this->expectException(ValidationException::class);
        $this->validator->validate(['name' => 123], ['name' => 'required|string']);
    }

    public function testUrlRulePasses(): void
    {
        $result = $this->validator->validate(
            ['url' => 'https://example.com'],
            ['url' => 'url']
        );
        $this->assertSame('https://example.com', $result['url']);
    }

    public function testUrlRuleFails(): void
    {
        $this->expectException(ValidationException::class);
        $this->validator->validate(['url' => 'not-a-url'], ['url' => 'required|url']);
    }

    public function testInRulePasses(): void
    {
        $result = $this->validator->validate(
            ['role' => 'admin'],
            ['role' => 'in:admin,user,editor']
        );
        $this->assertSame('admin', $result['role']);
    }

    public function testInRuleFails(): void
    {
        $this->expectException(ValidationException::class);
        $this->validator->validate(
            ['role' => 'superadmin'],
            ['role' => 'required|in:admin,user,editor']
        );
    }

    public function testArrayRulePasses(): void
    {
        $result = $this->validator->validate(
            ['items' => [1, 2, 3]],
            ['items' => 'array']
        );
        $this->assertSame([1, 2, 3], $result['items']);
    }

    public function testArrayRuleFails(): void
    {
        $this->expectException(ValidationException::class);
        $this->validator->validate(['items' => 'not-array'], ['items' => 'required|array']);
    }

    public function testRegexRulePasses(): void
    {
        $result = $this->validator->validate(
            ['code' => 'ABC-123'],
            ['code' => 'regex:/^[A-Z]+-\d+$/']
        );
        $this->assertSame('ABC-123', $result['code']);
    }

    public function testRegexRuleFails(): void
    {
        $this->expectException(ValidationException::class);
        $this->validator->validate(
            ['code' => 'invalid'],
            ['code' => 'required|regex:/^[A-Z]+-\d+$/']
        );
    }

    public function testOptionalFieldsSkippedWhenMissing(): void
    {
        $result = $this->validator->validate([], ['email' => 'email']);
        $this->assertArrayHasKey('email', $result);
        $this->assertNull($result['email']);
    }

    public function testNullableFieldAcceptsNull(): void
    {
        $result = $this->validator->validate(
            ['note' => null],
            ['note' => 'nullable|string']
        );
        $this->assertNull($result['note']);
    }

    public function testMultipleFieldValidation(): void
    {
        $result = $this->validator->validate(
            ['name' => 'John', 'email' => 'john@test.com', 'age' => '30'],
            ['name' => 'required|string|min:2', 'email' => 'required|email', 'age' => 'required|integer']
        );
        $this->assertSame('John', $result['name']);
        $this->assertSame('john@test.com', $result['email']);
    }

    public function testValidationExceptionContainsDetails(): void
    {
        try {
            $this->validator->validate(
                ['email' => '', 'name' => 'a'],
                ['email' => 'required|email', 'name' => 'required|min:2']
            );
            $this->fail('Expected ValidationException');
        } catch (ValidationException $e) {
            $this->assertSame(400, $e->getStatusCode());
            $this->assertSame('VALIDATION_ERROR', $e->getErrorCode());
            $this->assertNotEmpty($e->getDetails());
        }
    }

    public function testRulesAsArray(): void
    {
        $result = $this->validator->validate(
            ['name' => 'John'],
            ['name' => ['required', 'string', 'min:2']]
        );
        $this->assertSame('John', $result['name']);
    }

    public function testUnicodeStringLengthWithMin(): void
    {
        $result = $this->validator->validate(
            ['name' => 'Příliš'],
            ['name' => 'min:3']
        );
        $this->assertSame('Příliš', $result['name']);
    }
}
