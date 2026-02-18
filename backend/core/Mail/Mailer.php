<?php

declare(strict_types=1);

namespace WebklientApp\Core\Mail;

use WebklientApp\Core\ConfigLoader;

/**
 * Raw SMTP client. Connects to SMTP server and sends messages
 * using socket-level communication. Supports STARTTLS, AUTH LOGIN,
 * AUTH PLAIN, and direct SSL connections.
 */
class Mailer
{
    private string $host;
    private int $port;
    private string $encryption; // 'tls', 'ssl', or ''
    private string $username;
    private string $password;
    private string $defaultFrom;
    private string $defaultFromName;
    private int $timeout;

    /** @var resource|null */
    private $socket = null;
    private array $log = [];

    public function __construct(?array $config = null)
    {
        if ($config === null) {
            $loader = ConfigLoader::getInstance();
            $config = $loader->get('mail', []);
        }

        $this->host = $config['host'] ?? 'localhost';
        $this->port = (int) ($config['port'] ?? 587);
        $this->encryption = $config['encryption'] ?? 'tls';
        $this->username = $config['username'] ?? '';
        $this->password = $config['password'] ?? '';
        $this->defaultFrom = $config['from_address'] ?? 'noreply@localhost';
        $this->defaultFromName = $config['from_name'] ?? 'WebklientApp';
        $this->timeout = (int) ($config['timeout'] ?? 30);
    }

    public function send(Message $message): bool
    {
        if ($message->getFrom() === '') {
            $message->from($this->defaultFrom, $this->defaultFromName);
        }

        $recipients = $message->getAllRecipients();
        if (empty($recipients)) {
            throw new \RuntimeException('No recipients defined.');
        }

        $this->log = [];

        try {
            $this->connect();
            $this->ehlo();

            if ($this->encryption === 'tls') {
                $this->startTls();
                $this->ehlo();
            }

            if ($this->username !== '') {
                $this->authenticate();
            }

            $this->mailFrom($message->getFrom());

            foreach ($recipients as $recipient) {
                $this->rcptTo($recipient);
            }

            $this->data($message->build());
            $this->quit();

            return true;
        } catch (\Throwable $e) {
            $this->log[] = "ERROR: {$e->getMessage()}";
            $this->close();
            throw $e;
        }
    }

    public function getLog(): array
    {
        return $this->log;
    }

    private function connect(): void
    {
        $host = $this->host;
        if ($this->encryption === 'ssl') {
            $host = 'ssl://' . $host;
        }

        $errno = 0;
        $errstr = '';
        $this->socket = @fsockopen($host, $this->port, $errno, $errstr, $this->timeout);

        if (!$this->socket) {
            throw new \RuntimeException("SMTP connection failed: {$errstr} ({$errno})");
        }

        stream_set_timeout($this->socket, $this->timeout);
        $this->readResponse(220);
    }

    private function ehlo(): void
    {
        $hostname = gethostname() ?: 'localhost';
        $this->sendCommand("EHLO {$hostname}", 250);
    }

    private function startTls(): void
    {
        $this->sendCommand('STARTTLS', 220);

        $result = stream_socket_enable_crypto(
            $this->socket,
            true,
            STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT | STREAM_CRYPTO_METHOD_TLSv1_3_CLIENT
        );

        if ($result !== true) {
            throw new \RuntimeException('STARTTLS negotiation failed.');
        }
    }

    private function authenticate(): void
    {
        $this->sendCommand('AUTH LOGIN', 334);
        $this->sendCommand(base64_encode($this->username), 334);
        $this->sendCommand(base64_encode($this->password), 235);
    }

    private function mailFrom(string $from): void
    {
        $this->sendCommand("MAIL FROM:<{$from}>", 250);
    }

    private function rcptTo(string $to): void
    {
        $this->sendCommand("RCPT TO:<{$to}>", 250);
    }

    private function data(string $content): void
    {
        $this->sendCommand('DATA', 354);

        // Dot-stuffing per RFC 5321
        $lines = explode("\r\n", $content);
        foreach ($lines as $line) {
            if (str_starts_with($line, '.')) {
                $line = '.' . $line;
            }
            fwrite($this->socket, $line . "\r\n");
        }

        $this->sendCommand('.', 250);
    }

    private function quit(): void
    {
        try {
            $this->sendCommand('QUIT', 221);
        } catch (\Throwable) {
            // Ignore quit errors
        }
        $this->close();
    }

    private function close(): void
    {
        if ($this->socket !== null && is_resource($this->socket)) {
            fclose($this->socket);
        }
        $this->socket = null;
    }

    private function sendCommand(string $command, int $expectedCode): string
    {
        $logCmd = str_starts_with($command, 'AUTH') || strlen($command) > 50
            ? substr($command, 0, 20) . '...'
            : $command;
        $this->log[] = ">>> {$logCmd}";

        fwrite($this->socket, $command . "\r\n");
        return $this->readResponse($expectedCode);
    }

    private function readResponse(int $expectedCode): string
    {
        $response = '';
        $maxLines = 100;
        while ($maxLines-- > 0) {
            $line = fgets($this->socket, 4096);
            if ($line === false) {
                throw new \RuntimeException('SMTP server closed connection unexpectedly.');
            }
            $response .= $line;

            // Lines shorter than 4 chars (just code + CRLF) are terminal
            if (!isset($line[3]) || $line[3] === ' ') {
                break;
            }
            // Multi-line responses have '-' after the 3-digit code
            if ($line[3] !== '-') {
                break;
            }
        }

        $this->log[] = "<<< " . trim($response);

        $code = (int) substr($response, 0, 3);
        if ($code !== $expectedCode) {
            throw new \RuntimeException(
                "SMTP error: expected {$expectedCode}, got {$code}. Response: " . trim($response)
            );
        }

        return $response;
    }
}
