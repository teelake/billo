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
$invoiceTaxEnabled = (bool) ($invoice_tax_enabled ?? true);
$dt = isset($document_tax) && is_array($document_tax) ? $document_tax : ['supported' => false, 'wht_types' => [], 'platform_vat_rate' => 7.5];
$orgTax = isset($org_tax_row) && is_array($org_tax_row) ? $org_tax_row : [];
$invRow = is_array($invoice) ? $invoice : [];
if ($is_credit_note) {
    $invoiceLineMode = 'line';
} elseif (empty($dt['supported'])) {
    $invoiceLineMode = 'line';
} elseif ($is_edit && (($invRow['tax_computation'] ?? 'line') === 'line')) {
    $invoiceLineMode = 'line';
} else {
    $invoiceLineMode = 'document';
}
$showLineTaxCol = $invoiceTaxEnabled && $invoiceLineMode === 'line';
$docTaxSupported = !empty($dt['supported']) && !$is_credit_note;
$platformVat = (float) ($dt['platform_vat_rate'] ?? 7.5);
$whtTypes = isset($dt['wht_types']) && is_array($dt['wht_types']) ? $dt['wht_types'] : [];
$orgVatStored = (float) ($orgTax['vat_rate'] ?? 0);
$defaultVatDisplay = $orgVatStored > 0.00001 ? $orgVatStored : $platformVat;
$applyVatChecked = $is_edit
    ? (!empty($invRow['apply_vat']))
    : (!empty($orgTax['enable_vat']));
$applyWhtChecked = $is_edit
    ? (!empty($invRow['apply_wht']))
    : (!empty($orgTax['enable_wht']));
$vatRateField = $is_edit
    ? (string) (float) ($invRow['vat_rate'] ?? $defaultVatDisplay)
    : (string) $defaultVatDisplay;
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

                <div class="org-settings-tabs invoice-form-tabs" data-billo-form-tabs data-billo-default-tab="details">
                    <div class="org-settings-tablist" role="tablist" aria-label="Invoice sections">
                        <button type="button" class="org-settings-tab" role="tab" id="inv-tab-details" aria-selected="true" aria-controls="inv-panel-details" data-billo-tab="details" tabindex="0">Details</button>
                        <?php if ($docTaxSupported): ?>
                            <button type="button" class="org-settings-tab" role="tab" id="inv-tab-tax" aria-selected="false" aria-controls="inv-panel-tax" data-billo-tab="tax" tabindex="-1">VAT &amp; WHT</button>
                        <?php endif; ?>
                        <button type="button" class="org-settings-tab" role="tab" id="inv-tab-lines" aria-selected="false" aria-controls="inv-panel-lines" data-billo-tab="lines" tabindex="-1">Line items</button>
                    </div>
                    <div class="org-settings-panels">
                        <div class="org-settings-panel" role="tabpanel" id="inv-panel-details" aria-labelledby="inv-tab-details" data-billo-panel="details">
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
                        </div>

                        <?php if ($docTaxSupported): ?>
                            <div class="org-settings-panel" role="tabpanel" id="inv-panel-tax" aria-labelledby="inv-tab-tax" data-billo-panel="tax" hidden>
                                <p class="org-settings-panel__lead">Document-level tax for this invoice. Line items do not carry separate tax rates in this mode.</p>
                                <div class="welcome-card" style="padding:1rem 1.25rem;margin:0">
                                    <h2 class="invoice-lines-title" style="margin-top:0">VAT &amp; withholding (this invoice)</h2>
                                    <p class="hint" style="margin:0 0 1rem">VAT is calculated on the subtotal of line items. WHT is calculated on the same subtotal (not on the VAT-inclusive amount). Defaults come from <a href="<?= billo_e(billo_url('/organization#invoicing')) ?>">business tax settings</a>.</p>
                                    <div class="field-toggle" style="margin-bottom:0.75rem">
                                        <div class="field-toggle__text">
                                            <strong class="field-toggle__label">Apply VAT</strong>
                                            <p class="hint" style="margin:0.35rem 0 0">Charge VAT on this invoice’s subtotal.</p>
                                        </div>
                                        <label class="field-toggle__control">
                                            <input type="checkbox" name="apply_vat" value="1" class="field-toggle__input" id="inv-apply-vat"<?= $applyVatChecked ? ' checked' : '' ?>>
                                            <span class="field-toggle__track" aria-hidden="true"><span class="field-toggle__thumb"></span></span>
                                        </label>
                                    </div>
                                    <div class="field" id="inv-vat-rate-wrap">
                                        <label class="label" for="inv-vat-rate">VAT rate (%)</label>
                                        <input class="input" type="text" inputmode="decimal" name="vat_rate" id="inv-vat-rate" value="<?= billo_e(rtrim(rtrim(sprintf('%.4f', (float) $vatRateField), '0'), '.') ?: '0') ?>" style="max-width:8rem">
                                    </div>
                                    <div class="field-toggle" style="margin:1rem 0 0.75rem">
                                        <div class="field-toggle__text">
                                            <strong class="field-toggle__label">Apply WHT</strong>
                                            <p class="hint" style="margin:0.35rem 0 0">Withholding tax reduces the amount payable by the client.</p>
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
                            </div>
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

                        <div class="org-settings-panel" role="tabpanel" id="inv-panel-lines" aria-labelledby="inv-tab-lines" data-billo-panel="lines" hidden>
                            <div class="invoice-lines-block" style="margin-top:0">
                                <div class="invoice-lines-head">
                                    <h2 class="invoice-lines-title">Line items</h2>
                                    <button type="button" class="btn btn--secondary btn--sm" id="invoice-add-line">Add line</button>
                                </div>
                                <div class="table-wrap invoice-lines-table-wrap">
                                    <table class="data-table invoice-lines-table<?= !$invoiceTaxEnabled ? ' invoice-lines-table--no-tax' : '' ?>">
                                        <thead>
                                        <tr>
                                            <th>Description</th>
                                            <th style="width:5.5rem">Qty</th>
                                            <th style="width:6.5rem"><?= $is_credit_note ? 'Unit (negative)' : 'Unit' ?></th>
                                            <?php if ($showLineTaxCol): ?>
                                                <th style="width:5rem">Tax %</th>
                                            <?php endif; ?>
                                            <th style="width:3rem"></th>
                                        </tr>
                                        </thead>
                                        <tbody data-invoice-lines data-tax-enabled="<?= $showLineTaxCol ? '1' : '0' ?>" data-next-index="<?= $rowCount ?>">
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
                                                <?php if ($showLineTaxCol): ?>
                                                    <td>
                                                        <label class="sr-only" for="line-tax-<?= $i ?>">Tax %</label>
                                                        <input class="input input--sm" id="line-tax-<?= $i ?>" data-line-field="tax_rate" name="lines[<?= $i ?>][tax_rate]" inputmode="decimal" value="<?= billo_e((string) $t) ?>">
                                                    </td>
                                                <?php endif; ?>
                                                <td class="invoice-line-actions">
                                                    <?php if (!$showLineTaxCol): ?>
                                                        <input type="hidden" data-line-field="tax_rate" name="lines[<?= $i ?>][tax_rate]" value="0">
                                                    <?php endif; ?>
                                                    <button type="button" class="btn btn--ghost btn--sm invoice-line-remove" title="Remove row" aria-label="Remove row">×</button>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
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
        <td>
            <label class="sr-only">Tax %</label>
            <input class="input input--sm" data-line-field="tax_rate" inputmode="decimal" value="0">
        </td>
        <td class="invoice-line-actions">
            <button type="button" class="btn btn--ghost btn--sm invoice-line-remove" title="Remove row" aria-label="Remove row">×</button>
        </td>
    </tr>
</template>
<template id="invoice-line-empty-row-no-tax">
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
