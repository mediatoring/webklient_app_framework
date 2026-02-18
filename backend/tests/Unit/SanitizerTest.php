<?php

declare(strict_types=1);

namespace WebklientApp\Tests\Unit;

use PHPUnit\Framework\TestCase;
use WebklientApp\Core\Validation\Sanitizer;

class SanitizerTest extends TestCase
{
    public function testStringTrimsWhitespace(): void
    {
        $this->assertSame('hello', Sanitizer::string('  hello  '));
    }

    public function testStringEscapesHtml(): void
    {
        $this->assertSame(
            '&lt;script&gt;alert(1)&lt;/script&gt;',
            Sanitizer::string('<script>alert(1)</script>')
        );
    }

    public function testStringEscapesQuotes(): void
    {
        $result = Sanitizer::string('" onmouseover="alert(1)"');
        $this->assertStringNotContainsString('"', $result);
    }

    public function testStringHandlesEmptyInput(): void
    {
        $this->assertSame('', Sanitizer::string(''));
    }

    public function testStringConvertsNonStringTypes(): void
    {
        $this->assertSame('123', Sanitizer::string(123));
        $this->assertSame('1', Sanitizer::string(true));
    }

    public function testEmailLowercasesAndTrims(): void
    {
        $this->assertSame('test@example.com', Sanitizer::email('  TEST@EXAMPLE.COM  '));
    }

    public function testUrlSanitization(): void
    {
        $result = Sanitizer::url('https://example.com/path');
        $this->assertStringContainsString('https://example.com/path', $result);
    }

    public function testUrlTrimsWhitespace(): void
    {
        $clean = Sanitizer::url('  https://example.com  ');
        $this->assertStringContainsString('https://example.com', $clean);
    }

    public function testUrlSanitizesHtmlTags(): void
    {
        $malicious = 'https://example.com/<script>alert(1)</script>';
        $sanitized = Sanitizer::url($malicious);
        $this->assertStringNotContainsString('<script>', $sanitized);
        $this->assertStringNotContainsString('<', $sanitized);
    }

    public function testIntegerSanitization(): void
    {
        $this->assertSame(42, Sanitizer::integer('42'));
        $this->assertSame(42, Sanitizer::integer(42));
        $this->assertSame(0, Sanitizer::integer('abc'));
    }

    public function testIntegerFromNegative(): void
    {
        $this->assertSame(-5, Sanitizer::integer('-5'));
    }

    public function testIntegerFromSqlInjectionPayload(): void
    {
        $this->assertSame(1, Sanitizer::integer('1; DROP TABLE users'));
        $this->assertSame(1, Sanitizer::integer('1 OR 1=1'));
        $this->assertSame(0, Sanitizer::integer("'; DELETE FROM users; --"));
    }

    public function testFilenameRemovesSpecialChars(): void
    {
        $this->assertSame('my-file_v2.pdf', Sanitizer::filename('my-file_v2.pdf'));
    }

    public function testFilenameStripsPathTraversal(): void
    {
        $result = Sanitizer::filename('../../etc/.passwd');
        $this->assertStringNotContainsString('/', $result);
        $this->assertStringNotContainsString('\\', $result);
        $this->assertStringNotContainsString('..', $result);
    }

    public function testFilenameStripsSpacesAndUnicode(): void
    {
        $this->assertSame('file.txt', Sanitizer::filename('file .txt'));
    }

    public function testArraySanitizesStrings(): void
    {
        $input = [
            'name' => '<b>John</b>',
            'age' => 25,
            'active' => true,
        ];
        $result = Sanitizer::array($input);

        $this->assertSame('&lt;b&gt;John&lt;/b&gt;', $result['name']);
        $this->assertSame(25, $result['age']);
        $this->assertTrue($result['active']);
    }

    public function testArrayHandlesNestedArrays(): void
    {
        $input = [
            'user' => [
                'name' => '<script>x</script>',
                'meta' => [
                    'bio' => '<img src=x onerror=alert(1)>',
                ],
            ],
        ];
        $result = Sanitizer::array($input);

        $this->assertStringNotContainsString('<script>', $result['user']['name']);
        $this->assertStringNotContainsString('<img', $result['user']['meta']['bio']);
    }

    public function testArrayPreservesEmptyArrays(): void
    {
        $result = Sanitizer::array([]);
        $this->assertSame([], $result);
    }

    public function testArrayKeysPreserved(): void
    {
        $input = ['a' => 'x', 'b' => 'y'];
        $result = Sanitizer::array($input);
        $this->assertArrayHasKey('a', $result);
        $this->assertArrayHasKey('b', $result);
    }
}
