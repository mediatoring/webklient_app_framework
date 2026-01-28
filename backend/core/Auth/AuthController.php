<?php

declare(strict_types=1);

namespace WebklientApp\Core\Auth;

use WebklientApp\Core\Http\Request;
use WebklientApp\Core\Http\JsonResponse;
use WebklientApp\Core\Exceptions\ValidationException;

class AuthController
{
    private AuthService $auth;

    public function __construct()
    {
        $config = \WebklientApp\Core\ConfigLoader::getInstance();
        $db = new \WebklientApp\Core\Database\Connection($config->get('database'));
        $jwt = new JWTService($config->get('auth.jwt'));
        $this->auth = new AuthService($db, $jwt);
    }

    public function login(Request $request): JsonResponse
    {
        $identity = $request->input('identity') ?? $request->input('email') ?? '';
        $password = $request->input('password') ?? '';

        if (empty($identity) || empty($password)) {
            throw new ValidationException('Identity and password are required.', [
                'identity' => empty($identity) ? 'Required' : null,
                'password' => empty($password) ? 'Required' : null,
            ]);
        }

        $result = $this->auth->login($identity, $password, $request->ip(), $request->userAgent());

        return JsonResponse::success($result, 'Login successful.');
    }

    public function logout(Request $request): JsonResponse
    {
        $token = $request->bearerToken();
        if ($token) {
            $this->auth->logout($token);
        }

        return JsonResponse::success(null, 'Logged out.');
    }

    public function refresh(Request $request): JsonResponse
    {
        $refreshToken = $request->input('refresh_token') ?? '';

        if (empty($refreshToken)) {
            throw new ValidationException('Refresh token is required.');
        }

        $result = $this->auth->refresh($refreshToken, $request->ip(), $request->userAgent());

        return JsonResponse::success($result, 'Token refreshed.');
    }
}
