<?php

declare(strict_types=1);

namespace WebklientApp\Core\Mail;

use WebklientApp\Core\ConfigLoader;
use WebklientApp\Core\View\ViewRenderer;

/**
 * High-level mail service. Combines Mailer + ViewRenderer to send
 * templated emails. Provides convenience methods for common emails.
 */
class MailService
{
    private Mailer $mailer;
    private ViewRenderer $view;
    private string $appName;
    private string $appUrl;

    public function __construct(?Mailer $mailer = null, ?ViewRenderer $viewRenderer = null)
    {
        $this->mailer = $mailer ?? new Mailer();
        $this->view = $viewRenderer ?? new ViewRenderer();

        $config = ConfigLoader::getInstance();
        $this->appName = $config->env('APP_NAME', 'WebklientApp');
        $this->appUrl = rtrim($config->env('APP_URL', 'http://localhost'), '/');
    }

    /**
     * Send a templated email.
     *
     * @param string $to         Recipient email
     * @param string $subject    Email subject
     * @param string $template   View template name (dot notation, e.g. 'emails.welcome')
     * @param array  $data       Template variables
     * @param string $toName     Recipient display name
     */
    public function send(string $to, string $subject, string $template, array $data = [], string $toName = ''): bool
    {
        $data['appName'] = $this->appName;
        $data['appUrl'] = $this->appUrl;

        $html = $this->view->render($template, $data);
        $text = $this->htmlToPlainText($html);

        $message = (new Message())
            ->to($to, $toName)
            ->subject($subject)
            ->html($html)
            ->text($text);

        return $this->mailer->send($message);
    }

    /**
     * Send password reset email.
     */
    public function sendPasswordReset(string $email, string $displayName, string $token): bool
    {
        $resetUrl = "{$this->appUrl}/password-reset?token={$token}&email=" . urlencode($email);

        return $this->send(
            $email,
            "Obnovení hesla — {$this->appName}",
            'emails.password-reset',
            [
                'displayName' => $displayName,
                'resetUrl' => $resetUrl,
                'token' => $token,
                'expiresMinutes' => 60,
            ],
            $displayName
        );
    }

    /**
     * Send welcome email after registration or account creation.
     */
    public function sendWelcome(string $email, string $displayName, string $username): bool
    {
        return $this->send(
            $email,
            "Vítejte v {$this->appName}",
            'emails.welcome',
            [
                'displayName' => $displayName,
                'username' => $username,
                'loginUrl' => "{$this->appUrl}/login",
            ],
            $displayName
        );
    }

    /**
     * Send a generic notification email.
     */
    public function sendNotification(string $email, string $displayName, string $subject, string $body, ?string $actionUrl = null, ?string $actionLabel = null): bool
    {
        return $this->send(
            $email,
            $subject,
            'emails.notification',
            [
                'displayName' => $displayName,
                'body' => $body,
                'actionUrl' => $actionUrl,
                'actionLabel' => $actionLabel ?? 'Zobrazit',
            ],
            $displayName
        );
    }

    private function htmlToPlainText(string $html): string
    {
        $text = preg_replace('/<br\s*\/?>/', "\n", $html);
        $text = preg_replace('/<\/p>/', "\n\n", $text);
        $text = preg_replace('/<a[^>]+href="([^"]*)"[^>]*>(.*?)<\/a>/', '$2 ($1)', $text);
        $text = strip_tags($text);
        $text = html_entity_decode($text, ENT_QUOTES, 'UTF-8');
        $text = preg_replace('/[ \t]+/', ' ', $text);
        $text = preg_replace('/\n{3,}/', "\n\n", $text);
        return trim($text);
    }
}
