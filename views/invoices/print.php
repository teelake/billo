<?php
declare(strict_types=1);

/** @var array<string, mixed> $invoice */
/** @var array<string, mixed> $organization */

$org = isset($organization) && is_array($organization) ? $organization : [];
$orgName = trim((string) ($org['legal_name'] ?? ''));
if ($orgName === '') {
    $orgName = (string) ($org['name'] ?? billo_brand_name());
}
$currency = (string) ($invoice['currency'] ?? 'NGN');
$num = (string) ($invoice['invoice_number'] ?? 'Invoice');
/** @var list<array<string, mixed>> $lines */
$lines = isset($invoice['lines']) && is_array($invoice['lines']) ? $invoice['lines'] : [];
$title = $num . ' — ' . $orgName;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= billo_e($title) ?></title>
    <meta name="robots" content="noindex, nofollow">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Plus+Jakarta+Sans:wght@600;700&display=swap" rel="stylesheet">
    <style>
        :root { --ink: #0f172a; --muted: #64748b; --border: #e2e8f0; --bg: #f8fafc; }
        * { box-sizing: border-box; }
        body { font-family: Inter, system-ui, sans-serif; margin: 0; color: var(--ink); background: #fff; line-height: 1.5; }
        .toolbar { display: flex; gap: 0.75rem; align-items: center; flex-wrap: wrap; padding: 1rem 1.25rem; background: var(--bg); border-bottom: 1px solid var(--border); }
        .toolbar a { color: #1e3a8a; font-weight: 600; text-decoration: none; font-size: 0.9rem; }
        .toolbar a:hover { color: #16a34a; }
        .toolbar-save { border: 1px solid #e2e8f0; padding: 0.45rem 1rem; border-radius: 999px; background: #fff; }
        .toolbar button { font-family: inherit; font-size: 0.9rem; font-weight: 600; cursor: pointer; padding: 0.5rem 1rem; border-radius: 999px; border: none; background: #16a34a; color: #fff; }
        .toolbar button:hover { background: #15803d; }
        .sheet { max-width: 48rem; margin: 0 auto; padding: 2rem 1.5rem 3rem; }
        .org { font-family: "Plus Jakarta Sans", system-ui, sans-serif; font-weight: 700; font-size: 1.35rem; letter-spacing: -0.03em; margin: 0 0 0.25rem; }
        .doc-label { font-size: 0.72rem; text-transform: uppercase; letter-spacing: 0.1em; color: var(--muted); margin: 0 0 0.35rem; }
        h1 { font-family: "Plus Jakarta Sans", system-ui, sans-serif; font-size: 1.75rem; margin: 0 0 1rem; letter-spacing: -0.02em; }
        .meta { width: 100%; border-collapse: collapse; font-size: 0.95rem; margin-bottom: 1.5rem; }
        .meta td { padding: 0.25rem 0; vertical-align: top; }
        .meta td:first-child { color: var(--muted); width: 8rem; }
        .lines { width: 100%; border-collapse: collapse; font-size: 0.9rem; }
        .lines th { text-align: left; font-size: 0.7rem; text-transform: uppercase; letter-spacing: 0.06em; color: var(--muted); padding: 0.65rem 0.5rem; border-bottom: 2px solid var(--border); background: #f1f5f9; }
        .lines th.num { text-align: right; }
        .lines td { padding: 0.65rem 0.5rem; border-bottom: 1px solid var(--border); vertical-align: top; }
        .lines td.num { text-align: right; font-variant-numeric: tabular-nums; }
        .totals { margin-left: auto; margin-top: 1rem; max-width: 16rem; font-size: 0.95rem; }
        .totals-row { display: flex; justify-content: space-between; gap: 2rem; padding: 0.2rem 0; color: var(--muted); }
        .totals-row.total { margin-top: 0.35rem; padding-top: 0.5rem; border-top: 1px solid var(--border); font-weight: 700; font-size: 1.05rem; color: var(--ink); }
        .notes { margin-top: 1.75rem; font-size: 0.9rem; }
        .notes h2 { font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.06em; color: var(--muted); margin: 0 0 0.35rem; }
        .notes p { margin: 0; white-space: pre-wrap; color: #334155; }
        .letterhead img { max-height: 56px; margin-bottom: 0.5rem; }
        .letterhead-addr { font-size: 0.82rem; color: #475569; line-height: 1.45; margin: 0.35rem 0 0; }
        .letterhead-tax { font-size: 0.8rem; color: #64748b; margin: 0.5rem 0 0; }
        .inv-footer { margin-top: 1.75rem; padding-top: 1rem; border-top: 1px solid var(--border); font-size: 0.82rem; color: #64748b; white-space: pre-wrap; }
        @media print {
            .no-print { display: none !important; }
            body { background: #fff; }
            .sheet { padding-top: 0.5rem; }
        }
    </style>
</head>
<body>
<div class="toolbar no-print">
    <a href="<?= billo_e(billo_url('/invoices/show?id=' . (int) ($invoice['id'] ?? 0))) ?>">← Back to invoice</a>
    <?php if (class_exists(\Dompdf\Dompdf::class)): ?>
        <a class="toolbar-save" href="<?= billo_e(billo_url('/invoices/pdf?id=' . (int) ($invoice['id'] ?? 0))) ?>">Download PDF</a>
    <?php endif; ?>
    <button type="button" onclick="window.print()">Print / Save as PDF</button>
</div>
<div class="sheet">
    <?php
    $logoRef = isset($org['invoice_logo_url']) ? trim((string) $org['invoice_logo_url']) : '';
    if ($logoRef !== '') {
        if (str_starts_with($logoRef, 'https://') || str_starts_with($logoRef, 'http://')) {
            echo '<div class="letterhead"><img src="' . billo_e($logoRef) . '" alt=""></div>';
        } else {
            $root = realpath(BILLO_ROOT);
            $path = $root ? realpath(BILLO_ROOT . DIRECTORY_SEPARATOR . str_replace(['/', '\\'], DIRECTORY_SEPARATOR, ltrim($logoRef, '/\\'))) : false;
            if ($path !== false && is_file($path) && str_starts_with($path, $root)) {
                $mime = @mime_content_type($path);
                if (is_string($mime) && str_starts_with($mime, 'image/')) {
                    $raw = @file_get_contents($path);
                    if ($raw !== false && $raw !== '') {
                        echo '<div class="letterhead"><img src="data:' . billo_e($mime) . ';base64,' . base64_encode($raw) . '" alt=""></div>';
                    }
                }
            }
        }
    }
    ?>
    <p class="doc-label">Invoice from</p>
    <p class="org"><?= billo_e($orgName) ?></p>
    <?php
    $addrParts = array_filter([
        $org['billing_address_line1'] ?? '',
        $org['billing_address_line2'] ?? '',
        trim(implode(', ', array_filter([
            $org['billing_city'] ?? '',
            $org['billing_state'] ?? '',
            $org['billing_country'] ?? '',
        ], static fn ($v) => is_string($v) && $v !== ''))),
    ], static fn ($v) => is_string($v) && trim($v) !== '');
    if ($addrParts !== []) {
        echo '<p class="letterhead-addr">' . nl2br(billo_e(implode("\n", $addrParts))) . '</p>';
    }
    if (!empty($org['tax_id'])) {
        echo '<p class="letterhead-tax">Tax ID: ' . billo_e((string) $org['tax_id']) . '</p>';
    }
    if (!empty($org['company_registration_number'])) {
        echo '<p class="letterhead-tax">CAC / Reg.: ' . billo_e((string) $org['company_registration_number']) . '</p>';
    }
    if (!empty($org['company_website'])) {
        echo '<p class="letterhead-tax">Website: ' . billo_e((string) $org['company_website']) . '</p>';
    }
    ?>
    <h1><?= billo_e($num) ?></h1>

    <table class="meta">
        <?php if (!empty($invoice['client_id'])): ?>
            <tr>
                <td>Bill to</td>
                <td>
                    <strong><?= billo_e((string) ($invoice['client_name'] ?? '')) ?></strong>
                    <?php if (!empty($invoice['client_company'])): ?>
                        <br><?= billo_e((string) $invoice['client_company']) ?>
                    <?php endif; ?>
                </td>
            </tr>
        <?php endif; ?>
        <tr><td>Issue date</td><td><?= billo_e((string) ($invoice['issue_date'] ?? '')) ?></td></tr>
        <?php if (!empty($invoice['due_date'])): ?>
            <tr><td>Due date</td><td><?= billo_e((string) $invoice['due_date']) ?></td></tr>
        <?php endif; ?>
        <tr><td>Status</td><td><?= billo_e((string) ($invoice['status'] ?? '')) ?></td></tr>
    </table>

    <table class="lines">
        <thead>
        <tr>
            <th>Description</th>
            <th class="num">Qty</th>
            <th class="num">Unit</th>
            <th class="num">Tax %</th>
            <th class="num">Line total</th>
        </tr>
        </thead>
        <tbody>
        <?php foreach ($lines as $ln): ?>
            <tr>
                <td><?= billo_e((string) ($ln['description'] ?? '')) ?></td>
                <td class="num"><?= billo_e(rtrim(rtrim(sprintf('%.4f', (float) ($ln['quantity'] ?? 0)), '0'), '.') ?: '0') ?></td>
                <td class="num"><?= billo_e($currency) ?> <?= billo_e(number_format((float) ($ln['unit_amount'] ?? 0), 2)) ?></td>
                <td class="num"><?= billo_e(number_format((float) ($ln['tax_rate'] ?? 0), 2)) ?></td>
                <td class="num"><strong><?= billo_e($currency) ?> <?= billo_e(number_format((float) ($ln['line_total'] ?? 0), 2)) ?></strong></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>

    <div class="totals">
        <div class="totals-row"><span>Subtotal</span><span><?= billo_e($currency) ?> <?= billo_e(number_format((float) ($invoice['subtotal'] ?? 0), 2)) ?></span></div>
        <div class="totals-row"><span>Tax</span><span><?= billo_e($currency) ?> <?= billo_e(number_format((float) ($invoice['tax_total'] ?? 0), 2)) ?></span></div>
        <div class="totals-row total"><span>Total</span><span><?= billo_e($currency) ?> <?= billo_e(number_format((float) ($invoice['total'] ?? 0), 2)) ?></span></div>
    </div>

    <?php if (!empty($invoice['notes'])): ?>
        <div class="notes">
            <h2>Notes</h2>
            <p><?= nl2br(billo_e((string) $invoice['notes'])) ?></p>
        </div>
    <?php endif; ?>
    <?php if (!empty($org['invoice_footer'])): ?>
        <div class="inv-footer"><?= nl2br(billo_e((string) $org['invoice_footer'])) ?></div>
    <?php endif; ?>
</div>
</body>
</html>
