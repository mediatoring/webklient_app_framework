<?php

declare(strict_types=1);

namespace WebklientApp\Core\Cache;

interface CacheInterface
{
    public function get(string $key, mixed $default = null): mixed;
    public function set(string $key, mixed $value, int $ttl = 3600): bool;
    public function has(string $key): bool;
    public function delete(string $key): bool;
    public function flush(): bool;
    public function remember(string $key, int $ttl, callable $callback): mixed;

    /**
     * Tag-based operations for group invalidation.
     */
    public function tag(string $tag): static;
    public function flushTag(string $tag): bool;
}
