<?php

declare(strict_types=1);

namespace WebklientApp\Core\Mail;

class Message
{
    private string $from = '';
    private string $fromName = '';
    private array $to = [];
    private array $cc = [];
    private array $bcc = [];
    private array $replyTo = [];
    private string $subject = '';
    private string $textBody = '';
    private string $htmlBody = '';
    private array $headers = [];

    public function from(string $email, string $name = ''): self
    {
        $this->from = self::sanitizeEmail($email);
        $this->fromName = self::sanitizeHeaderValue($name);
        return $this;
    }

    public function to(string $email, string $name = ''): self
    {
        $this->to[] = ['email' => self::sanitizeEmail($email), 'name' => self::sanitizeHeaderValue($name)];
        return $this;
    }

    public function cc(string $email, string $name = ''): self
    {
        $this->cc[] = ['email' => self::sanitizeEmail($email), 'name' => self::sanitizeHeaderValue($name)];
        return $this;
    }

    public function bcc(string $email, string $name = ''): self
    {
        $this->bcc[] = ['email' => self::sanitizeEmail($email), 'name' => self::sanitizeHeaderValue($name)];
        return $this;
    }

    public function replyTo(string $email, string $name = ''): self
    {
        $this->replyTo[] = ['email' => self::sanitizeEmail($email), 'name' => self::sanitizeHeaderValue($name)];
        return $this;
    }

    public function subject(string $subject): self
    {
        $this->subject = self::sanitizeHeaderValue($subject);
        return $this;
    }

    public function text(string $body): self
    {
        $this->textBody = $body;
        return $this;
    }

    public function html(string $body): self
    {
        $this->htmlBody = $body;
        return $this;
    }

    public function header(string $name, string $value): self
    {
        $this->headers[$name] = $value;
        return $this;
    }

    public function getFrom(): string
    {
        return $this->from;
    }

    public function getFromName(): string
    {
        return $this->fromName;
    }

    public function getTo(): array
    {
        return $this->to;
    }

    public function getCc(): array
    {
        return $this->cc;
    }

    public function getBcc(): array
    {
        return $this->bcc;
    }

    public function getReplyTo(): array
    {
        return $this->replyTo;
    }

    public function getSubject(): string
    {
        return $this->subject;
    }

    public function getTextBody(): string
    {
        return $this->textBody;
    }

    public function getHtmlBody(): string
    {
        return $this->htmlBody;
    }

    /**
     * Build all recipients list (to + cc + bcc) for SMTP envelope.
     */
    public function getAllRecipients(): array
    {
        return array_merge(
            array_column($this->to, 'email'),
            array_column($this->cc, 'email'),
            array_column($this->bcc, 'email')
        );
    }

    private function formatAddress(string $email, string $name): string
    {
        if ($name === '') {
            return $email;
        }
        $encoded = mb_encode_mimeheader($name, 'UTF-8', 'Q');
        return "{$encoded} <{$email}>";
    }

    private function formatAddressList(array $addresses): string
    {
        return implode(', ', array_map(
            fn(array $a) => $this->formatAddress($a['email'], $a['name']),
            $addresses
        ));
    }

    /**
     * Build the complete RFC 2822 message (headers + body).
     */
    public function build(): string
    {
        $boundary = 'WKA_' . bin2hex(random_bytes(16));
        $headers = [];

        $headers[] = 'From: ' . $this->formatAddress($this->from, $this->fromName);
        $headers[] = 'To: ' . $this->formatAddressList($this->to);

        if (!empty($this->cc)) {
            $headers[] = 'Cc: ' . $this->formatAddressList($this->cc);
        }
        if (!empty($this->replyTo)) {
            $headers[] = 'Reply-To: ' . $this->formatAddressList($this->replyTo);
        }

        $headers[] = 'Subject: ' . mb_encode_mimeheader($this->subject, 'UTF-8', 'Q');
        $headers[] = 'Date: ' . date('r');
        $headers[] = 'Message-ID: <' . bin2hex(random_bytes(16)) . '@' . $this->extractDomain($this->from) . '>';
        $headers[] = 'MIME-Version: 1.0';
        $headers[] = 'X-Mailer: WebklientApp/1.0';

        foreach ($this->headers as $name => $value) {
            $headers[] = "{$name}: {$value}";
        }

        $hasText = $this->textBody !== '';
        $hasHtml = $this->htmlBody !== '';

        if ($hasText && $hasHtml) {
            $headers[] = "Content-Type: multipart/alternative; boundary=\"{$boundary}\"";
            $body = "--{$boundary}\r\n";
            $body .= "Content-Type: text/plain; charset=UTF-8\r\n";
            $body .= "Content-Transfer-Encoding: quoted-printable\r\n\r\n";
            $body .= quoted_printable_encode($this->textBody) . "\r\n";
            $body .= "--{$boundary}\r\n";
            $body .= "Content-Type: text/html; charset=UTF-8\r\n";
            $body .= "Content-Transfer-Encoding: quoted-printable\r\n\r\n";
            $body .= quoted_printable_encode($this->htmlBody) . "\r\n";
            $body .= "--{$boundary}--\r\n";
        } elseif ($hasHtml) {
            $headers[] = 'Content-Type: text/html; charset=UTF-8';
            $headers[] = 'Content-Transfer-Encoding: quoted-printable';
            $body = quoted_printable_encode($this->htmlBody);
        } else {
            $headers[] = 'Content-Type: text/plain; charset=UTF-8';
            $headers[] = 'Content-Transfer-Encoding: quoted-printable';
            $body = quoted_printable_encode($this->textBody);
        }

        return implode("\r\n", $headers) . "\r\n\r\n" . $body;
    }

    private function extractDomain(string $email): string
    {
        $parts = explode('@', $email);
        return $parts[1] ?? 'localhost';
    }

    /**
     * Strip CRLF and everything after them to prevent header injection.
     */
    private static function sanitizeHeaderValue(string $value): string
    {
        $value = str_replace("\0", '', $value);
        // Truncate at first CR or LF — anything after an injected newline is malicious
        $value = preg_replace('/[\r\n].*$/s', '', $value);
        return $value;
    }

    /**
     * Sanitize email address: strip control chars, angle brackets,
     * truncate at whitespace to prevent header injection.
     */
    private static function sanitizeEmail(string $email): string
    {
        $email = str_replace(["\0", '<', '>'], '', $email);
        // Truncate at first CR, LF, or space — email addresses never contain these
        $email = preg_replace('/[\r\n\s].*$/s', '', $email);
        return trim($email);
    }
}
