<?php

declare(strict_types=1);

namespace WebklientApp\Core\Security;

class Hash
{
    private int $bcryptRounds;

    public function __construct(int $bcryptRounds = 12)
    {
        $this->bcryptRounds = $bcryptRounds;
    }

    public function make(string $password): string
    {
        return password_hash($password, PASSWORD_BCRYPT, ['cost' => $this->bcryptRounds]);
    }

    public function verify(string $password, string $hash): bool
    {
        return password_verify($password, $hash);
    }

    public function needsRehash(string $hash): bool
    {
        return password_needs_rehash($hash, PASSWORD_BCRYPT, ['cost' => $this->bcryptRounds]);
    }

    /**
     * Check password strength. Returns list of failures.
     */
    public static function checkStrength(string $password, int $minLength = 8): array
    {
        $failures = [];
        if (mb_strlen($password) < $minLength) {
            $failures[] = "Must be at least {$minLength} characters.";
        }
        if (!preg_match('/[A-Z]/', $password)) {
            $failures[] = 'Must contain at least one uppercase letter.';
        }
        if (!preg_match('/[a-z]/', $password)) {
            $failures[] = 'Must contain at least one lowercase letter.';
        }
        if (!preg_match('/[0-9]/', $password)) {
            $failures[] = 'Must contain at least one digit.';
        }
        if (!preg_match('/[^a-zA-Z0-9]/', $password)) {
            $failures[] = 'Must contain at least one special character.';
        }
        return $failures;
    }
}
