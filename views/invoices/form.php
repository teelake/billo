<?php
declare(strict_types=1);

use App\Core\Csrf;

/** @var array<string, mixed>|null $invoice */
/** @var list<array<string, mixed>> $lines */
/** @var list<array<string, mixed>> $clients */
/** @var bool $is_edit */
/** @var string $user_name */
/** @var string $role */
/** @var bool $show_team_nav */

$error = $error ?? '';
$inv = is_array($invoice) ? $invoice : [];
$is_edit = !empty($is_edit);
$invId = isset($inv['id']) ? (int) $inv['id'] : 0;

$defaultIssue = date('Y-m-d');
$issueVal = $is_edit ? (string) ($inv['issue_date'] ?? $defaultIssue) : $defaultIssue;
$dueVal = $is_edit ? (string) ($inv['due_date'] ?? '') : '';
$currencyVal = $is_edit ? (string) ($inv['currency'] ?? 'NGN') : 'NGN';
$notesVal = $is_edit ? (string) ($inv['notes'] ?? '') : '';
$clientIdVal = $is_edit && !empty($inv['client_id']) ? (string) (int) $inv['client_id'] : '';

$lineList = array_values(array_filter($lines, static fn ($r) => is_array($r)));
if ($lineList === []) {
    $lineList = [['description' => '', 'quantity' => '1', 'unit_amount' => '', 'tax_rate' => '0']];
}
$rowCount = count($lineList);

$title = ($is_edit ? 'Edit invoice' : 'New invoice') . ' — billo';
ob_start();
?>
<section class="app-dashboard">
    <?php
    $active = 'invoices';
    include dirname(__DIR__) . '/partials/app_topbar.php';
    ?>
    <div class="container app-dashboard__body">
        <div class="page-head">
            <div>
                <h1 class="page-head__title"><?= $is_edit ? 'Edit invoice' : 'New invoice' ?></h1>
                <p class="page-head__lead"><?= $is_edit ? 'Update draft line items and details.' : 'Create a draft—you can mark it sent once a client is set.' ?></p>
            </div>
            <a class="btn btn--secondary" href="<?= billo_e(billo_url($is_edit ? '/invoices/show?id=' . $invId : '/invoices')) ?>"><?= $is_edit ? 'Cancel' : 'Back' ?></a>
        </div>

        <?php if ($error !== ''): ?>
            <div class="alert alert--error" role="alert" style="margin-bottom:1rem"><?= billo_e($error) ?></div>
        <?php endif; ?>

        <div class="welcome-card invoice-form-card">
            <form class="form form--spaced" method="post" action="<?= billo_e(billo_url($is_edit ? '/invoices/update' : '/invoices')) ?>">
                <input type="hidden" name="_csrf" value="<?= billo_e(Csrf::token()) ?>">
                <?php if ($is_edit): ?>
                    <input type="hidden" name="id" value="<?= $invId ?>">
                <?php endif; ?>

                <div class="field-grid">
                    <div class="field">
                        <label class="label" for="client_id">Client</label>
                        <select class="input" id="client_id" name="client_id">
                            <option value="">— Optional for drafts —</option>
                            <?php foreach ($clients as $cl): ?>
                                <?php $cid = (int) ($cl['id'] ?? 0); ?>
                                <option value="<?= $cid ?>"<?= (string) $cid === $clientIdVal ? ' selected' : '' ?>><?= billo_e((string) ($cl['name'] ?? '')) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="field">
                        <label class="label" for="issue_date">Issue date <span class="label__req">*</span></label>
                        <input class="input" id="issue_date" name="issue_date" type="date" required value="<?= billo_e($issueVal) ?>">
                    </div>
                    <div class="field">
                        <label class="label" for="due_date">Due date</label>
                        <input class="input" id="due_date" name="due_date" type="date" value="<?= billo_e($dueVal) ?>">
                    </div>
                    <div class="field">
                        <label class="label" for="currency">Currency</label>
                        <select class="input" id="currency" name="currency">
                            <?php
                            $opts = ['NGN', 'USD', 'GBP', 'EUR'];
                            foreach ($opts as $o) {
                                $sel = $currencyVal === $o ? ' selected' : '';
                                echo '<option value="' . billo_e($o) . '"' . $sel . '>' . billo_e($o) . '</option>';
                            }
                            ?>
                        </select>
                    </div>
                </div>

                <div class="field">
                    <label class="label" for="notes">Notes (optional)</label>
                    <textarea class="input" id="notes" name="notes" rows="3" placeholder="Payment terms, bank details, etc."><?= billo_e($notesVal) ?></textarea>
                </div>

                <div class="invoice-lines-block">
                    <div class="invoice-lines-head">
                        <h2 class="invoice-lines-title">Line items</h2>
                        <button type="button" class="btn btn--secondary btn--sm" id="invoice-add-line">Add line</button>
                    </div>
                    <div class="table-wrap invoice-lines-table-wrap">
                        <table class="data-table invoice-lines-table">
                            <thead>
                            <tr>
                                <th>Description</th>
                                <th style="width:5.5rem">Qty</th>
                                <th style="width:6.5rem">Unit</th>
                                <th style="width:5rem">Tax %</th>
                                <th style="width:3rem"></th>
                            </tr>
                            </thead>
                            <tbody data-invoice-lines data-next-index="<?= $rowCount ?>">
                            <?php foreach ($lineList as $i => $row): ?>
                                <?php
                                $d = isset($row['description']) ? (string) $row['description'] : '';
                                $q = $row['quantity'] ?? '1';
                                $u = isset($row['unit_amount']) ? (string) $row['unit_amount'] : '';
                                $t = $row['tax_rate'] ?? '0';
                                if (is_numeric($q)) {
                                    $qf = (float) $q;
                                    $q = abs($qf - round($qf)) < 0.00001
                                        ? (string) (int) round($qf)
                                        : rtrim(rtrim(sprintf('%.4f', $qf), '0'), '.');
                                }
                                if (is_numeric($t)) {
                                    $t = rtrim(rtrim(sprintf('%.3f', (float) $t), '0'), '.');
                                    if ($t === '') {
                                        $t = '0';
                                    }
                                }
                                ?>
                                <tr data-invoice-line>
                                    <td>
                                        <label class="sr-only" for="line-desc-<?= $i ?>">Description</label>
                                        <input class="input input--sm" id="line-desc-<?= $i ?>" data-line-field="description" name="lines[<?= $i ?>][description]" maxlength="500" value="<?= billo_e($d) ?>" placeholder="Description">
                                    </td>
                                    <td>
                                        <label class="sr-only" for="line-qty-<?= $i ?>">Qty</label>
                                        <input class="input input--sm" id="line-qty-<?= $i ?>" data-line-field="quantity" name="lines[<?= $i ?>][quantity]" inputmode="decimal" value="<?= billo_e((string) $q) ?>">
                                    </td>
                                    <td>
                                        <label class="sr-only" for="line-unit-<?= $i ?>">Unit price</label>
                                        <input class="input input--sm" id="line-unit-<?= $i ?>" data-line-field="unit_amount" name="lines[<?= $i ?>][unit_amount]" inputmode="decimal" value="<?= billo_e($u) ?>">
                                    </td>
                                    <td>
                                        <label class="sr-only" for="line-tax-<?= $i ?>">Tax %</label>
                                        <input class="input input--sm" id="line-tax-<?= $i ?>" data-line-field="tax_rate" name="lines[<?= $i ?>][tax_rate]" inputmode="decimal" value="<?= billo_e((string) $t) ?>">
                                    </td>
                                    <td class="invoice-line-actions">
                                        <button type="button" class="btn btn--ghost btn--sm invoice-line-remove" title="Remove row" aria-label="Remove row">×</button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <div class="form-actions">
                    <button type="submit" class="btn btn--primary"><?= $is_edit ? 'Save changes' : 'Create draft' ?></button>
                </div>
            </form>
        </div>
    </div>
</section>

<template id="invoice-line-empty-row">
    <tr data-invoice-line>
        <td>
            <label class="sr-only">Description</label>
            <input class="input input--sm" data-line-field="description" maxlength="500" value="" placeholder="Description">
        </td>
        <td>
            <label class="sr-only">Qty</label>
            <input class="input input--sm" data-line-field="quantity" inputmode="decimal" value="1">
        </td>
        <td>
            <label class="sr-only">Unit price</label>
            <input class="input input--sm" data-line-field="unit_amount" inputmode="decimal" value="">
        </td>
        <td>
            <label class="sr-only">Tax %</label>
            <input class="input input--sm" data-line-field="tax_rate" inputmode="decimal" value="0">
        </td>
        <td class="invoice-line-actions">
            <button type="button" class="btn btn--ghost btn--sm invoice-line-remove" title="Remove row" aria-label="Remove row">×</button>
        </td>
    </tr>
</template>
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
