<?php

declare(strict_types=1);

namespace WebklientApp\Tests\Security;

use PHPUnit\Framework\TestCase;
use WebklientApp\Core\View\ViewRenderer;

/**
 * Security tests: XSS via template variables, path traversal in template names,
 * template injection, and HTML entity escaping.
 */
class ViewSecurityTest extends TestCase
{
    private ViewRenderer $view;
    private string $viewsDir;

    protected function setUp(): void
    {
        $this->viewsDir = sys_get_temp_dir() . '/wka_test_views_' . bin2hex(random_bytes(4));
        mkdir($this->viewsDir, 0755, true);
        mkdir($this->viewsDir . '/layouts', 0755, true);

        file_put_contents($this->viewsDir . '/test.php', '<?= $this->e($input) ?>');
        file_put_contents($this->viewsDir . '/raw.php', '<?= $input ?>');
        file_put_contents($this->viewsDir . '/multi.php', '<div><?= $this->e($title) ?></div><p><?= $this->e($body) ?></p>');
        file_put_contents($this->viewsDir . '/layout-test.php', '<?php $this->layout("layouts.main"); ?><p><?= $this->e($content) ?></p>');
        file_put_contents($this->viewsDir . '/layouts/main.php', '<html><body><?= $this->content() ?></body></html>');
        file_put_contents($this->viewsDir . '/section-test.php', '<?php $this->layout("layouts.main"); ?><?php $this->beginSection("title"); ?><?= $this->e($title) ?><?php $this->endSection(); ?><p>body</p>');
        file_put_contents($this->viewsDir . '/partial-test.php', '<?= $this->partial("test", ["input" => $input]) ?>');

        $this->view = new ViewRenderer($this->viewsDir);
    }

    protected function tearDown(): void
    {
        $files = glob($this->viewsDir . '/{,layouts/}*', GLOB_BRACE);
        foreach ($files as $file) {
            if (is_file($file)) {
                unlink($file);
            }
        }
        @rmdir($this->viewsDir . '/layouts');
        @rmdir($this->viewsDir);
    }

    public function testEscapesHtmlTags(): void
    {
        $html = $this->view->render('test', ['input' => '<script>alert(1)</script>']);
        $this->assertStringNotContainsString('<script>', $html);
        $this->assertStringContainsString('&lt;script&gt;', $html);
    }

    public function testEscapesDoubleQuotes(): void
    {
        $html = $this->view->render('test', ['input' => '"><img src=x onerror=alert(1)>']);
        $this->assertStringNotContainsString('">', $html);
        $this->assertStringContainsString('&quot;', $html);
    }

    public function testEscapesSingleQuotes(): void
    {
        $html = $this->view->render('test', ['input' => "' onclick='alert(1)'"]);
        $this->assertStringNotContainsString("'", $html);
        $this->assertStringContainsString('&#039;', $html);
    }

    public function testEscapesAmpersand(): void
    {
        $html = $this->view->render('test', ['input' => '&lt;script&gt;']);
        $this->assertStringContainsString('&amp;lt;', $html);
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('xssPayloadProvider')]
    public function testEscapesXssPayloads(string $payload): void
    {
        $html = $this->view->render('test', ['input' => $payload]);
        $this->assertStringNotContainsString('<', $html);
        $this->assertStringNotContainsString('>', $html);
    }

    public static function xssPayloadProvider(): array
    {
        return [
            'script tag' => ['<script>alert("XSS")</script>'],
            'img onerror' => ['<img src=x onerror=alert(1)>'],
            'svg onload' => ['<svg/onload=alert(1)>'],
            'event handler' => ['<div onclick="alert(1)">click me</div>'],
            'javascript protocol' => ['<a href="javascript:alert(1)">link</a>'],
            'iframe' => ['<iframe src="javascript:alert(1)">'],
            'input autofocus' => ['<input autofocus onfocus=alert(1)>'],
            'nested script' => ['<scr<script>ipt>alert(1)</scr</script>ipt>'],
            'null byte script' => ["<scr\x00ipt>alert(1)</script>"],
        ];
    }

    public function testRawOutputIsNotEscaped(): void
    {
        $html = $this->view->render('raw', ['input' => '<b>bold</b>']);
        $this->assertStringContainsString('<b>bold</b>', $html);
    }

    public function testMultipleVariablesAllEscaped(): void
    {
        $html = $this->view->render('multi', [
            'title' => '<script>evil</script>',
            'body' => '<img src=x onerror=hack>',
        ]);
        $this->assertStringNotContainsString('<script>', $html);
        $this->assertStringNotContainsString('<img', $html);
    }

    public function testPathTraversalInTemplateName(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->view->render('../../etc/passwd', []);
    }

    public function testPathTraversalWithDotNotation(): void
    {
        $traversalPaths = [
            '....etc.passwd',
            'layouts.....secret',
        ];

        foreach ($traversalPaths as $path) {
            try {
                $this->view->render($path, []);
            } catch (\RuntimeException $e) {
                $this->assertStringContainsString('not found', $e->getMessage());
            }
        }
        $this->assertTrue(true);
    }

    public function testNonExistentTemplateThrows(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('View template not found');
        $this->view->render('nonexistent', []);
    }

    public function testLayoutSystemWorks(): void
    {
        $html = $this->view->render('layout-test', ['content' => '<script>xss</script>']);
        $this->assertStringContainsString('<html>', $html);
        $this->assertStringContainsString('</html>', $html);
        $this->assertStringNotContainsString('<script>xss</script>', $html);
        $this->assertStringContainsString('&lt;script&gt;', $html);
    }

    public function testPartialEscaping(): void
    {
        $html = $this->view->render('partial-test', ['input' => '<script>evil</script>']);
        $this->assertStringNotContainsString('<script>', $html);
        $this->assertStringContainsString('&lt;script&gt;', $html);
    }

    public function testPreservesMultibyteCharacters(): void
    {
        $html = $this->view->render('test', ['input' => 'Příliš žluťoučký kůň']);
        $this->assertStringContainsString('Příliš žluťoučký kůň', $html);
    }

    public function testEmptyStringHandled(): void
    {
        $html = $this->view->render('test', ['input' => '']);
        $this->assertSame('', trim($html));
    }

    public function testNullByteInInput(): void
    {
        $html = $this->view->render('test', ['input' => "safe\x00<script>evil</script>"]);
        $this->assertStringNotContainsString('<script>', $html);
    }

    public function testExtractSkipDoesNotOverwriteThis(): void
    {
        $html = $this->view->render('test', ['this' => 'overwritten', 'input' => 'safe']);
        $this->assertStringContainsString('safe', $html);
    }

    public function testOverlongVariableContent(): void
    {
        $long = str_repeat('<script>', 10000);
        $html = $this->view->render('test', ['input' => $long]);
        $this->assertStringNotContainsString('<script>', $html);
    }

    public function testRenderDoesNotLeakStateAcrossCalls(): void
    {
        $html1 = $this->view->render('test', ['input' => 'first']);
        $html2 = $this->view->render('test', ['input' => 'second']);

        $this->assertStringContainsString('first', $html1);
        $this->assertStringNotContainsString('second', $html1);
        $this->assertStringContainsString('second', $html2);
        $this->assertStringNotContainsString('first', $html2);
    }
}
