<?php
/**
 * DRXStore - SMTP Mailer
 * Full SMTP implementation (no external dependencies)
 * Falls back to PHP mail() if SMTP not configured.
 * Developed by Vineet
 */
class Mailer {
    private array $cfg;

    public function __construct(array $smtpConfig = []) {
        $this->cfg = $smtpConfig;
    }

    public static function fromSettings(): self {
        $cfg = getSettings();
        return new self([
            'host'     => $cfg['smtp_host']   ?? '',
            'port'     => (int)($cfg['smtp_port']   ?? 587),
            'user'     => $cfg['smtp_user']   ?? '',
            'pass'     => $cfg['smtp_pass']   ?? '',
            'from'     => $cfg['smtp_from']   ?? ($cfg['store_email'] ?? ADMIN_EMAIL),
            'name'     => $cfg['smtp_name']   ?? ($cfg['store_name']  ?? APP_NAME),
            'secure'   => $cfg['smtp_secure'] ?? 'tls', // tls or ssl
        ]);
    }

    public function send(string $to, string $subject, string $htmlBody): bool {
        // Use SMTP if configured, otherwise fall back to mail()
        if (!empty($this->cfg['host']) && !empty($this->cfg['user'])) {
            return $this->sendSmtp($to, $subject, $htmlBody);
        }
        return $this->sendMail($to, $subject, $htmlBody);
    }

    private function sendMail(string $to, string $subject, string $body): bool {
        $from = $this->cfg['from'] ?? ADMIN_EMAIL;
        $name = $this->cfg['name'] ?? APP_NAME;
        $headers  = "MIME-Version: 1.0\r\nContent-Type: text/html; charset=UTF-8\r\n";
        $headers .= "From: {$name} <{$from}>\r\nReply-To: {$from}\r\nX-Mailer: DRXStore\r\n";
        return @mail($to, $subject, $body, $headers);
    }

    private function sendSmtp(string $to, string $subject, string $htmlBody): bool {
        $host   = $this->cfg['host'];
        $port   = (int)($this->cfg['port'] ?? 587);
        $user   = $this->cfg['user'];
        $pass   = $this->cfg['pass'];
        $from   = $this->cfg['from'];
        $name   = $this->cfg['name'];
        $secure = strtolower($this->cfg['secure'] ?? 'tls');

        $errno  = 0; $errstr = '';
        $timeout = 15;

        // Determine connection type
        if ($secure === 'ssl') {
            $host_str = "ssl://{$host}";
        } else {
            $host_str = $host;
        }

        $socket = @fsockopen($host_str, $port, $errno, $errstr, $timeout);
        if (!$socket) {
            error_log("DRXStore SMTP: Cannot connect to {$host}:{$port} - {$errstr}");
            // Fallback to mail()
            return $this->sendMail($to, $subject, $htmlBody);
        }

        // Helper functions
        $recv = function() use ($socket): string {
            $r = '';
            while (($line = fgets($socket, 512)) !== false) {
                $r .= $line;
                if ($line[3] === ' ') break; // End of multi-line response
            }
            return $r;
        };
        $send = function(string $cmd) use ($socket): void {
            fputs($socket, $cmd . "\r\n");
        };

        try {
            $recv(); // Greeting
            $send("EHLO localhost");
            $r = $recv();

            // STARTTLS upgrade for port 587
            if ($secure === 'tls' && strpos($r, 'STARTTLS') !== false) {
                $send("STARTTLS");
                $recv();
                stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT);
                $send("EHLO localhost");
                $recv();
            }

            // AUTH LOGIN
            $send("AUTH LOGIN");
            $recv();
            $send(base64_encode($user));
            $recv();
            $send(base64_encode($pass));
            $authR = $recv();

            if (strpos($authR, '235') === false) {
                fclose($socket);
                error_log("DRXStore SMTP AUTH failed: {$authR}");
                return $this->sendMail($to, $subject, $htmlBody);
            }

            // Envelope
            $send("MAIL FROM:<{$from}>");
            $recv();
            $send("RCPT TO:<{$to}>");
            $recv();

            // Data
            $send("DATA");
            $recv();

            // Build message
            $boundary = md5(uniqid('drx'));
            $headers  = "From: =?UTF-8?B?" . base64_encode($name) . "?= <{$from}>\r\n";
            $headers .= "To: {$to}\r\n";
            $headers .= "Subject: =?UTF-8?B?" . base64_encode($subject) . "?=\r\n";
            $headers .= "MIME-Version: 1.0\r\n";
            $headers .= "Content-Type: multipart/alternative; boundary=\"{$boundary}\"\r\n";
            $headers .= "X-Mailer: DRXStore/" . APP_VERSION . "\r\n";
            $headers .= "Date: " . date('r') . "\r\n";

            $body = "--{$boundary}\r\n";
            $body .= "Content-Type: text/plain; charset=UTF-8\r\n";
            $body .= "Content-Transfer-Encoding: base64\r\n\r\n";
            $body .= chunk_split(base64_encode(strip_tags(str_replace(['<br>', '<br/>', '<br />', '</p>'], "\n", $htmlBody)))) . "\r\n";
            $body .= "--{$boundary}\r\n";
            $body .= "Content-Type: text/html; charset=UTF-8\r\n";
            $body .= "Content-Transfer-Encoding: base64\r\n\r\n";
            $body .= chunk_split(base64_encode($htmlBody)) . "\r\n";
            $body .= "--{$boundary}--\r\n";

            $message = $headers . "\r\n" . $body . "\r\n.\r\n";
            // Escape lines starting with dot
            $message = preg_replace('/^\./', '..', $message);
            fputs($socket, $message);
            $sendR = $recv();

            $send("QUIT");
            fclose($socket);

            return strpos($sendR, '250') !== false;

        } catch (Exception $e) {
            if (is_resource($socket)) fclose($socket);
            error_log("DRXStore SMTP error: " . $e->getMessage());
            return $this->sendMail($to, $subject, $htmlBody);
        }
    }

    public static function test(string $to, array $config): array {
        $mailer = new self($config);
        $body   = mailTemplate('Test Email', '<p>This is a test email from DRXStore. If you received this, your SMTP settings are working correctly.</p><p><strong>Sent at:</strong> ' . date('d M Y, h:i A') . '</p>');
        $ok     = $mailer->send($to, 'DRXStore SMTP Test', $body);
        return ['success' => $ok, 'message' => $ok ? 'Test email sent successfully!' : 'Failed to send. Check SMTP credentials.'];
    }
}

/**
 * Global sendMail helper — uses SMTP if configured
 */
function sendMail(string $to, string $subject, string $htmlBody): bool {
    return Mailer::fromSettings()->send($to, $subject, $htmlBody);
}
