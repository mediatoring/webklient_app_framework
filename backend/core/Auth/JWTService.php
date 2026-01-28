<?php

declare(strict_types=1);

namespace WebklientApp\Core\Auth;

use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use WebklientApp\Core\Exceptions\AuthenticationException;

class JWTService
{
    private string $secret;
    private int $accessTtl;
    private int $refreshTtl;
    private string $algo;

    public function __construct(array $config)
    {
        $this->secret = $config['secret'] ?? '';
        $this->accessTtl = $config['access_ttl'] ?? 900;
        $this->refreshTtl = $config['refresh_ttl'] ?? 2592000;
        $this->algo = $config['algo'] ?? 'HS256';

        if (empty($this->secret)) {
            throw new \RuntimeException('JWT secret is not configured.');
        }
    }

    public function createAccessToken(int $userId, array $extra = []): string
    {
        $now = time();
        $payload = array_merge([
            'sub' => $userId,
            'type' => 'access',
            'iat' => $now,
            'exp' => $now + $this->accessTtl,
            'jti' => bin2hex(random_bytes(16)),
        ], $extra);

        return JWT::encode($payload, $this->secret, $this->algo);
    }

    public function createRefreshToken(int $userId): string
    {
        $now = time();
        $payload = [
            'sub' => $userId,
            'type' => 'refresh',
            'iat' => $now,
            'exp' => $now + $this->refreshTtl,
            'jti' => bin2hex(random_bytes(16)),
        ];

        return JWT::encode($payload, $this->secret, $this->algo);
    }

    public function decode(string $token): object
    {
        try {
            return JWT::decode($token, new Key($this->secret, $this->algo));
        } catch (\Exception $e) {
            throw new AuthenticationException('Invalid or expired token.');
        }
    }

    public function hashToken(string $token): string
    {
        return hash('sha256', $token);
    }

    public function getAccessTtl(): int
    {
        return $this->accessTtl;
    }

    public function getRefreshTtl(): int
    {
        return $this->refreshTtl;
    }
}
