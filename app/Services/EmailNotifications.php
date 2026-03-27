<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Config;

final class EmailNotifications
{
    public function __construct(
        private MailService $mail = new MailService(),
    ) {
    }

    public function sendVerifyEmail(string $toEmail, string $plainToken): void
    {
        $link = $this->url('/verify-email?token=' . rawurlencode($plainToken));
        $app = (string) Config::get('app.name', 'billo');
        $subject = "Confirm your email — {$app}";
        $text = "Hi,\n\nPlease confirm your email for {$app} by opening this link:\n{$link}\n\nIf you didn’t create an account, you can ignore this message.\n";
        $html = '<p>Hi,</p><p>Please confirm your email by clicking the button below.</p>'
            . '<p><a href="' . htmlspecialchars($link, ENT_QUOTES, 'UTF-8') . '" '
            . 'style="display:inline-block;padding:12px 20px;background:#16a34a;color:#fff;border-radius:999px;text-decoration:none;font-weight:600">'
            . 'Verify email</a></p>'
            . '<p style="color:#64748b;font-size:14px">If the button doesn’t work, copy and paste this URL:<br>'
            . htmlspecialchars($link, ENT_QUOTES, 'UTF-8') . '</p>';

        $this->mail->send($toEmail, $subject, $html, $text);
    }

    public function sendPasswordReset(string $toEmail, string $plainToken): void
    {
        $link = $this->url('/reset-password?token=' . rawurlencode($plainToken));
        $app = (string) Config::get('app.name', 'billo');
        $subject = "Reset your password — {$app}";
        $text = "We received a request to reset your {$app} password.\n\nOpen this link to choose a new password:\n{$link}\n\nThis link will expire soon. If you didn’t ask for this, you can ignore this email.\n";
        $html = '<p>We received a request to reset your password.</p>'
            . '<p><a href="' . htmlspecialchars($link, ENT_QUOTES, 'UTF-8') . '" '
            . 'style="display:inline-block;padding:12px 20px;background:#1e3a8a;color:#fff;border-radius:999px;text-decoration:none;font-weight:600">'
            . 'Reset password</a></p>'
            . '<p style="color:#64748b;font-size:14px">If you didn’t request this, you can ignore this message.</p>';

        $this->mail->send($toEmail, $subject, $html, $text);
    }

    public function sendOrganizationInvite(
        string $toEmail,
        string $organizationName,
        string $inviterName,
        string $roleLabel,
        string $plainToken,
    ): void {
        $link = $this->url('/invitations/accept?token=' . rawurlencode($plainToken));
        $app = (string) Config::get('app.name', 'billo');
        $subject = "You’ve been invited to {$organizationName} on {$app}";
        $text = "{$inviterName} invited you to join {$organizationName} on {$app} as {$roleLabel}.\n\nAccept the invite:\n{$link}\n";
        $html = '<p><strong>' . htmlspecialchars($inviterName, ENT_QUOTES, 'UTF-8') . '</strong> invited you to join '
            . '<strong>' . htmlspecialchars($organizationName, ENT_QUOTES, 'UTF-8') . '</strong>'
            . ' as <strong>' . htmlspecialchars($roleLabel, ENT_QUOTES, 'UTF-8') . '</strong>.</p>'
            . '<p><a href="' . htmlspecialchars($link, ENT_QUOTES, 'UTF-8') . '" '
            . 'style="display:inline-block;padding:12px 20px;background:#16a34a;color:#fff;border-radius:999px;text-decoration:none;font-weight:600">'
            . 'Accept invitation</a></p>';

        $this->mail->send($toEmail, $subject, $html, $text);
    }

    private function url(string $path): string
    {
        $base = rtrim((string) Config::get('app.url', ''), '/');
        $prefix = rtrim((string) Config::get('app.base_path', ''), '/');
        $path = '/' . ltrim($path, '/');
        if ($prefix !== '') {
            return $base . $prefix . $path;
        }

        return $base . $path;
    }
}
