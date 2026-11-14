<?php

declare(strict_types=1);

namespace App\Services;

use Setting;

/**
 * Sending a plain email, via real SMTP when the office has entered
 * mail server details, falling back to PHP's built-in mail() otherwise.
 *
 * Why SMTP was added: PHP's mail() only works when the server itself
 * has a configured local mail transport (sendmail/Postfix). Many
 * shared-hosting accounts either have none, or deliver through it
 * straight to spam because the message carries no authentication the
 * receiving server can verify. Talking to a real mailbox provider's
 * SMTP server directly (Gmail, the domain's own cPanel mail, Zoho,
 * etc.) with a username/password fixes both problems and works the
 * same whether this runs on localhost or on the live host.
 *
 * No Composer/PHPMailer dependency -- this is a small hand-rolled SMTP
 * client over a plain socket (fsockopen), supporting implicit TLS
 * (smtps, typically port 465) and STARTTLS (typically port 587), with
 * AUTH LOGIN. That covers every mainstream provider's documented
 * settings without adding a package manager to the project.
 *
 * Same two rules as SmsService, for the same reasons:
 *  - Never throws.
 *  - Never logs the message body (only the outcome and the address
 *    domain, never the full address or the secret it carries).
 */
final class EmailService
{
    public const KEY_ENABLED      = 'email_enabled';
    public const KEY_FROM_NAME    = 'email_from_name';
    public const KEY_FROM_ADDRESS = 'email_from_address';

    // SMTP: optional. When KEY_SMTP_HOST is blank, send() uses mail().
    public const KEY_SMTP_HOST       = 'email_smtp_host';
    public const KEY_SMTP_PORT       = 'email_smtp_port';
    public const KEY_SMTP_ENCRYPTION = 'email_smtp_encryption'; // '', 'ssl', or 'tls'
    public const KEY_SMTP_USERNAME   = 'email_smtp_username';
    public const KEY_SMTP_PASSWORD   = 'email_smtp_password';

    private Setting $settings;

    public function __construct(?Setting $settings = null)
    {
        $this->settings = $settings ?? new Setting();
    }

    /** @return array<string, string> */
    public function config(): array
    {
        $out = [];
        foreach ([
            self::KEY_ENABLED, self::KEY_FROM_NAME, self::KEY_FROM_ADDRESS,
            self::KEY_SMTP_HOST, self::KEY_SMTP_PORT, self::KEY_SMTP_ENCRYPTION,
            self::KEY_SMTP_USERNAME, self::KEY_SMTP_PASSWORD,
        ] as $key) {
            try {
                $out[$key] = (string) $this->settings->get($key, '');
            } catch (\Throwable $e) {
                $out[$key] = '';
            }
        }
        // Fall back to the general "Admin email" setting for the From
        // address, so a fresh install needs only one email typed in.
        if ($out[self::KEY_FROM_ADDRESS] === '') {
            try {
                $out[self::KEY_FROM_ADDRESS] = (string) $this->settings->get('admin_email', '');
            } catch (\Throwable $e) {
                // leave blank
            }
        }
        return $out;
    }

    private function usesSmtp(array $c): bool
    {
        return trim($c[self::KEY_SMTP_HOST]) !== '';
    }

    public function isConfigured(): bool
    {
        $c = $this->config();
        if ($c[self::KEY_ENABLED] !== '1' || $c[self::KEY_FROM_ADDRESS] === ''
            || filter_var($c[self::KEY_FROM_ADDRESS], FILTER_VALIDATE_EMAIL) === false) {
            return false;
        }

        return $this->usesSmtp($c) ? true : function_exists('mail');
    }

    /**
     * Send one plain-text email. Answers true only when the message was
     * accepted for delivery (by the SMTP server, or by mail() -- neither
     * proves it reached the inbox). Never throws.
     */
    public function send(?string $to, string $subject, string $body): bool
    {
        if (!$this->isConfigured()) {
            return false;
        }
        $to = trim((string) $to);
        if ($to === '' || filter_var($to, FILTER_VALIDATE_EMAIL) === false || trim($body) === '') {
            return false;
        }

        $c = $this->config();
        $fromAddress = $c[self::KEY_FROM_ADDRESS];
        $fromName = $c[self::KEY_FROM_NAME] !== '' ? $c[self::KEY_FROM_NAME] : 'Class Portal';

        try {
            $ok = $this->usesSmtp($c)
                ? $this->sendViaSmtp($c, $to, $fromAddress, $fromName, $subject, $body)
                : $this->sendViaMailFunction($to, $fromAddress, $fromName, $subject, $body);
        } catch (\Throwable $e) {
            error_log('Email send failed: ' . $e->getMessage());
            $ok = false;
        }

        $this->note($to, $ok);
        return $ok;
    }

    private function sendViaMailFunction(string $to, string $fromAddress, string $fromName, string $subject, string $body): bool
    {
        $headers = implode("\r\n", [
            'From: ' . $this->encodeHeader($fromName) . ' <' . $fromAddress . '>',
            'Reply-To: ' . $fromAddress,
            'Content-Type: text/plain; charset=UTF-8',
            'X-Mailer: PHP/' . phpversion(),
        ]);

        return (bool) @mail($to, $this->encodeHeader($subject), $body, $headers);
    }

    /**
     * A minimal RFC 5321 SMTP conversation over a plain socket: connect
     * (plain, or TLS-wrapped immediately for "ssl"/port 465), EHLO,
     * STARTTLS when requested, AUTH LOGIN, MAIL FROM/RCPT TO/DATA, QUIT.
     * Throws \RuntimeException with the server's own reply text on any
     * step that fails, so error_log() carries a useful reason.
     */
    private function sendViaSmtp(array $c, string $to, string $fromAddress, string $fromName, string $subject, string $body): bool
    {
        $host = trim($c[self::KEY_SMTP_HOST]);
        $port = (int) ($c[self::KEY_SMTP_PORT] !== '' ? $c[self::KEY_SMTP_PORT] : 587);
        $encryption = strtolower(trim($c[self::KEY_SMTP_ENCRYPTION]));
        $username = $c[self::KEY_SMTP_USERNAME];
        $password = $c[self::KEY_SMTP_PASSWORD];

        $transport = $encryption === 'ssl' ? 'ssl://' : '';
        $sock = @stream_socket_client(
            $transport . $host . ':' . $port,
            $errno,
            $errstr,
            15,
            STREAM_CLIENT_CONNECT,
            stream_context_create(['ssl' => ['verify_peer' => true, 'verify_peer_name' => true]])
        );
        if ($sock === false) {
            throw new \RuntimeException("SMTP connect to {$host}:{$port} failed: {$errstr} ({$errno})");
        }
        stream_set_timeout($sock, 15);

        $this->smtpExpect($sock, 220);
        $this->smtpCommand($sock, 'EHLO ' . (parse_url(BASE_URL, PHP_URL_HOST) ?: 'localhost'), 250);

        if ($encryption === 'tls') {
            $this->smtpCommand($sock, 'STARTTLS', 220);
            if (!@stream_socket_enable_crypto($sock, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
                throw new \RuntimeException('SMTP STARTTLS negotiation failed.');
            }
            // EHLO again over the now-encrypted channel, as the RFC requires.
            $this->smtpCommand($sock, 'EHLO ' . (parse_url(BASE_URL, PHP_URL_HOST) ?: 'localhost'), 250);
        }

        if (trim($username) !== '') {
            $this->smtpCommand($sock, 'AUTH LOGIN', 334);
            $this->smtpCommand($sock, base64_encode($username), 334);
            $this->smtpCommand($sock, base64_encode($password), 235);
        }

        $this->smtpCommand($sock, 'MAIL FROM:<' . $fromAddress . '>', 250);
        $this->smtpCommand($sock, 'RCPT TO:<' . $to . '>', [250, 251]);
        $this->smtpCommand($sock, 'DATA', 354);

        $headers = implode("\r\n", [
            'From: ' . $this->encodeHeader($fromName) . ' <' . $fromAddress . '>',
            'To: <' . $to . '>',
            'Subject: ' . $this->encodeHeader($subject),
            'Date: ' . date('r'),
            'Message-ID: <' . bin2hex(random_bytes(10)) . '@' . (parse_url(BASE_URL, PHP_URL_HOST) ?: 'localhost') . '>',
            'MIME-Version: 1.0',
            'Content-Type: text/plain; charset=UTF-8',
            'Content-Transfer-Encoding: 8bit',
        ]);
        // Dot-stuff any line that starts with a lone ".", per RFC 5321 4.5.2,
        // and end the message with the required "<CRLF>.<CRLF>".
        $dataBody = preg_replace('/^\./m', '..', str_replace(["\r\n", "\r"], "\n", $body));
        $dataBody = str_replace("\n", "\r\n", $dataBody);

        fwrite($sock, $headers . "\r\n\r\n" . $dataBody . "\r\n.\r\n");
        $this->smtpReadReply($sock, 250);

        $this->smtpCommand($sock, 'QUIT', [221, 0]); // 0: some servers close before replying to QUIT
        fclose($sock);

        return true;
    }

    /** @param resource $sock */
    private function smtpCommand($sock, string $line, int|array $expect): void
    {
        fwrite($sock, $line . "\r\n");
        $this->smtpReadReply($sock, $expect);
    }

    /** @param resource $sock */
    private function smtpExpect($sock, int|array $expect): void
    {
        $this->smtpReadReply($sock, $expect);
    }

    /** @param resource $sock */
    private function smtpReadReply($sock, int|array $expect): string
    {
        $expect = is_array($expect) ? $expect : [$expect];
        $reply = '';
        while (!feof($sock)) {
            $line = fgets($sock, 515);
            if ($line === false) {
                break;
            }
            $reply .= $line;
            // A multi-line reply has "-" after the code on every line but
            // the last ("250-..." then finally "250 ...").
            if (strlen($line) < 4 || $line[3] !== '-') {
                break;
            }
        }
        $code = (int) substr($reply, 0, 3);
        if ($expect !== [0] && !in_array($code, $expect, true)) {
            throw new \RuntimeException('SMTP server rejected the message: ' . trim($reply));
        }
        return $reply;
    }

    private function encodeHeader(string $value): string
    {
        return '=?UTF-8?B?' . base64_encode($value) . '?=';
    }

    /** Record only the outcome and the domain, never the full address or the message. */
    private function note(string $to, bool $ok): void
    {
        $domain = strpos($to, '@') !== false ? substr($to, strpos($to, '@')) : '';
        error_log(sprintf('Email %s to ...%s', $ok ? 'sent' : 'failed', $domain));
    }
}
