<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Config;

final class MailService
{
    public function send(string $to, string $subject, string $htmlBody, string $textBody): bool
    {
        $to = trim($to);
        if ($to === '' || !filter_var($to, FILTER_VALIDATE_EMAIL)) {
            return false;
        }

        $driver = (string) Config::get('mail.driver', 'log');
        $fromAddr = (string) Config::get('mail.from_address', 'noreply@example.com');
        $fromName = (string) Config::get('mail.from_name', 'billo');

        return match ($driver) {
            'smtp' => $this->sendViaSmtp($to, $subject, $htmlBody, $textBody, $fromAddr, $fromName),
            'mail' => $this->sendViaPhpMail($to, $subject, $htmlBody, $textBody, $fromAddr, $fromName),
            default => $this->sendViaLog($to, $subject, $htmlBody, $textBody, $fromAddr, $fromName),
        };
    }

    private function sendViaLog(
        string $to,
        string $subject,
        string $htmlBody,
        string $textBody,
        string $fromAddr,
        string $fromName,
    ): bool {
        $line = str_repeat('=', 60) . PHP_EOL
            . date('c') . PHP_EOL
            . "From: {$fromName} <{$fromAddr}>" . PHP_EOL
            . "To: {$to}" . PHP_EOL
            . "Subject: {$subject}" . PHP_EOL . PHP_EOL
            . $textBody . PHP_EOL . PHP_EOL;
        $path = dirname(__DIR__, 2) . '/storage/logs/mail.log';
        return @file_put_contents($path, $line, FILE_APPEND | LOCK_EX) !== false;
    }

    private function sendViaPhpMail(
        string $to,
        string $subject,
        string $htmlBody,
        string $textBody,
        string $fromAddr,
        string $fromName,
    ): bool {
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

    private function sendViaSmtp(
        string $to,
        string $subject,
        string $htmlBody,
        string $textBody,
        string $fromAddr,
        string $fromName,
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

        $boundary = 'billo_' . bin2hex(random_bytes(8));
        $subjectLine = 'Subject: ' . $this->encodeHeaderSubject($subject);
        $message = "From: " . $this->encodeHeaderName($fromName) . " <{$fromAddr}>\r\n";
        $message .= "To: <{$to}>\r\n";
        $message .= "{$subjectLine}\r\n";
        $message .= "MIME-Version: 1.0\r\n";
        $message .= "Content-Type: multipart/alternative; boundary=\"{$boundary}\"\r\n";
        $message .= "X-Mailer: billo\r\n\r\n";
        $message .= "--{$boundary}\r\nContent-Type: text/plain; charset=UTF-8\r\n\r\n{$textBody}\r\n\r\n";
        $message .= "--{$boundary}\r\nContent-Type: text/html; charset=UTF-8\r\n\r\n{$htmlBody}\r\n\r\n";
        $message .= "--{$boundary}--\r\n";
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
