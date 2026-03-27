<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Config;

final class MailService
{
    /**
     * @param list<array{filename:string,content:string,mime:string}>|null $attachments
     */
    public function send(
        string $to,
        string $subject,
        string $htmlBody,
        string $textBody,
        ?array $attachments = null,
    ): bool {
        $to = trim($to);
        if ($to === '' || !filter_var($to, FILTER_VALIDATE_EMAIL)) {
            return false;
        }

        $driver = (string) Config::get('mail.driver', 'log');
        $fromAddr = (string) Config::get('mail.from_address', 'noreply@example.com');
        $fromName = (string) Config::get('mail.from_name', 'billo');
        $atts = $this->normalizeAttachments($attachments);

        return match ($driver) {
            'smtp' => $this->sendViaSmtp($to, $subject, $htmlBody, $textBody, $fromAddr, $fromName, $atts),
            'mail' => $this->sendViaPhpMail($to, $subject, $htmlBody, $textBody, $fromAddr, $fromName, $atts),
            default => $this->sendViaLog($to, $subject, $htmlBody, $textBody, $fromAddr, $fromName, $atts),
        };
    }

    /**
     * @param list<array{filename:string,content:string,mime:string}> $attachments
     */
    private function sendViaLog(
        string $to,
        string $subject,
        string $htmlBody,
        string $textBody,
        string $fromAddr,
        string $fromName,
        array $attachments,
    ): bool {
        $line = str_repeat('=', 60) . PHP_EOL
            . date('c') . PHP_EOL
            . "From: {$fromName} <{$fromAddr}>" . PHP_EOL
            . "To: {$to}" . PHP_EOL
            . "Subject: {$subject}" . PHP_EOL;
        foreach ($attachments as $a) {
            $line .= 'Attachment: ' . $a['filename'] . ' (' . strlen($a['content']) . " bytes)\n";
        }
        $line .= PHP_EOL . $textBody . PHP_EOL . PHP_EOL;
        $path = dirname(__DIR__, 2) . '/storage/logs/mail.log';

        return @file_put_contents($path, $line, FILE_APPEND | LOCK_EX) !== false;
    }

    /**
     * @param list<array{filename:string,content:string,mime:string}> $attachments
     */
    private function sendViaPhpMail(
        string $to,
        string $subject,
        string $htmlBody,
        string $textBody,
        string $fromAddr,
        string $fromName,
        array $attachments,
    ): bool {
        if ($attachments === []) {
            $boundary = 'billo_' . bin2hex(random_bytes(8));
            $headers = [];
            $headers[] = 'MIME-Version: 1.0';
            $headers[] = 'Content-Type: multipart/alternative; boundary="' . $boundary . '"';
            $headers[] = 'From: ' . $this->encodeHeaderName($fromName) . " <{$fromAddr}>";
            $headers[] = 'Reply-To: ' . $fromAddr;
            $headers[] = 'X-Mailer: billo';

            $body = "--{$boundary}\r\n";
            $body .= "Content-Type: text/plain; charset=UTF-8\r\n\r\n";
            $body .= $textBody . "\r\n\r\n";
            $body .= "--{$boundary}\r\n";
            $body .= "Content-Type: text/html; charset=UTF-8\r\n\r\n";
            $body .= $htmlBody . "\r\n\r\n";
            $body .= "--{$boundary}--";

            return @mail($to, $this->encodeHeaderSubject($subject), $body, implode("\r\n", $headers));
        }

        $rootBoundary = 'billo_m_' . bin2hex(random_bytes(8));
        $altBoundary = 'billo_a_' . bin2hex(random_bytes(8));
        $headers = [];
        $headers[] = 'MIME-Version: 1.0';
        $headers[] = 'Content-Type: multipart/mixed; boundary="' . $rootBoundary . '"';
        $headers[] = 'From: ' . $this->encodeHeaderName($fromName) . " <{$fromAddr}>";
        $headers[] = 'Reply-To: ' . $fromAddr;
        $headers[] = 'X-Mailer: billo';

        $body = "--{$rootBoundary}\r\n";
        $body .= "Content-Type: multipart/alternative; boundary=\"{$altBoundary}\"\r\n\r\n";
        $body .= "--{$altBoundary}\r\n";
        $body .= "Content-Type: text/plain; charset=UTF-8\r\n\r\n";
        $body .= $textBody . "\r\n\r\n";
        $body .= "--{$altBoundary}\r\n";
        $body .= "Content-Type: text/html; charset=UTF-8\r\n\r\n";
        $body .= $htmlBody . "\r\n\r\n";
        $body .= "--{$altBoundary}--\r\n";

        foreach ($attachments as $a) {
            $body .= "--{$rootBoundary}\r\n";
            $body .= 'Content-Type: ' . $a['mime'] . '; name="' . $this->encodeMimeFilename($a['filename']) . "\"\r\n";
            $body .= "Content-Transfer-Encoding: base64\r\n";
            $body .= 'Content-Disposition: attachment; filename="' . $this->encodeMimeFilename($a['filename']) . "\"\r\n\r\n";
            $body .= rtrim(chunk_split(base64_encode($a['content']), 76, "\r\n")) . "\r\n\r\n";
        }
        $body .= "--{$rootBoundary}--";

        return @mail($to, $this->encodeHeaderSubject($subject), $body, implode("\r\n", $headers));
    }

    /**
     * @param list<array{filename:string,content:string,mime:string}> $attachments
     */
    private function sendViaSmtp(
        string $to,
        string $subject,
        string $htmlBody,
        string $textBody,
        string $fromAddr,
        string $fromName,
        array $attachments,
    ): bool {
        $host = (string) Config::get('mail.smtp.host', '127.0.0.1');
        $port = (int) Config::get('mail.smtp.port', 587);
        $encryption = strtolower((string) Config::get('mail.smtp.encryption', 'tls'));
        $user = (string) Config::get('mail.smtp.username', '');
        $pass = (string) Config::get('mail.smtp.password', '');
        $timeout = (int) Config::get('mail.smtp.timeout', 20);

        $remote = ($encryption === 'ssl') ? 'ssl://' . $host : 'tcp://' . $host;
        $fp = @stream_socket_client($remote . ':' . $port, $errno, $errstr, $timeout, STREAM_CLIENT_CONNECT);
        if ($fp === false) {
            error_log("Billo SMTP connect failed: {$errstr} ({$errno})");

            return false;
        }
        stream_set_timeout($fp, $timeout);

        $read = static function () use ($fp): string {
            $data = '';
            while (!feof($fp)) {
                $line = fgets($fp, 515);
                if ($line === false) {
                    break;
                }
                $data .= $line;
                if (strlen($line) < 4 || $line[3] === ' ') {
                    break;
                }
            }

            return $data;
        };

        $send = static function (string $cmd) use ($fp): void {
            fwrite($fp, $cmd . "\r\n");
        };

        $read();
        $send('EHLO billo');
        $banner = $read();
        if (!str_starts_with($banner, '250')) {
            fclose($fp);

            return false;
        }

        if ($encryption === 'tls') {
            $send('STARTTLS');
            $r = $read();
            if (!str_starts_with($r, '220')) {
                fclose($fp);

                return false;
            }
            if (!stream_socket_enable_crypto($fp, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
                fclose($fp);

                return false;
            }
            $send('EHLO billo');
            $read();
        }

        if ($user !== '' && $pass !== '') {
            $send('AUTH LOGIN');
            $read();
            $send(base64_encode($user));
            $read();
            $send(base64_encode($pass));
            $auth = $read();
            if (!str_starts_with($auth, '235')) {
                error_log('Billo SMTP auth failed: ' . trim($auth));
                fclose($fp);

                return false;
            }
        }

        $send('MAIL FROM:<' . $fromAddr . '>');
        if (!str_starts_with($read(), '250')) {
            fclose($fp);

            return false;
        }
        $send('RCPT TO:<' . $to . '>');
        if (!str_starts_with($read(), '250')) {
            fclose($fp);

            return false;
        }
        $send('DATA');
        if (!str_starts_with($read(), '354')) {
            fclose($fp);

            return false;
        }

        $message = $this->buildSmtpMessage($to, $fromAddr, $fromName, $subject, $htmlBody, $textBody, $attachments);
        $message = preg_replace('/(\r\n|^)\./', '$1..', $message) ?? $message;
        fwrite($fp, $message . "\r\n.\r\n");
        if (!str_starts_with($read(), '250')) {
            fclose($fp);

            return false;
        }
        $send('QUIT');
        $read();
        fclose($fp);

        return true;
    }

    /**
     * @param list<array{filename:string,content:string,mime:string}> $attachments
     */
    private function buildSmtpMessage(
        string $to,
        string $fromAddr,
        string $fromName,
        string $subject,
        string $htmlBody,
        string $textBody,
        array $attachments,
    ): string {
        $subjectLine = 'Subject: ' . $this->encodeHeaderSubject($subject);
        $fromLine = 'From: ' . $this->encodeHeaderName($fromName) . " <{$fromAddr}>";
        $base = "{$fromLine}\r\nTo: <{$to}>\r\n{$subjectLine}\r\nMIME-Version: 1.0\r\nX-Mailer: billo\r\n";

        if ($attachments === []) {
            $boundary = 'billo_' . bin2hex(random_bytes(8));

            return $base . "Content-Type: multipart/alternative; boundary=\"{$boundary}\"\r\n\r\n"
                . "--{$boundary}\r\nContent-Type: text/plain; charset=UTF-8\r\n\r\n{$textBody}\r\n\r\n"
                . "--{$boundary}\r\nContent-Type: text/html; charset=UTF-8\r\n\r\n{$htmlBody}\r\n\r\n"
                . "--{$boundary}--";
        }

        $rootBoundary = 'billo_m_' . bin2hex(random_bytes(8));
        $altBoundary = 'billo_a_' . bin2hex(random_bytes(8));
        $msg = $base . "Content-Type: multipart/mixed; boundary=\"{$rootBoundary}\"\r\n\r\n";
        $msg .= "--{$rootBoundary}\r\n";
        $msg .= "Content-Type: multipart/alternative; boundary=\"{$altBoundary}\"\r\n\r\n";
        $msg .= "--{$altBoundary}\r\nContent-Type: text/plain; charset=UTF-8\r\n\r\n{$textBody}\r\n\r\n";
        $msg .= "--{$altBoundary}\r\nContent-Type: text/html; charset=UTF-8\r\n\r\n{$htmlBody}\r\n\r\n";
        $msg .= "--{$altBoundary}--\r\n";

        foreach ($attachments as $a) {
            $msg .= "--{$rootBoundary}\r\n";
            $msg .= 'Content-Type: ' . $a['mime'] . '; name="' . $this->encodeMimeFilename($a['filename']) . "\"\r\n";
            $msg .= "Content-Transfer-Encoding: base64\r\n";
            $msg .= 'Content-Disposition: attachment; filename="' . $this->encodeMimeFilename($a['filename']) . "\"\r\n\r\n";
            $msg .= rtrim(chunk_split(base64_encode($a['content']), 76, "\r\n")) . "\r\n\r\n";
        }
        $msg .= "--{$rootBoundary}--";

        return $msg;
    }

    /**
     * @return list<array{filename:string,content:string,mime:string}>
     */
    private function normalizeAttachments(?array $attachments): array
    {
        if ($attachments === null || $attachments === []) {
            return [];
        }
        $out = [];
        foreach ($attachments as $a) {
            if (!is_array($a)) {
                continue;
            }
            $fn = isset($a['filename']) ? trim((string) $a['filename']) : '';
            $content = $a['content'] ?? '';
            $mime = isset($a['mime']) ? trim((string) $a['mime']) : 'application/octet-stream';
            if ($fn === '' || !is_string($content) || $content === '') {
                continue;
            }
            if (strlen($fn) > 180) {
                $fn = substr($fn, 0, 180);
            }
            $out[] = ['filename' => $fn, 'content' => $content, 'mime' => $mime !== '' ? $mime : 'application/octet-stream'];
        }

        return $out;
    }

    private function encodeMimeFilename(string $filename): string
    {
        return addcslashes($filename, "\\\"\r\n");
    }

    private function encodeHeaderSubject(string $subject): string
    {
        if (preg_match('/[^\x20-\x7E]/', $subject)) {
            return '=?UTF-8?B?' . base64_encode($subject) . '?=';
        }

        return $subject;
    }

    private function encodeHeaderName(string $name): string
    {
        if (preg_match('/[^\x20-\x7E]/', $name)) {
            return '=?UTF-8?B?' . base64_encode($name) . '?=';
        }

        return $name;
    }
}
