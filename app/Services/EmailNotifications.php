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

    /**
     * @param array<string, mixed> $invoice row from InvoiceRepository::findWithLines (includes lines)
     */
    public function sendInvoiceToClient(
        string $toEmail,
        string $organizationName,
        array $invoice,
        ?string $pdfBinary = null,
        string $pdfFilename = 'invoice.pdf',
    ): bool {
        $app = (string) Config::get('app.name', 'billo');
        $num = (string) ($invoice['invoice_number'] ?? 'Invoice');
        $invKind = (string) ($invoice['invoice_kind'] ?? 'invoice');
        $subject = $invKind === 'credit_note'
            ? "{$num} — Credit note from {$organizationName} — {$app}"
            : "{$num} from {$organizationName} — {$app}";

        $currency = (string) ($invoice['currency'] ?? 'NGN');
        $issue = (string) ($invoice['issue_date'] ?? '');
        $due = isset($invoice['due_date']) && $invoice['due_date'] !== null && $invoice['due_date'] !== ''
            ? (string) $invoice['due_date'] : '';
        $status = (string) ($invoice['status'] ?? '');

        $clientBits = array_filter([
            $invoice['client_name'] ?? '',
            $invoice['client_company'] ?? '',
        ], static fn ($v) => is_string($v) && $v !== '');
        $clientLine = $clientBits !== [] ? implode(' · ', $clientBits) : 'Client';

        /** @var list<array<string, mixed>> $lines */
        $lines = isset($invoice['lines']) && is_array($invoice['lines']) ? $invoice['lines'] : [];

        $text = ($invKind === 'credit_note' ? "Credit note {$num}" : "Invoice {$num}") . "\nFrom: {$organizationName}\n\n";
        $text .= "Bill to: {$clientLine}\n";
        $text .= "Issue date: {$issue}\n";
        if ($due !== '') {
            $text .= "Due date: {$due}\n";
        }
        $text .= "Status: {$status}\n\n";
        foreach ($lines as $ln) {
            $desc = (string) ($ln['description'] ?? '');
            $qty = (float) ($ln['quantity'] ?? 0);
            $unit = (float) ($ln['unit_amount'] ?? 0);
            $lt = (float) ($ln['line_total'] ?? 0);
            $text .= sprintf(
                "- %s | qty %s × %s %s = %s %s\n",
                $desc,
                rtrim(rtrim(sprintf('%.4f', $qty), '0'), '.') ?: '0',
                $currency,
                number_format($unit, 2),
                $currency,
                number_format($lt, 2),
            );
        }
        $text .= sprintf(
            "\nSubtotal: %s %s\nTax: %s %s\nTotal: %s %s\n",
            $currency,
            number_format((float) ($invoice['subtotal'] ?? 0), 2),
            $currency,
            number_format((float) ($invoice['tax_total'] ?? 0), 2),
            $currency,
            number_format((float) ($invoice['total'] ?? 0), 2),
        );
        if (!empty($invoice['notes'])) {
            $text .= "\nNotes:\n" . (string) $invoice['notes'] . "\n";
        }
        if ($pdfBinary !== null && $pdfBinary !== '') {
            $text .= "\n(A PDF copy is attached.)\n";
        }

        $payUrl = '';
        $invStatus = (string) ($invoice['status'] ?? '');
        $invTotal = (float) ($invoice['total'] ?? 0);
        if (
            $invKind === 'invoice'
            && $invStatus === 'sent'
            && $invTotal > 0
            && (new StripeCheckoutService())->isConfigured()
        ) {
            $payUrl = (new PaymentLinkService())->buildUrl((int) ($invoice['id'] ?? 0), (int) ($invoice['organization_id'] ?? 0));
        }
        if ($payUrl !== '') {
            $text .= "\nPay online (card): {$payUrl}\n";
        }

        $text .= "\n—\nSent via {$app}\n";

        $h = static function (string $s): string {
            return htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        };

        $rowsHtml = '';
        foreach ($lines as $ln) {
            $desc = (string) ($ln['description'] ?? '');
            $qty = (float) ($ln['quantity'] ?? 0);
            $unit = (float) ($ln['unit_amount'] ?? 0);
            $tax = (float) ($ln['tax_rate'] ?? 0);
            $lt = (float) ($ln['line_total'] ?? 0);
            $rowsHtml .= '<tr>'
                . '<td style="padding:10px 12px;border-bottom:1px solid #e2e8f0">' . $h($desc) . '</td>'
                . '<td style="padding:10px 12px;border-bottom:1px solid #e2e8f0;text-align:right;font-variant-numeric:tabular-nums">' . $h(rtrim(rtrim(sprintf('%.4f', $qty), '0'), '.') ?: '0') . '</td>'
                . '<td style="padding:10px 12px;border-bottom:1px solid #e2e8f0;text-align:right;font-variant-numeric:tabular-nums">' . $h($currency . ' ' . number_format($unit, 2)) . '</td>'
                . '<td style="padding:10px 12px;border-bottom:1px solid #e2e8f0;text-align:right;font-variant-numeric:tabular-nums">' . $h(number_format($tax, 2)) . '%</td>'
                . '<td style="padding:10px 12px;border-bottom:1px solid #e2e8f0;text-align:right;font-weight:600;font-variant-numeric:tabular-nums">' . $h($currency . ' ' . number_format($lt, 2)) . '</td>'
                . '</tr>';
        }

        $notesHtml = '';
        if (!empty($invoice['notes'])) {
            $notesHtml = '<h3 style="margin:24px 0 8px;font-size:14px;color:#64748b;text-transform:uppercase;letter-spacing:.06em">Notes</h3>'
                . '<p style="margin:0;white-space:pre-wrap;color:#334155">' . $h((string) $invoice['notes']) . '</p>';
        }

        $kindLabel = $invKind === 'credit_note' ? 'Credit note' : 'Invoice';
        $payBlock = '';
        if ($payUrl !== '') {
            $payBlock = '<p style="margin:24px 0 0"><a href="' . $h($payUrl) . '" '
                . 'style="display:inline-block;padding:12px 20px;background:#16a34a;color:#fff;border-radius:999px;text-decoration:none;font-weight:600">'
                . 'Pay invoice online</a></p>'
                . '<p style="margin:12px 0 0;font-size:13px;color:#64748b">Or copy this link:<br>' . $h($payUrl) . '</p>';
        }

        $html = '<div style="font-family:Inter,system-ui,sans-serif;max-width:640px;color:#0f172a;line-height:1.55">'
            . '<p style="margin:0 0 8px;font-size:13px;color:#64748b;text-transform:uppercase;letter-spacing:.06em">' . $h($kindLabel) . '</p>'
            . '<h1 style="margin:0 0 4px;font-size:26px;font-weight:700;letter-spacing:-.02em">' . $h($num) . '</h1>'
            . '<p style="margin:0 0 20px;color:#334155">From <strong>' . $h($organizationName) . '</strong></p>'
            . '<table style="width:100%;border-collapse:collapse;margin-bottom:8px;font-size:15px">'
            . '<tr><td style="padding:4px 0;color:#64748b;width:40%">Bill to</td><td style="padding:4px 0">' . $h($clientLine) . '</td></tr>'
            . '<tr><td style="padding:4px 0;color:#64748b">Issue date</td><td style="padding:4px 0">' . $h($issue) . '</td></tr>'
            . ($due !== '' ? '<tr><td style="padding:4px 0;color:#64748b">Due date</td><td style="padding:4px 0">' . $h($due) . '</td></tr>' : '')
            . '<tr><td style="padding:4px 0;color:#64748b">Status</td><td style="padding:4px 0">' . $h($status) . '</td></tr>'
            . '</table>'
            . '<table style="width:100%;border-collapse:collapse;margin-top:20px;font-size:14px">'
            . '<thead><tr>'
            . '<th style="text-align:left;padding:10px 12px;background:#f1f5f9;border-bottom:2px solid #e2e8f0">Item</th>'
            . '<th style="text-align:right;padding:10px 12px;background:#f1f5f9;border-bottom:2px solid #e2e8f0">Qty</th>'
            . '<th style="text-align:right;padding:10px 12px;background:#f1f5f9;border-bottom:2px solid #e2e8f0">Unit</th>'
            . '<th style="text-align:right;padding:10px 12px;background:#f1f5f9;border-bottom:2px solid #e2e8f0">Tax</th>'
            . '<th style="text-align:right;padding:10px 12px;background:#f1f5f9;border-bottom:2px solid #e2e8f0">Total</th>'
            . '</tr></thead><tbody>' . $rowsHtml . '</tbody></table>'
            . '<table style="width:100%;max-width:280px;margin-left:auto;margin-top:16px;font-size:15px">'
            . '<tr><td style="padding:6px 0;color:#64748b">Subtotal</td><td style="padding:6px 0;text-align:right;font-variant-numeric:tabular-nums">' . $h($currency . ' ' . number_format((float) ($invoice['subtotal'] ?? 0), 2)) . '</td></tr>'
            . '<tr><td style="padding:6px 0;color:#64748b">Tax</td><td style="padding:6px 0;text-align:right;font-variant-numeric:tabular-nums">' . $h($currency . ' ' . number_format((float) ($invoice['tax_total'] ?? 0), 2)) . '</td></tr>'
            . '<tr><td style="padding:10px 0 6px;font-weight:700;font-size:17px">Total</td><td style="padding:10px 0 6px;text-align:right;font-weight:700;font-size:17px;font-variant-numeric:tabular-nums">' . $h($currency . ' ' . number_format((float) ($invoice['total'] ?? 0), 2)) . '</td></tr>'
            . '</table>'
            . $notesHtml
            . ($pdfBinary !== null && $pdfBinary !== ''
                ? '<p style="margin:24px 0 0;font-size:14px;color:#334155">A <strong>PDF copy</strong> is attached.</p>' : '')
            . $payBlock
            . '<p style="margin:28px 0 0;font-size:13px;color:#94a3b8">Sent via ' . $h($app) . '</p>'
            . '</div>';

        $attachments = null;
        if ($pdfBinary !== null && $pdfBinary !== '') {
            $fn = $pdfFilename !== '' ? $pdfFilename : 'invoice.pdf';
            $attachments = [['filename' => $fn, 'content' => $pdfBinary, 'mime' => 'application/pdf']];
        }

        return $this->mail->send($toEmail, $subject, $html, $text, $attachments);
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
