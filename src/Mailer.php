<?php

require_once __DIR__ . '/Logger.php';

class Mailer
{
    private array $cfg;

    public function __construct(array $cfg)
    {
        $this->cfg = $cfg['email'] ?? [];
    }

    // -------------------------------------------------------------------------
    // Public API
    // -------------------------------------------------------------------------

    public function sendSuccess(string $title, string $siteUrl, int $todayCount): void
    {
        if (empty($this->cfg['enabled']) || empty($this->cfg['on_success'])) {
            return;
        }

        $prefix  = $this->cfg['subject_prefix'] ?? '[AutoBlogger]';
        $subject = "{$prefix} Published: {$title}";

        $body = "AutoBlogger successfully published a new post.\n"
              . "\n"
              . "Title:       {$title}\n"
              . "Site:        {$siteUrl}\n"
              . "Posts today: {$todayCount}\n"
              . "Time:        " . date('Y-m-d H:i:s') . "\n"
              . "\n"
              . "--\n"
              . "AutoBlogger";

        $this->send($subject, $body);
    }

    public function sendError(string $errorMessage, string $context = ''): void
    {
        if (empty($this->cfg['enabled']) || empty($this->cfg['on_error'])) {
            return;
        }

        $prefix  = $this->cfg['subject_prefix'] ?? '[AutoBlogger]';
        $subject = "{$prefix} Error";

        $body = "AutoBlogger encountered an error.\n"
              . "\n"
              . "Error:   {$errorMessage}\n"
              . "Context: {$context}\n"
              . "Time:    " . date('Y-m-d H:i:s') . "\n"
              . "\n"
              . "--\n"
              . "AutoBlogger";

        $this->send($subject, $body);
    }

    // -------------------------------------------------------------------------
    // Delivery
    // -------------------------------------------------------------------------

    private function send(string $subject, string $body): void
    {
        try {
            if (!empty($this->cfg['use_smtp'])) {
                $this->sendSmtp($subject, $body);
            } else {
                $this->sendMail($subject, $body);
            }
        } catch (Exception $e) {
            Logger::error('Mailer: ' . $e->getMessage());
        }
    }

    // -------------------------------------------------------------------------
    // PHP mail()
    // -------------------------------------------------------------------------

    private function sendMail(string $subject, string $body): void
    {
        $recipient = $this->cfg['recipient'] ?? '';
        $hostname  = $_SERVER['HTTP_HOST'] ?? 'localhost';
        $headers   = "From: autoblogger@{$hostname}\r\nContent-Type: text/plain; charset=UTF-8";

        $sent = mail($recipient, $subject, $body, $headers);

        if (!$sent) {
            throw new RuntimeException("mail() failed for recipient: {$recipient}");
        }
    }

    // -------------------------------------------------------------------------
    // Raw SMTP via fsockopen
    // -------------------------------------------------------------------------

    private function sendSmtp(string $subject, string $body): void
    {
        $host      = $this->cfg['smtp_host'] ?? '';
        $port      = (int) ($this->cfg['smtp_port'] ?? 587);
        $user      = $this->cfg['smtp_user'] ?? '';
        $pass      = $this->cfg['smtp_pass'] ?? '';
        $recipient = $this->cfg['recipient'] ?? '';

        $errno  = 0;
        $errstr = '';

        Logger::info("Mailer: connecting to {$host}:{$port}");

        // Port 465 = implicit SSL (smtps), port 587/25 = STARTTLS
        if ($port === 465) {
            $socket = @fsockopen("ssl://{$host}", $port, $errno, $errstr, 10);
        } else {
            $socket = @fsockopen($host, $port, $errno, $errstr, 10);
        }

        if ($socket === false) {
            throw new RuntimeException("SMTP connect failed ({$errno}): {$errstr}");
        }

        // Read server greeting (220)
        $greeting = $this->smtpRead($socket);
        Logger::info('Mailer: SMTP greeting — ' . trim($greeting));
        $this->smtpExpect($greeting, '220', 'Unexpected SMTP greeting');

        // EHLO
        $this->smtpWrite($socket, "EHLO autoblogger\r\n");
        $ehlo = $this->smtpReadMultiline($socket);
        Logger::info('Mailer: EHLO accepted');

        // STARTTLS for port 587
        if ($port === 587) {
            $this->smtpWrite($socket, "STARTTLS\r\n");
            $tls = $this->smtpRead($socket);
            $this->smtpExpect($tls, '220', 'STARTTLS not accepted');

            if (!stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
                throw new RuntimeException('SMTP: could not enable TLS encryption');
            }

            // Re-send EHLO after STARTTLS
            $this->smtpWrite($socket, "EHLO autoblogger\r\n");
            $this->smtpReadMultiline($socket);
            Logger::info('Mailer: STARTTLS enabled');
        }

        // AUTH LOGIN
        $this->smtpWrite($socket, "AUTH LOGIN\r\n");
        $auth = $this->smtpRead($socket);
        $this->smtpExpect($auth, '334', 'AUTH LOGIN not accepted');

        // Username
        $this->smtpWrite($socket, base64_encode($user) . "\r\n");
        $userResp = $this->smtpRead($socket);
        $this->smtpExpect($userResp, '334', 'SMTP username not accepted');

        // Password
        $this->smtpWrite($socket, base64_encode($pass) . "\r\n");
        $passResp = $this->smtpRead($socket);
        $this->smtpExpect($passResp, '235', 'SMTP authentication failed');
        Logger::info('Mailer: SMTP authenticated');

        // MAIL FROM
        $this->smtpWrite($socket, "MAIL FROM:<{$user}>\r\n");
        $fromResp = $this->smtpRead($socket);
        $this->smtpExpect($fromResp, '250', 'MAIL FROM rejected');

        // RCPT TO
        $this->smtpWrite($socket, "RCPT TO:<{$recipient}>\r\n");
        $rcptResp = $this->smtpRead($socket);
        $this->smtpExpect($rcptResp, '250', 'RCPT TO rejected');

        // DATA command
        $this->smtpWrite($socket, "DATA\r\n");
        $dataResp = $this->smtpRead($socket);
        $this->smtpExpect($dataResp, '354', 'DATA command rejected');

        // Message headers + blank line + body + end-of-data marker
        $message = "From: {$user}\r\n"
                 . "To: {$recipient}\r\n"
                 . "Subject: {$subject}\r\n"
                 . "MIME-Version: 1.0\r\n"
                 . "Content-Type: text/plain; charset=UTF-8\r\n"
                 . "\r\n"
                 . $body
                 . "\r\n.\r\n";

        $this->smtpWrite($socket, $message);
        $sentResp = $this->smtpRead($socket);
        $this->smtpExpect($sentResp, '250', 'Message DATA rejected');
        Logger::info('Mailer: SMTP message accepted by server');

        // QUIT
        $this->smtpWrite($socket, "QUIT\r\n");
        fclose($socket);

        Logger::info("Mailer: email sent via SMTP to {$recipient}");
    }

    // -------------------------------------------------------------------------
    // SMTP socket helpers
    // -------------------------------------------------------------------------

    /** Write a command to the SMTP socket. */
    private function smtpWrite($socket, string $data): void
    {
        fwrite($socket, $data);
    }

    /** Read a single response line from the SMTP socket. */
    private function smtpRead($socket): string
    {
        $response = '';
        while ($line = fgets($socket, 515)) {
            $response .= $line;
            // Multi-line responses have a '-' after the code; single/last line has a space
            if (isset($line[3]) && $line[3] === ' ') {
                break;
            }
        }
        return $response;
    }

    /**
     * Read a potentially multi-line EHLO-style response until the
     * last line (code + space) is received.
     */
    private function smtpReadMultiline($socket): string
    {
        return $this->smtpRead($socket); // smtpRead already handles multi-line correctly
    }

    /**
     * Assert that $response starts with the expected SMTP code.
     * Throws RuntimeException if not.
     */
    private function smtpExpect(string $response, string $code, string $context): void
    {
        if (strpos(trim($response), $code) !== 0) {
            throw new RuntimeException(
                "Mailer SMTP — {$context}. Expected {$code}, got: " . trim($response)
            );
        }
    }
}
