<?php

declare(strict_types=1);

namespace WebklientApp\Core\Storage;

class LocalStorage implements StorageInterface
{
    private string $basePath;
    private string $baseUrl;

    public function __construct(string $basePath, string $baseUrl = '/api/files')
    {
        $this->basePath = rtrim($basePath, '/');
        $this->baseUrl = rtrim($baseUrl, '/');

        if (!is_dir($this->basePath)) {
            mkdir($this->basePath, 0775, true);
        }
    }

    public function store(string $sourcePath, string $destPath): bool
    {
        $full = $this->fullPath($destPath);
        $dir = dirname($full);
        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }
        return copy($sourcePath, $full);
    }

    public function get(string $path): ?string
    {
        $full = $this->fullPath($path);
        if (!file_exists($full)) {
            return null;
        }
        return file_get_contents($full) ?: null;
    }

    public function delete(string $path): bool
    {
        $full = $this->fullPath($path);
        return file_exists($full) && unlink($full);
    }

    public function exists(string $path): bool
    {
        return file_exists($this->fullPath($path));
    }

    public function url(string $path): string
    {
        return $this->baseUrl . '/' . ltrim($path, '/');
    }

    public function size(string $path): int
    {
        $full = $this->fullPath($path);
        return file_exists($full) ? filesize($full) : 0;
    }

    public function fullPath(string $path): string
    {
        return $this->basePath . '/' . ltrim($path, '/');
    }
}
