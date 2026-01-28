<?php

declare(strict_types=1);

namespace WebklientApp\Core\Storage;

interface StorageInterface
{
    public function store(string $sourcePath, string $destPath): bool;
    public function get(string $path): ?string;
    public function delete(string $path): bool;
    public function exists(string $path): bool;
    public function url(string $path): string;
    public function size(string $path): int;
}
