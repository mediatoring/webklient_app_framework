<?php

declare(strict_types=1);

namespace WebklientApp\Core\Auth;

use WebklientApp\Core\Database\Connection;
use WebklientApp\Core\Database\QueryBuilder;
use WebklientApp\Core\Exceptions\AuthenticationException;
use WebklientApp\Core\Exceptions\ValidationException;

class AuthService
{
    private Connection $db;
    private JWTService $jwt;

    public function __construct(Connection $db, JWTService $jwt)
    {
        $this->db = $db;
        $this->jwt = $jwt;
    }

    /**
     * @return array{access_token: string, refresh_token: string, expires_in: int, user: array}
     */
    public function login(string $identity, string $password, string $ip, string $userAgent): array
    {
        // Find user by email or username
        $user = $this->db->fetchOne(
            "SELECT * FROM `users` WHERE (`email` = ? OR `username` = ?) AND `is_active` = 1",
            [$identity, $identity]
        );

        if (!$user || !password_verify($password, $user['password_hash'])) {
            throw new AuthenticationException('Invalid credentials.');
        }

        // Update last login
        $this->db->execute("UPDATE `users` SET `last_login_at` = NOW() WHERE `id` = ?", [$user['id']]);

        return $this->issueTokens((int) $user['id'], $ip, $userAgent, $user);
    }

    /**
     * @return array{access_token: string, refresh_token: string, expires_in: int}
     */
    public function refresh(string $refreshToken, string $ip, string $userAgent): array
    {
        $payload = $this->jwt->decode($refreshToken);

        if (($payload->type ?? '') !== 'refresh') {
            throw new AuthenticationException('Invalid token type.');
        }

        // Check token in DB
        $hash = $this->jwt->hashToken($refreshToken);
        $stored = $this->db->fetchOne(
            "SELECT * FROM `api_tokens` WHERE `token_hash` = ? AND `type` = 'refresh' AND `revoked_at` IS NULL",
            [$hash]
        );

        if (!$stored || strtotime($stored['expires_at']) < time()) {
            throw new AuthenticationException('Refresh token is invalid or expired.');
        }

        // Revoke old refresh token
        $this->db->execute("UPDATE `api_tokens` SET `revoked_at` = NOW() WHERE `id` = ?", [$stored['id']]);

        $userId = (int) $payload->sub;

        // Verify user still active
        $user = $this->db->fetchOne("SELECT `id` FROM `users` WHERE `id` = ? AND `is_active` = 1", [$userId]);
        if (!$user) {
            throw new AuthenticationException('User account is deactivated.');
        }

        return $this->issueTokens($userId, $ip, $userAgent);
    }

    public function logout(string $token): void
    {
        $hash = $this->jwt->hashToken($token);

        // Revoke the access token
        $this->db->execute(
            "UPDATE `api_tokens` SET `revoked_at` = NOW() WHERE `token_hash` = ?",
            [$hash]
        );

        // Also revoke related refresh token for this user
        try {
            $payload = $this->jwt->decode($token);
            $this->db->execute(
                "UPDATE `api_tokens` SET `revoked_at` = NOW() WHERE `user_id` = ? AND `type` = 'refresh' AND `revoked_at` IS NULL",
                [$payload->sub]
            );
        } catch (\Throwable) {
            // Token might be expired, still revoke by hash
        }
    }

    public function validateAccessToken(string $token): array
    {
        $payload = $this->jwt->decode($token);

        if (($payload->type ?? '') !== 'access') {
            throw new AuthenticationException('Invalid token type.');
        }

        $hash = $this->jwt->hashToken($token);
        $stored = $this->db->fetchOne(
            "SELECT * FROM `api_tokens` WHERE `token_hash` = ? AND `type` = 'access' AND `revoked_at` IS NULL",
            [$hash]
        );

        if (!$stored || strtotime($stored['expires_at']) < time()) {
            throw new AuthenticationException('Token is invalid or expired.');
        }

        // Update last_used_at
        $this->db->execute("UPDATE `api_tokens` SET `last_used_at` = NOW() WHERE `id` = ?", [$stored['id']]);

        $user = $this->db->fetchOne(
            "SELECT `id`, `username`, `email`, `display_name`, `is_active` FROM `users` WHERE `id` = ? AND `is_active` = 1",
            [$payload->sub]
        );

        if (!$user) {
            throw new AuthenticationException('User not found or deactivated.');
        }

        // Attach impersonation info if present
        if (isset($payload->impersonated_by)) {
            $user['impersonated_by'] = $payload->impersonated_by;
        }

        return $user;
    }

    public function createImpersonationToken(int $sudoUserId, int $targetUserId, string $ip, string $userAgent): array
    {
        $target = $this->db->fetchOne("SELECT `id`, `username`, `email`, `display_name` FROM `users` WHERE `id` = ?", [$targetUserId]);
        if (!$target) {
            throw new ValidationException('Target user not found.', ['user_id' => $targetUserId]);
        }

        $accessToken = $this->jwt->createAccessToken($targetUserId, [
            'impersonated_by' => $sudoUserId,
        ]);

        $this->storeToken($targetUserId, $accessToken, 'access', $this->jwt->getAccessTtl(), $ip, $userAgent);

        return [
            'access_token' => $accessToken,
            'expires_in' => $this->jwt->getAccessTtl(),
            'impersonating' => $target,
        ];
    }

    private function issueTokens(int $userId, string $ip, string $userAgent, ?array $user = null): array
    {
        $accessToken = $this->jwt->createAccessToken($userId);
        $refreshToken = $this->jwt->createRefreshToken($userId);

        $this->storeToken($userId, $accessToken, 'access', $this->jwt->getAccessTtl(), $ip, $userAgent);
        $this->storeToken($userId, $refreshToken, 'refresh', $this->jwt->getRefreshTtl(), $ip, $userAgent);

        $result = [
            'access_token' => $accessToken,
            'refresh_token' => $refreshToken,
            'token_type' => 'Bearer',
            'expires_in' => $this->jwt->getAccessTtl(),
        ];

        if ($user) {
            unset($user['password_hash']);
            $result['user'] = $user;
        }

        return $result;
    }

    private function storeToken(int $userId, string $token, string $type, int $ttl, string $ip, string $userAgent): void
    {
        $hash = $this->jwt->hashToken($token);
        $expiresAt = date('Y-m-d H:i:s', time() + $ttl);

        $this->db->execute(
            "INSERT INTO `api_tokens` (`user_id`, `token_hash`, `type`, `expires_at`, `ip_address`, `user_agent`) VALUES (?, ?, ?, ?, ?, ?)",
            [$userId, $hash, $type, $expiresAt, $ip, substr($userAgent, 0, 500)]
        );
    }
}
