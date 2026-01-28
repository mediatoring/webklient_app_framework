<?php

declare(strict_types=1);

namespace WebklientApp\Core\Security;

use WebklientApp\Core\Database\Connection;
use WebklientApp\Core\Exceptions\RateLimitException;

class RateLimiter
{
    private Connection $db;
    private array $limits;
    private int $window;

    public function __construct(Connection $db, array $config)
    {
        $this->db = $db;
        $this->limits = $config;
        $this->window = $config['window'] ?? 3600;
    }

    /**
     * Check and increment rate limit. Throws on exceeded.
     *
     * @return array{limit: int, remaining: int, reset: int}
     */
    public function hit(string $key, string $group = 'authenticated'): array
    {
        $limit = $this->limits[$group] ?? $this->limits['authenticated'] ?? 1000;
        $now = time();
        $expiresAt = date('Y-m-d H:i:s', $now + $this->window);

        $record = $this->db->fetchOne(
            "SELECT * FROM `rate_limits` WHERE `key` = ?",
            [$key]
        );

        if (!$record || strtotime($record['expires_at']) < $now) {
            // New window
            $this->db->execute(
                "INSERT INTO `rate_limits` (`key`, `hits`, `window_start`, `expires_at`)
                 VALUES (?, 1, NOW(), ?)
                 ON DUPLICATE KEY UPDATE `hits` = 1, `window_start` = NOW(), `expires_at` = ?",
                [$key, $expiresAt, $expiresAt]
            );
            $hits = 1;
            $reset = $now + $this->window;
        } else {
            $hits = (int)$record['hits'] + 1;
            $reset = strtotime($record['expires_at']);

            $this->db->execute(
                "UPDATE `rate_limits` SET `hits` = ? WHERE `key` = ?",
                [$hits, $key]
            );
        }

        $remaining = max(0, $limit - $hits);

        if ($hits > $limit) {
            throw new RateLimitException(
                "Rate limit exceeded. Try again after " . date('H:i:s', $reset) . "."
            );
        }

        return [
            'limit' => $limit,
            'remaining' => $remaining,
            'reset' => $reset,
        ];
    }

    public function cleanExpired(): int
    {
        return $this->db->execute("DELETE FROM `rate_limits` WHERE `expires_at` < NOW()");
    }
}
