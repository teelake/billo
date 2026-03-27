<?php
declare(strict_types=1);

use App\Core\Csrf;

/** @var array<string, mixed>|null $invoice */
/** @var list<array<string, mixed>> $lines */
/** @var list<array<string, mixed>> $clients */
/** @var bool $is_edit */
/** @var bool $is_credit_note */
/** @var array<string, mixed> $document_tax */
/** @var array<string, mixed> $org_tax_row */
/** @var string $user_name */
/** @var string $role */
/** @var bool $show_team_nav */

$error = $error ?? '';
$inv = is_array($invoice) ? $invoice : [];
$is_edit = !empty($is_edit);
$is_credit_note = !empty($is_credit_note);
$invId = isset($inv['id']) ? (int) $inv['id'] : 0;
$creditedRef = $is_credit_note ? trim((string) ($inv['credited_invoice_number'] ?? '')) : '';

$defaultIssue = date('Y-m-d');
$issueVal = $is_edit ? (string) ($inv['issue_date'] ?? $defaultIssue) : $defaultIssue;
$dueVal = $is_edit ? (string) ($inv['due_date'] ?? '') : '';
$currencyVal = $is_edit ? (string) ($inv['currency'] ?? 'NGN') : 'NGN';
$notesVal = $is_edit ? (string) ($inv['notes'] ?? '') : '';
$clientIdVal = $is_edit && !empty($inv['client_id']) ? (string) (int) $inv['client_id'] : '';
$selectedClientLabel = '';
$clientsForCombo = [];
foreach ($clients as $cl) {
    if (!is_array($cl)) {
        continue;
    }
    $cid = (int) ($cl['id'] ?? 0);
    if ($cid <= 0) {
        continue;
    }
    $nm = (string) ($cl['name'] ?? '');
    $clientsForCombo[] = [
        'id' => $cid,
        'name' => $nm,
        'email' => isset($cl['email']) ? (string) $cl['email'] : '',
        'company' => isset($cl['company_name']) ? (string) $cl['company_name'] : '',
    ];
    if ($clientIdVal !== '' && (string) $cid === $clientIdVal) {
        $selectedClientLabel = $nm;
    }
}
$clientsJson = json_encode($clientsForCombo, JSON_HEX_TAG | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE);
if ($clientsJson === false) {
    $clientsJson = '[]';
}

$lineList = array_values(array_filter($lines, static fn ($r) => is_array($r)));
if ($lineList === []) {
    $lineList = [['description' => '', 'quantity' => '1', 'unit_amount' => '', 'tax_rate' => '0']];
}
$rowCount = count($lineList);
$dt = isset($document_tax) && is_array($document_tax) ? $document_tax : ['supported' => false, 'wht_types' => [], 'platform_vat_rate' => 7.5];
$orgTax = isset($org_tax_row) && is_array($org_tax_row) ? $org_tax_row : [];
$invRow = is_array($invoice) ? $invoice : [];
$docTaxSupported = !empty($dt['supported']) && !$is_credit_note;
$platformVat = (float) ($dt['platform_vat_rate'] ?? 7.5);
$whtTypes = isset($dt['wht_types']) && is_array($dt['wht_types']) ? $dt['wht_types'] : [];
$whtRateById = [];
foreach ($whtTypes as $w) {
    if (!is_array($w)) {
        continue;
    }
    $wid = (int) ($w['id'] ?? 0);
    if ($wid <= 0) {
        continue;
    }
    $whtRateById[$wid] = (float) ($w['rate'] ?? 0);
}
$whtRatesJson = json_encode($whtRateById, JSON_HEX_TAG | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE);
if ($whtRatesJson === false) {
    $whtRatesJson = '{}';
}

$applyVatChecked = $is_edit
    ? (!empty($invRow['apply_vat']))
    : (!empty($orgTax['enable_vat']));
$applyWhtChecked = $is_edit
    ? (!empty($invRow['apply_wht']))
    : (!empty($orgTax['enable_wht']));
$vatRateForPreview = $platformVat;
$whtSelected = $is_edit ? (int) ($invRow['wht_id'] ?? 0) : (int) ($orgTax['default_wht_id'] ?? 0);

$title = ($is_credit_note ? 'Edit credit note' : ($is_edit ? 'Edit invoice' : 'New invoice')) . ' — billo';
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
                <h1 class="page-head__title"><?= $is_credit_note ? 'Edit credit note' : ($is_edit ? 'Edit invoice' : 'New invoice') ?></h1>
                <p class="page-head__lead"><?php
                    if ($is_credit_note) {
                        echo 'Credit notes use negative unit amounts for each line.';
                        if ($creditedRef !== '') {
                            echo ' Applies to invoice ' . billo_e($creditedRef) . '.';
                        }
                    } elseif ($is_edit) {
                        echo 'Update draft line items and details.';
                    } else {
                        echo 'Create a draft—you can mark it sent once a client is set.';
                    }
                ?></p>
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
                    <div class="field billo-combobox" data-billo-client-combobox>
                        <label class="label" for="invoice_client_search">Client</label>
                        <input type="hidden" name="client_id" id="client_id" value="<?= billo_e($clientIdVal) ?>">
                        <input class="input billo-combobox__search" type="search" id="invoice_client_search" autocomplete="off" placeholder="Search by name or email…" value="<?= billo_e($selectedClientLabel) ?>">
                        <ul class="billo-combobox__list" id="invoice-client-suggestions" role="listbox" hidden></ul>
                        <p class="hint">Optional for drafts. Type to filter; click a row to select. Clear the box to remove the client.</p>
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

                <?php if ($docTaxSupported): ?>
                    <div class="welcome-card invoice-tax-inline" style="padding:1rem 1.25rem;margin:0 0 1.25rem">
                        <h2 class="invoice-lines-title" style="margin-top:0">VAT &amp; withholding</h2>
                        <p class="hint" style="margin:0 0 1rem">Applied to the line subtotal below. VAT rate is the <strong>platform</strong> percentage from <strong>System → Tax templates</strong>; toggles default from <a href="<?= billo_e(billo_url('/organization#invoicing')) ?>">business settings</a>.</p>
                        <div class="field-toggle" style="margin-bottom:0.75rem">
                            <div class="field-toggle__text">
                                <strong class="field-toggle__label">Apply VAT</strong>
                            </div>
                            <label class="field-toggle__control">
                                <input type="checkbox" name="apply_vat" value="1" class="field-toggle__input" id="inv-apply-vat"<?= $applyVatChecked ? ' checked' : '' ?>>
                                <span class="field-toggle__track" aria-hidden="true"><span class="field-toggle__thumb"></span></span>
                            </label>
                        </div>
                        <div class="field" id="inv-vat-rate-wrap">
                            <p class="label" style="margin:0 0 0.35rem">VAT rate</p>
                            <p class="hint" style="margin:0"><strong><?= billo_e(rtrim(rtrim(sprintf('%.4f', $vatRateForPreview), '0'), '.') ?: '0') ?>%</strong> — set by platform operator (additive tax template).</p>
                            <input type="hidden" id="inv-vat-rate" value="<?= billo_e(rtrim(rtrim(sprintf('%.6f', $vatRateForPreview), '0'), '.') ?: '0') ?>" autocomplete="off" aria-hidden="true">
                        </div>
                        <div class="field-toggle" style="margin:1rem 0 0.75rem">
                            <div class="field-toggle__text">
                                <strong class="field-toggle__label">Apply WHT</strong>
                            </div>
                            <label class="field-toggle__control">
                                <input type="checkbox" name="apply_wht" value="1" class="field-toggle__input" id="inv-apply-wht"<?= $applyWhtChecked ? ' checked' : '' ?>>
                                <span class="field-toggle__track" aria-hidden="true"><span class="field-toggle__thumb"></span></span>
                            </label>
                        </div>
                        <div class="field" id="inv-wht-type-wrap">
                            <label class="label" for="inv-wht-id">WHT type</label>
                            <select class="input" name="wht_id" id="inv-wht-id" style="max-width:24rem">
                                <option value="">— Select —</option>
                                <?php foreach ($whtTypes as $w): ?>
                                    <?php
                                    if (!is_array($w)) {
                                        continue;
                                    }
                                    $wid = (int) ($w['id'] ?? 0);
                                    if ($wid <= 0) {
                                        continue;
                                    }
                                    $sel = $wid === $whtSelected ? ' selected' : '';
                                    $lab = (string) ($w['name'] ?? 'WHT') . ' (' . rtrim(rtrim(sprintf('%.2f', (float) ($w['rate'] ?? 0)), '0'), '.') . '%)';
                                    ?>
                                    <option value="<?= $wid ?>"<?= $sel ?>><?= billo_e($lab) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <script type="application/json" id="billo-invoice-wht-rates"><?= $whtRatesJson ?></script>
                    <script>
                    (function () {
                        var vat = document.getElementById('inv-apply-vat');
                        var vatWrap = document.getElementById('inv-vat-rate-wrap');
                        var wht = document.getElementById('inv-apply-wht');
                        var whtWrap = document.getElementById('inv-wht-type-wrap');
                        function sync() {
                            if (vatWrap) vatWrap.hidden = !vat || !vat.checked;
                            if (whtWrap) whtWrap.hidden = !wht || !wht.checked;
                        }
                        if (vat) vat.addEventListener('change', sync);
                        if (wht) wht.addEventListener('change', sync);
                        sync();
                    })();
                    </script>
                <?php endif; ?>

                <div class="invoice-lines-block">
                    <div class="invoice-lines-head">
                        <h2 class="invoice-lines-title">Line items</h2>
                        <button type="button" class="btn btn--secondary btn--sm" id="invoice-add-line">Add line</button>
                    </div>
                    <div class="table-wrap invoice-lines-table-wrap">
                        <table class="data-table invoice-lines-table">
                            <thead>
                            <tr>
                                <th class="invoice-lines-table__col invoice-lines-table__col--desc">Description</th>
                                <th class="invoice-lines-table__col invoice-lines-table__col--qty">Qty</th>
                                <th class="invoice-lines-table__col invoice-lines-table__col--unit"><?= $is_credit_note ? 'Unit (negative)' : 'Unit price' ?></th>
                                <th class="invoice-lines-table__col invoice-lines-table__col--actions"></th>
                            </tr>
                            </thead>
                            <tbody data-invoice-lines data-tax-enabled="0" data-next-index="<?= $rowCount ?>">
                            <?php foreach ($lineList as $i => $row): ?>
                                <?php
                                $d = isset($row['description']) ? (string) $row['description'] : '';
                                $q = $row['quantity'] ?? '1';
                                $u = isset($row['unit_amount']) ? (string) $row['unit_amount'] : '';
                                if (is_numeric($q)) {
                                    $qf = (float) $q;
                                    $q = abs($qf - round($qf)) < 0.00001
                                        ? (string) (int) round($qf)
                                        : rtrim(rtrim(sprintf('%.4f', $qf), '0'), '.');
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
                                    <td class="invoice-line-actions">
                                        <input type="hidden" data-line-field="tax_rate" name="lines[<?= $i ?>][tax_rate]" value="0">
                                        <button type="button" class="btn btn--ghost btn--sm invoice-line-remove" title="Remove row" aria-label="Remove row">×</button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <div class="invoice-form-totals welcome-card" data-invoice-form-totals data-doc-tax="<?= $docTaxSupported ? '1' : '0' ?>" style="padding:1rem 1.25rem;margin:1rem 0 0;background:rgba(15,23,42,0.03)">
                    <h2 class="invoice-lines-title" style="margin-top:0">Totals</h2>
                    <p class="hint" style="margin:0 0 0.75rem">Updates as you edit lines<?= $docTaxSupported ? ' and tax options' : '' ?>. The saved invoice is calculated on the server the same way.</p>
                    <dl class="invoice-form-totals__dl">
                        <div class="invoice-form-totals__row">
                            <dt>Subtotal</dt>
                            <dd data-total-sub><?= billo_e($currencyVal) ?> 0.00</dd>
                        </div>
                        <div class="invoice-form-totals__row">
                            <dt>Tax</dt>
                            <dd data-total-tax><?= billo_e($currencyVal) ?> 0.00</dd>
                        </div>
                        <div class="invoice-form-totals__row invoice-form-totals__row--grand">
                            <dt><strong>Grand total</strong></dt>
                            <dd data-total-grand><strong><?= billo_e($currencyVal) ?> 0.00</strong></dd>
                        </div>
                    </dl>
                </div>

                <div class="form-actions">
                    <button type="submit" class="btn btn--primary"><?= $is_edit ? 'Save changes' : 'Create draft' ?></button>
                </div>
            </form>
        </div>
    </div>
    <script type="application/json" id="billo-invoice-clients-data"><?= $clientsJson ?></script>
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
        <td class="invoice-line-actions">
            <input type="hidden" data-line-field="tax_rate" value="0">
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
