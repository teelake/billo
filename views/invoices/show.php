<?php
declare(strict_types=1);

use App\Core\Csrf;

/** @var array<string, mixed> $invoice */
/** @var bool $can_manage */
/** @var string $user_name */
/** @var string $role */
/** @var bool $show_team_nav */

$error = $error ?? '';
$success = $success ?? '';
$status = (string) ($invoice['status'] ?? '');
$invId = (int) ($invoice['id'] ?? 0);
$currency = (string) ($invoice['currency'] ?? 'NGN');
/** @var list<array<string, mixed>> $lines */
$lines = isset($invoice['lines']) && is_array($invoice['lines']) ? $invoice['lines'] : [];
$title = ($invoice['invoice_number'] ?? 'Invoice') . ' — billo';
ob_start();
?>
<section class="app-dashboard">
    <?php
    $active = 'invoices';
    include dirname(__DIR__) . '/partials/app_topbar.php';
    ?>
    <div class="container app-dashboard__body">
        <?php if ($error !== ''): ?>
            <div class="alert alert--error" role="alert" style="margin-bottom:1rem"><?= billo_e($error) ?></div>
        <?php endif; ?>
        <?php if ($success !== ''): ?>
            <div class="alert alert--success" role="alert" style="margin-bottom:1rem"><?= billo_e($success) ?></div>
        <?php endif; ?>

        <div class="page-head">
            <div>
                <p class="eyebrow eyebrow--dark"><span class="status-pill status-pill--<?= billo_e($status) ?>"><?= billo_e($status) ?></span></p>
                <h1 class="page-head__title"><?= billo_e((string) ($invoice['invoice_number'] ?? 'Invoice')) ?></h1>
                <p class="page-head__lead">
                    Issued <?= billo_e((string) ($invoice['issue_date'] ?? '')) ?>
                    <?php if (!empty($invoice['due_date'])): ?>
                        · Due <?= billo_e((string) $invoice['due_date']) ?>
                    <?php endif; ?>
                </p>
            </div>
            <div class="page-head__actions">
                <a class="btn btn--secondary" href="<?= billo_e(billo_url('/invoices')) ?>">All invoices</a>
                <?php if ($can_manage && $status === 'draft'): ?>
                    <a class="btn btn--primary" href="<?= billo_e(billo_url('/invoices/edit?id=' . $invId)) ?>">Edit draft</a>
                <?php endif; ?>
            </div>
        </div>

        <div class="invoice-detail-grid">
            <div class="welcome-card invoice-detail-card">
                <h2 class="invoice-detail-card__h">Bill to</h2>
                <?php if (empty($invoice['client_id'])): ?>
                    <p class="invoice-detail-card__muted">No client linked yet. Edit the draft to choose one before marking as sent.</p>
                <?php else: ?>
                    <p><strong><?= billo_e((string) ($invoice['client_name'] ?? '')) ?></strong>
                        <?php if (!empty($invoice['client_company'])): ?>
                            <br><?= billo_e((string) $invoice['client_company']) ?>
                        <?php endif; ?>
                    </p>
                    <?php if (!empty($invoice['client_email'])): ?>
                        <p class="invoice-detail-card__muted"><?= billo_e((string) $invoice['client_email']) ?></p>
                    <?php endif; ?>
                <?php endif; ?>

                <?php if (!empty($invoice['notes'])): ?>
                    <h3 class="invoice-detail-card__h2">Notes</h3>
                    <p class="invoice-detail-card__notes"><?= nl2br(billo_e((string) $invoice['notes'])) ?></p>
                <?php endif; ?>
            </div>

            <div class="welcome-card invoice-detail-card" style="padding:0;overflow:hidden">
                <table class="data-table data-table--comfortable">
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
                            <td class="num"><?= billo_e($currency) ?>&nbsp;<?= billo_e(number_format((float) ($ln['unit_amount'] ?? 0), 2)) ?></td>
                            <td class="num"><?= billo_e(number_format((float) ($ln['tax_rate'] ?? 0), 2)) ?></td>
                            <td class="num"><strong><?= billo_e($currency) ?>&nbsp;<?= billo_e(number_format((float) ($ln['line_total'] ?? 0), 2)) ?></strong></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
                <div class="invoice-totals">
                    <div class="invoice-totals__row"><span>Subtotal</span><span><?= billo_e($currency) ?> <?= billo_e(number_format((float) ($invoice['subtotal'] ?? 0), 2)) ?></span></div>
                    <div class="invoice-totals__row"><span>Tax</span><span><?= billo_e($currency) ?> <?= billo_e(number_format((float) ($invoice['tax_total'] ?? 0), 2)) ?></span></div>
                    <div class="invoice-totals__row invoice-totals__row--total"><span>Total</span><span><?= billo_e($currency) ?> <?= billo_e(number_format((float) ($invoice['total'] ?? 0), 2)) ?></span></div>
                </div>
            </div>
        </div>

        <?php if ($can_manage): ?>
            <div class="invoice-actions welcome-card" style="margin-top:1.25rem">
                <?php if ($status === 'draft'): ?>
                    <form method="post" action="<?= billo_e(billo_url('/invoices/send')) ?>" class="inline-form">
                        <input type="hidden" name="_csrf" value="<?= billo_e(Csrf::token()) ?>">
                        <input type="hidden" name="id" value="<?= $invId ?>">
                        <button type="submit" class="btn btn--primary"<?= empty($invoice['client_id']) ? ' disabled title="Add a client on the edit screen first"' : '' ?>>Mark as sent</button>
                    </form>
                    <form method="post" action="<?= billo_e(billo_url('/invoices/void')) ?>" class="inline-form" onsubmit="return confirm('Void this draft?');">
                        <input type="hidden" name="_csrf" value="<?= billo_e(Csrf::token()) ?>">
                        <input type="hidden" name="id" value="<?= $invId ?>">
                        <button type="submit" class="btn btn--secondary">Void</button>
                    </form>
                    <form method="post" action="<?= billo_e(billo_url('/invoices/delete')) ?>" class="inline-form" onsubmit="return confirm('Delete this draft permanently?');">
                        <input type="hidden" name="_csrf" value="<?= billo_e(Csrf::token()) ?>">
                        <input type="hidden" name="id" value="<?= $invId ?>">
                        <button type="submit" class="btn btn--ghost" style="color:var(--color-danger)">Delete draft</button>
                    </form>
                <?php elseif ($status === 'sent'): ?>
                    <form method="post" action="<?= billo_e(billo_url('/invoices/mark-paid')) ?>" class="inline-form">
                        <input type="hidden" name="_csrf" value="<?= billo_e(Csrf::token()) ?>">
                        <input type="hidden" name="id" value="<?= $invId ?>">
                        <button type="submit" class="btn btn--primary">Mark as paid</button>
                    </form>
                    <form method="post" action="<?= billo_e(billo_url('/invoices/void')) ?>" class="inline-form" onsubmit="return confirm('Void this invoice?');">
                        <input type="hidden" name="_csrf" value="<?= billo_e(Csrf::token()) ?>">
                        <input type="hidden" name="id" value="<?= $invId ?>">
                        <button type="submit" class="btn btn--secondary">Void</button>
                    </form>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>
</section>
<?php
$content = ob_get_clean();
$bodyClass = 'app-body';
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
    <link rel="stylesheet" href="<?= billo_e(billo_asset('css/app.css')) ?>">
</head>
<body class="<?= billo_e($bodyClass) ?>">
<?= $content ?>
<script src="<?= billo_e(billo_asset('js/app.js')) ?>" defer></script>
</body>
</html>
