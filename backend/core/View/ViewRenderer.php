<?php

declare(strict_types=1);

namespace WebklientApp\Core\View;

/**
 * Simple PHP template engine with layout support.
 *
 * Usage:
 *   $view = new ViewRenderer('/path/to/views');
 *   $html = $view->render('emails.welcome', ['user' => 'John']);
 *
 * Templates are plain PHP files. Inside a template:
 *   <?= $this->e($variable) ?>   — escaped output
 *   <?= $variable ?>              — raw output (be careful)
 *
 * Layout support:
 *   In template: $this->layout('layouts.email');
 *   In layout:   <?= $this->content() ?>
 */
class ViewRenderer
{
    private string $basePath;
    private ?string $layoutName = null;
    private string $renderedContent = '';
    private array $sections = [];
    private ?string $currentSection = null;

    public function __construct(?string $basePath = null)
    {
        $this->basePath = $basePath ?? dirname(__DIR__, 2) . '/views';
    }

    /**
     * Render a template with given data. Dot notation maps to directory separators.
     */
    public function render(string $template, array $data = []): string
    {
        $this->layoutName = null;
        $this->sections = [];

        $content = $this->renderTemplate($template, $data);

        if ($this->layoutName !== null) {
            $this->renderedContent = $content;
            $content = $this->renderTemplate($this->layoutName, $data);
        }

        return $content;
    }

    /**
     * Called from within a template to declare which layout to use.
     */
    protected function layout(string $name): void
    {
        $this->layoutName = $name;
    }

    /**
     * Called from within a layout to insert the rendered child content.
     */
    protected function content(): string
    {
        return $this->renderedContent;
    }

    /**
     * Start a named section (for inserting blocks into layout).
     */
    protected function beginSection(string $name): void
    {
        $this->currentSection = $name;
        ob_start();
    }

    /**
     * End the current section.
     */
    protected function endSection(): void
    {
        if ($this->currentSection !== null) {
            $this->sections[$this->currentSection] = ob_get_clean();
            $this->currentSection = null;
        }
    }

    /**
     * Yield a section's content in the layout.
     */
    protected function section(string $name, string $default = ''): string
    {
        return $this->sections[$name] ?? $default;
    }

    /**
     * Include a partial template.
     */
    protected function partial(string $template, array $data = []): string
    {
        return $this->renderTemplate($template, $data);
    }

    /**
     * Escape HTML entities for safe output.
     */
    protected function e(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    private function renderTemplate(string $template, array $data): string
    {
        $path = $this->resolvePath($template);
        if (!file_exists($path)) {
            throw new \RuntimeException("View template not found: {$template} (looked in {$path})");
        }

        extract($data, EXTR_SKIP);
        ob_start();

        try {
            include $path;
        } catch (\Throwable $e) {
            ob_end_clean();
            throw $e;
        }

        return ob_get_clean();
    }

    private function resolvePath(string $template): string
    {
        $relative = str_replace('.', DIRECTORY_SEPARATOR, $template) . '.php';
        return $this->basePath . DIRECTORY_SEPARATOR . $relative;
    }
}
