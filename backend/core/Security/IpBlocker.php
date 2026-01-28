<?php

declare(strict_types=1);

namespace WebklientApp\Core\Security;

use WebklientApp\Core\Database\Connection;

class IpBlocker
{
    private Connection $db;
    private int $maxAttempts;
    private int $lockoutMinutes;

    public function __construct(Connection $db, int $maxAttempts = 5, int $lockoutMinutes = 15)
    {
        $this->db = $db;
        $this->maxAttempts = $maxAttempts;
        $this->lockoutMinutes = $lockoutMinutes;
    }

    public function isBlocked(string $ip): bool
    {
        $record = $this->db->fetchOne(
            "SELECT * FROM `ip_blocks` WHERE `ip_address` = ?",
            [$ip]
        );

        if (!$record) {
            return false;
        }

        if ($record['is_whitelisted']) {
            return false;
        }

        if ($record['blocked_until'] && strtotime($record['blocked_until']) > time()) {
            return true;
        }

        return false;
    }

    public function recordFailedAttempt(string $ip): void
    {
        $record = $this->db->fetchOne(
            "SELECT * FROM `ip_blocks` WHERE `ip_address` = ?",
            [$ip]
        );

        if (!$record) {
            $this->db->execute(
                "INSERT INTO `ip_blocks` (`ip_address`, `failed_attempts`, `reason`) VALUES (?, 1, 'Failed login')",
                [$ip]
            );
            return;
        }

        if ($record['is_whitelisted']) {
            return;
        }

        $attempts = (int) $record['failed_attempts'] + 1;
        $blockedUntil = null;

        if ($attempts >= $this->maxAttempts) {
            $blockedUntil = date('Y-m-d H:i:s', time() + ($this->lockoutMinutes * 60));
        }

        $this->db->execute(
            "UPDATE `ip_blocks` SET `failed_attempts` = ?, `blocked_until` = ?, `reason` = 'Failed login' WHERE `ip_address` = ?",
            [$attempts, $blockedUntil, $ip]
        );
    }

    public function clearAttempts(string $ip): void
    {
        $this->db->execute(
            "UPDATE `ip_blocks` SET `failed_attempts` = 0, `blocked_until` = NULL WHERE `ip_address` = ? AND `is_whitelisted` = 0",
            [$ip]
        );
    }
}
