<?php

declare(strict_types=1);

namespace WebklientApp\Tests\Security;

use PHPUnit\Framework\TestCase;
use WebklientApp\Core\Mail\Message;

/**
 * Security tests: Email header injection, CRLF injection,
 * address sanitization, and message integrity.
 */
class MailSecurityTest extends TestCase
{
    public function testCrlfInjectionInRecipientEmail(): void
    {
        $msg = new Message();
        $msg->to("victim@example.com\r\nBCC: attacker@evil.com");

        $recipients = $msg->getAllRecipients();
        $this->assertCount(1, $recipients);
        $this->assertStringNotContainsString("\r", $recipients[0]);
        $this->assertStringNotContainsString("\n", $recipients[0]);
    }

    public function testCrlfInjectionInFromEmail(): void
    {
        $msg = new Message();
        $msg->from("sender@example.com\r\nBCC: attacker@evil.com");

        $built = $msg->to('test@example.com')->subject('Test')->text('Body')->build();
        $headerBlock = explode("\r\n\r\n", $built, 2)[0];

        $this->assertStringNotContainsString('attacker@evil.com', $headerBlock);
    }

    public function testCrlfInjectionInSubject(): void
    {
        $msg = new Message();
        $msg->from('sender@example.com')
            ->to('test@example.com')
            ->subject("Hello\r\nBCC: attacker@evil.com")
            ->text('Body');

        $built = $msg->build();
        $headerBlock = explode("\r\n\r\n", $built, 2)[0];

        $this->assertStringNotContainsString('attacker@evil.com', $headerBlock);
    }

    public function testCrlfInjectionInRecipientName(): void
    {
        $msg = new Message();
        $msg->from('sender@example.com')
            ->to('test@example.com', "Victim\r\nBCC: attacker@evil.com")
            ->subject('Test')
            ->text('Body');

        $built = $msg->build();
        $this->assertStringNotContainsString('attacker@evil.com', $built);
    }

    public function testCrlfInjectionInCcEmail(): void
    {
        $msg = new Message();
        $msg->cc("cc@example.com\r\nBCC: attacker@evil.com");

        $recipients = $msg->getAllRecipients();
        foreach ($recipients as $r) {
            $this->assertStringNotContainsString("\r", $r);
            $this->assertStringNotContainsString("\n", $r);
        }
    }

    public function testCrlfInjectionInBccEmail(): void
    {
        $msg = new Message();
        $msg->bcc("bcc@example.com\r\nTo: attacker@evil.com");

        $recipients = $msg->getAllRecipients();
        foreach ($recipients as $r) {
            $this->assertStringNotContainsString("\r", $r);
            $this->assertStringNotContainsString("\n", $r);
        }
    }

    public function testCrlfInjectionInReplyTo(): void
    {
        $msg = new Message();
        $msg->from('sender@example.com')
            ->to('test@example.com')
            ->replyTo("reply@example.com\r\nBCC: attacker@evil.com")
            ->subject('Test')
            ->text('Body');

        $built = $msg->build();
        $this->assertStringNotContainsString('attacker@evil.com', $built);
    }

    public function testNullByteInEmail(): void
    {
        $msg = new Message();
        $msg->to("test@example.com\0.evil.com");

        $recipients = $msg->getAllRecipients();
        $this->assertStringNotContainsString("\0", $recipients[0]);
    }

    public function testAngleBracketsStrippedFromEmail(): void
    {
        $msg = new Message();
        $msg->to('<attacker@evil.com>');

        $recipients = $msg->getAllRecipients();
        $this->assertStringNotContainsString('<', $recipients[0]);
        $this->assertStringNotContainsString('>', $recipients[0]);
    }

    public function testBccNotInBuiltHeaders(): void
    {
        $msg = new Message();
        $msg->from('sender@example.com')
            ->to('recipient@example.com')
            ->bcc('secret@example.com')
            ->subject('Test')
            ->text('Body');

        $built = $msg->build();
        $headerBlock = explode("\r\n\r\n", $built, 2)[0];

        $this->assertStringNotContainsString('secret@example.com', $headerBlock);
        $this->assertStringNotContainsString('Bcc:', $headerBlock);
    }

    public function testBccIncludedInRecipientsList(): void
    {
        $msg = new Message();
        $msg->to('to@example.com')
            ->cc('cc@example.com')
            ->bcc('bcc@example.com');

        $recipients = $msg->getAllRecipients();
        $this->assertCount(3, $recipients);
        $this->assertContains('bcc@example.com', $recipients);
    }

    public function testSubjectEncodedForUtf8(): void
    {
        $msg = new Message();
        $msg->from('sender@example.com')
            ->to('test@example.com')
            ->subject('Příliš žluťoučký kůň')
            ->text('Body');

        $built = $msg->build();
        $this->assertStringContainsString('Subject:', $built);
    }

    public function testMultipartAlternativeStructure(): void
    {
        $msg = new Message();
        $msg->from('sender@example.com')
            ->to('test@example.com')
            ->subject('Test')
            ->text('Plain text')
            ->html('<p>HTML</p>');

        $built = $msg->build();
        $this->assertStringContainsString('multipart/alternative', $built);
        $this->assertStringContainsString('text/plain', $built);
        $this->assertStringContainsString('text/html', $built);
    }

    public function testHtmlOnlyMessage(): void
    {
        $msg = new Message();
        $msg->from('sender@example.com')
            ->to('test@example.com')
            ->subject('Test')
            ->html('<p>HTML only</p>');

        $built = $msg->build();
        $this->assertStringContainsString('text/html', $built);
        $this->assertStringNotContainsString('multipart', $built);
    }

    public function testPlainTextOnlyMessage(): void
    {
        $msg = new Message();
        $msg->from('sender@example.com')
            ->to('test@example.com')
            ->subject('Test')
            ->text('Plain text only');

        $built = $msg->build();
        $this->assertStringContainsString('text/plain', $built);
        $this->assertStringNotContainsString('multipart', $built);
    }

    public function testMessageContainsMimeVersion(): void
    {
        $msg = new Message();
        $msg->from('sender@example.com')
            ->to('test@example.com')
            ->subject('Test')
            ->text('Body');

        $built = $msg->build();
        $this->assertStringContainsString('MIME-Version: 1.0', $built);
    }

    public function testMessageContainsMessageId(): void
    {
        $msg = new Message();
        $msg->from('sender@example.com')
            ->to('test@example.com')
            ->subject('Test')
            ->text('Body');

        $built = $msg->build();
        $this->assertMatchesRegularExpression('/Message-ID: <[a-f0-9]+@example\.com>/', $built);
    }

    public function testScriptTagInHtmlBodyNotStripped(): void
    {
        $msg = new Message();
        $msg->from('sender@example.com')
            ->to('test@example.com')
            ->subject('Test')
            ->html('<p>Hello</p><script>alert(1)</script>');

        $built = $msg->build();
        $this->assertStringContainsString('script', $built);
    }

    public function testCustomHeadersIncluded(): void
    {
        $msg = new Message();
        $msg->from('sender@example.com')
            ->to('test@example.com')
            ->subject('Test')
            ->header('X-Custom', 'value')
            ->text('Body');

        $built = $msg->build();
        $this->assertStringContainsString('X-Custom: value', $built);
    }

    public function testOverlongSubjectHandled(): void
    {
        $longSubject = str_repeat('A', 1000);
        $msg = new Message();
        $msg->from('sender@example.com')
            ->to('test@example.com')
            ->subject($longSubject)
            ->text('Body');

        $built = $msg->build();
        $this->assertStringContainsString('Subject:', $built);
    }

    public function testEmptyRecipientListDetected(): void
    {
        $msg = new Message();
        $recipients = $msg->getAllRecipients();
        $this->assertEmpty($recipients);
    }

    public function testMultipleInjectionVectorsInSingleMessage(): void
    {
        $msg = new Message();
        $msg->from("sender@example.com\r\nX-Injected: yes")
            ->to("victim@example.com\r\nBCC: spy@evil.com", "Name\r\nX-Evil: true")
            ->subject("Subject\r\nBCC: hidden@evil.com")
            ->text('Body');

        $built = $msg->build();
        $this->assertStringNotContainsString('X-Injected:', $built);
        $this->assertStringNotContainsString('spy@evil.com', $built);
        $this->assertStringNotContainsString('X-Evil:', $built);
        $this->assertStringNotContainsString('hidden@evil.com', $built);
    }
}
