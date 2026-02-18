<?php

declare(strict_types=1);

namespace WebklientApp\Core\Http\Controllers;

use WebklientApp\Core\Http\Request;
use WebklientApp\Core\Http\JsonResponse;
use WebklientApp\Core\Mail\MailService;
use WebklientApp\Core\Security\Hash;
use WebklientApp\Core\ConfigLoader;
use WebklientApp\Core\Exceptions\ValidationException;
use WebklientApp\Core\Exceptions\NotFoundException;

class PasswordResetController extends BaseController
{
    /**
     * POST /api/auth/forgot-password
     * Request a password reset link. Always returns success to prevent email enumeration.
     */
    public function forgotPassword(Request $request): JsonResponse
    {
        $email = trim($request->input('email') ?? '');
        if ($email === '') {
            throw new ValidationException('Email is required.', ['email' => 'Required']);
        }

        $user = $this->db->fetchOne(
            "SELECT `id`, `email`, `display_name` FROM `users` WHERE `email` = ? AND `is_active` = 1",
            [$email]
        );

        if ($user) {
            $this->db->execute(
                "UPDATE `password_resets` SET `used_at` = NOW() WHERE `user_id` = ? AND `used_at` IS NULL",
                [$user['id']]
            );

            $token = bin2hex(random_bytes(32));
            $tokenHash = hash('sha256', $token);
            $expiresAt = date('Y-m-d H:i:s', time() + 3600);

            $this->db->execute(
                "INSERT INTO `password_resets` (`user_id`, `token_hash`, `expires_at`, `ip_address`) VALUES (?, ?, ?, ?)",
                [$user['id'], $tokenHash, $expiresAt, $request->ip()]
            );

            try {
                $mail = new MailService();
                $mail->sendPasswordReset($user['email'], $user['display_name'] ?? '', $token);
            } catch (\Throwable) {
                // Log silently, don't expose mail errors
            }
        }

        return JsonResponse::success(
            null,
            'Pokud je email registrován, odeslali jsme odkaz pro obnovení hesla.'
        );
    }

    /**
     * POST /api/auth/reset-password
     * Reset password using a valid token.
     */
    public function resetPassword(Request $request): JsonResponse
    {
        $token = $request->input('token') ?? '';
        $email = trim($request->input('email') ?? '');
        $password = $request->input('password') ?? '';
        $passwordConfirmation = $request->input('password_confirmation') ?? '';

        $errors = [];
        if ($token === '') {
            $errors['token'] = 'Required';
        }
        if ($email === '') {
            $errors['email'] = 'Required';
        }
        if (strlen($password) < 8) {
            $errors['password'] = 'Min 8 characters';
        }
        if ($password !== $passwordConfirmation) {
            $errors['password_confirmation'] = 'Passwords do not match';
        }
        if (!empty($errors)) {
            throw new ValidationException('Validation failed.', $errors);
        }

        $tokenHash = hash('sha256', $token);
        $reset = $this->db->fetchOne(
            "SELECT pr.*, u.email FROM `password_resets` pr
             JOIN `users` u ON u.id = pr.user_id
             WHERE pr.token_hash = ? AND pr.used_at IS NULL AND pr.expires_at > NOW()
             AND u.email = ? AND u.is_active = 1",
            [$tokenHash, $email]
        );

        if (!$reset) {
            throw new ValidationException('Token je neplatný nebo vypršel.', ['token' => 'Invalid or expired']);
        }

        $config = ConfigLoader::getInstance();
        $hasher = new Hash((int) $config->env('BCRYPT_ROUNDS', 12));
        $hash = $hasher->make($password);
        $this->db->execute("UPDATE `users` SET `password_hash` = ? WHERE `id` = ?", [$hash, $reset['user_id']]);

        $this->db->execute("UPDATE `password_resets` SET `used_at` = NOW() WHERE `id` = ?", [$reset['id']]);

        $this->db->execute(
            "UPDATE `api_tokens` SET `revoked_at` = NOW() WHERE `user_id` = ? AND `revoked_at` IS NULL",
            [$reset['user_id']]
        );

        return JsonResponse::success(null, 'Heslo bylo úspěšně změněno. Přihlaste se novým heslem.');
    }
}
