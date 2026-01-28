<?php

declare(strict_types=1);

namespace WebklientApp\Core\Cache;

class FileCache implements CacheInterface
{
    private string $path;
    private ?string $currentTag = null;

    public function __construct(string $path)
    {
        $this->path = rtrim($path, '/');
        if (!is_dir($this->path)) {
            mkdir($this->path, 0775, true);
        }
    }

    public function get(string $key, mixed $default = null): mixed
    {
        $file = $this->filePath($key);
        if (!file_exists($file)) {
            return $default;
        }

        $data = unserialize(file_get_contents($file));
        if ($data['expires_at'] !== 0 && $data['expires_at'] < time()) {
            @unlink($file);
            return $default;
        }

        return $data['value'];
    }

    public function set(string $key, mixed $value, int $ttl = 3600): bool
    {
        $file = $this->filePath($key);
        $data = [
            'value' => $value,
            'expires_at' => $ttl > 0 ? time() + $ttl : 0,
            'tags' => $this->currentTag ? [$this->currentTag] : [],
        ];

        // Track tag membership
        if ($this->currentTag) {
            $this->addToTagIndex($this->currentTag, $key);
        }

        $dir = dirname($file);
        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }

        return file_put_contents($file, serialize($data), LOCK_EX) !== false;
    }

    public function has(string $key): bool
    {
        return $this->get($key) !== null;
    }

    public function delete(string $key): bool
    {
        $file = $this->filePath($key);
        return file_exists($file) && @unlink($file);
    }

    public function flush(): bool
    {
        $files = glob($this->path . '/**/*.cache');
        foreach ($files ?: [] as $file) {
            @unlink($file);
        }
        // Also clean tag indexes
        $tagFiles = glob($this->path . '/tags/*.tag');
        foreach ($tagFiles ?: [] as $file) {
            @unlink($file);
        }
        return true;
    }

    public function remember(string $key, int $ttl, callable $callback): mixed
    {
        $value = $this->get($key);
        if ($value !== null) {
            return $value;
        }

        $value = $callback();
        $this->set($key, $value, $ttl);
        return $value;
    }

    public function tag(string $tag): static
    {
        $clone = clone $this;
        $clone->currentTag = $tag;
        return $clone;
    }

    public function flushTag(string $tag): bool
    {
        $tagFile = $this->path . '/tags/' . md5($tag) . '.tag';
        if (!file_exists($tagFile)) {
            return true;
        }

        $keys = unserialize(file_get_contents($tagFile)) ?: [];
        foreach ($keys as $key) {
            $this->delete($key);
        }

        @unlink($tagFile);
        return true;
    }

    private function filePath(string $key): string
    {
        $hash = md5($key);
        $prefix = substr($hash, 0, 2);
        return $this->path . '/' . $prefix . '/' . $hash . '.cache';
    }

    private function addToTagIndex(string $tag, string $key): void
    {
        $tagDir = $this->path . '/tags';
        if (!is_dir($tagDir)) {
            mkdir($tagDir, 0775, true);
        }

        $tagFile = $tagDir . '/' . md5($tag) . '.tag';
        $keys = file_exists($tagFile) ? (unserialize(file_get_contents($tagFile)) ?: []) : [];
        if (!in_array($key, $keys)) {
            $keys[] = $key;
        }
        file_put_contents($tagFile, serialize($keys), LOCK_EX);
    }
}
