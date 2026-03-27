<?php
declare(strict_types=1);

use App\Core\Csrf;
use App\Support\InvoiceTheme;

/** @var array<string, mixed> $organization */
/** @var string $user_name */
/** @var string $role */
/** @var bool $show_team_nav */
/** @var string $error */
/** @var string $success */
/** @var list<array{code: string, name: string}>|mixed $nigerian_banks */
/** @var array<string, mixed>|null $organization_tax */
/** @var list<array<string, mixed>>|mixed $wht_types */
/** @var float|null $platform_vat_rate */

$error = $error ?? '';
$success = $success ?? '';
$o = $organization;
$val = static function (string $key) use ($o): string {
    $v = $o[$key] ?? '';

    return is_scalar($v) ? (string) $v : '';
};
$logoDisplayUrl = billo_organization_logo_display_url($o);
$hasStoredLogo = $logoDisplayUrl !== null;
$invoiceTaxOn = !isset($o['invoice_tax_enabled']) || (int) ($o['invoice_tax_enabled'] ?? 1) === 1;
$invoiceStyleCurrent = InvoiceTheme::normalizeStyle($val('invoice_style'));
$brandPrimary = $val('invoice_brand_primary') !== '' ? $val('invoice_brand_primary') : '#1E3A8A';
$brandAccent = $val('invoice_brand_accent') !== '' ? $val('invoice_brand_accent') : '#16A34A';
$styleLabels = [
    'modern' => 'Modern',
    'professional' => 'Professional',
    'premium' => 'Premium',
    'minimal' => 'Minimal',
];
$nigerian_banks = isset($nigerian_banks) && is_array($nigerian_banks) ? $nigerian_banks : [];
$orgTaxSettings = isset($organization_tax) && is_array($organization_tax) ? $organization_tax : [];
$whtTypesOrg = isset($wht_types) && is_array($wht_types) ? $wht_types : [];
$platVatOrg = isset($platform_vat_rate) ? (float) $platform_vat_rate : 7.5;
$orgVatOn = !empty($orgTaxSettings['enable_vat']);
$orgWhtOn = !empty($orgTaxSettings['enable_wht']);
$orgWhtDef = (int) ($orgTaxSettings['default_wht_id'] ?? 0);
$banksJson = json_encode($nigerian_banks, JSON_HEX_TAG | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE);
if ($banksJson === false) {
    $banksJson = '[]';
}

$title = 'Business details — billo';
ob_start();
?>
<section class="app-dashboard">
    <?php
    $active = 'organization';
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
                <h1 class="page-head__title">Business details</h1>
                <p class="page-head__lead">Shown on invoice PDFs, print view, and client emails. Your account name stays “<?= billo_e($val('name')) ?>” for the app.</p>
            </div>
            <a class="btn btn--secondary" href="<?= billo_e(billo_url('/dashboard')) ?>">Dashboard</a>
        </div>

        <div class="welcome-card org-settings-card">
            <form class="form form--spaced org-settings-form" method="post" action="<?= billo_e(billo_url('/organization')) ?>" enctype="multipart/form-data">
                <input type="hidden" name="_csrf" value="<?= billo_e(Csrf::token()) ?>">

                <div class="org-settings-tabs" data-org-settings-tabs>
                    <div class="org-settings-tablist" role="tablist" aria-label="Business settings sections">
                        <button type="button" class="org-settings-tab" role="tab" id="org-tab-business" aria-selected="true" aria-controls="org-panel-business" data-org-tab="business">Business &amp; legal</button>
                        <button type="button" class="org-settings-tab" role="tab" id="org-tab-branding" aria-selected="false" aria-controls="org-panel-branding" data-org-tab="branding" tabindex="-1">Logo &amp; footer</button>
                        <button type="button" class="org-settings-tab" role="tab" id="org-tab-bank" aria-selected="false" aria-controls="org-panel-bank" data-org-tab="bank" tabindex="-1">Bank</button>
                        <button type="button" class="org-settings-tab" role="tab" id="org-tab-invoicing" aria-selected="false" aria-controls="org-panel-invoicing" data-org-tab="invoicing" tabindex="-1">Invoicing &amp; PDF</button>
                        <button type="button" class="org-settings-tab" role="tab" id="org-tab-integrations" aria-selected="false" aria-controls="org-panel-integrations" data-org-tab="integrations" tabindex="-1">Integrations</button>
                    </div>

                    <div class="org-settings-panels">
                        <div class="org-settings-panel" role="tabpanel" id="org-panel-business" aria-labelledby="org-tab-business" data-org-panel="business">
                            <p class="org-settings-panel__lead">Legal name, address, identifiers, and website shown on invoices.</p>
                <div class="field">
                    <label class="label" for="legal_name">Legal / invoice name</label>
                    <input class="input" id="legal_name" name="legal_name" maxlength="200" value="<?= billo_e($val('legal_name')) ?>" placeholder="Same as display name or registered business name">
                </div>

                <div class="field">
                    <label class="label" for="billing_address_line1">Address line 1</label>
                    <input class="input" id="billing_address_line1" name="billing_address_line1" maxlength="255" value="<?= billo_e($val('billing_address_line1')) ?>">
                </div>
                <div class="field">
                    <label class="label" for="billing_address_line2">Address line 2</label>
                    <input class="input" id="billing_address_line2" name="billing_address_line2" maxlength="255" value="<?= billo_e($val('billing_address_line2')) ?>">
                </div>
                <div class="field-grid">
                    <div class="field">
                        <label class="label" for="billing_city">City</label>
                        <input class="input" id="billing_city" name="billing_city" maxlength="120" value="<?= billo_e($val('billing_city')) ?>">
                    </div>
                    <div class="field">
                        <label class="label" for="billing_state">State</label>
                        <input class="input" id="billing_state" name="billing_state" maxlength="120" value="<?= billo_e($val('billing_state')) ?>">
                    </div>
                </div>
                <div class="field">
                    <label class="label" for="billing_country">Country (ISO)</label>
                    <input class="input" id="billing_country" name="billing_country" maxlength="2" value="<?= billo_e($val('billing_country') ?: 'NG') ?>" placeholder="NG">
                </div>
                <div class="field">
                    <label class="label" for="tax_id">Tax / TIN ID</label>
                    <input class="input" id="tax_id" name="tax_id" maxlength="64" value="<?= billo_e($val('tax_id')) ?>" autocomplete="off">
                    <p class="hint">Used on invoices. <strong>One workspace per country + TIN</strong>—duplicates are blocked to avoid registering the same legal entity twice.</p>
                </div>
                <div class="field">
                    <label class="label" for="company_registration_number">CAC / company registration no.</label>
                    <input class="input" id="company_registration_number" name="company_registration_number" maxlength="40" value="<?= billo_e($val('company_registration_number')) ?>" placeholder="e.g. RC 123456 or BN 789012" autocomplete="off">
                    <p class="hint"><strong>One workspace per country + registration number</strong> on this platform (spaces and punctuation ignored when matching).</p>
                </div>
                <div class="field">
                    <label class="label" for="company_website">Company website</label>
                    <input class="input" id="company_website" name="company_website" type="text" maxlength="255" value="<?= billo_e($val('company_website')) ?>" placeholder="https://example.com or example.ng" inputmode="url" autocomplete="url">
                    <p class="hint">Optional public site; <strong>one workspace per domain</strong> (<code>www.</code> ignored). Shown on invoice PDF/print when set.</p>
                </div>
                        </div>

                        <div class="org-settings-panel" role="tabpanel" id="org-panel-branding" aria-labelledby="org-tab-branding" data-org-panel="branding" hidden>
                            <p class="org-settings-panel__lead">Logo and footer text for PDFs, print, and emails.</p>
                <div class="field org-logo-field">
                    <span class="label">Invoice logo</span>
                    <div class="org-logo-preview-wrap" id="org-logo-preview-wrap"<?= $hasStoredLogo ? '' : ' hidden' ?>>
                        <img class="org-logo-preview-wrap__img" id="org-logo-preview-img" src="<?= $hasStoredLogo ? billo_e((string) $logoDisplayUrl) : '' ?>" alt="Logo preview" width="200" height="80" loading="lazy" decoding="async">
                    </div>
                    <label class="label" for="logo_upload">Upload image</label>
                    <input class="input" type="file" id="logo_upload" name="logo_upload" accept="image/jpeg,image/png,image/gif,image/webp">
                    <p class="hint">Max <strong>1 MB</strong>. JPG, PNG, GIF, or WebP. Large images are scaled (long edge up to 1200 px) and re-encoded at high quality (JPEG 92% or PNG compression) to save space.</p>
                    <label class="org-logo-remove">
                        <input type="checkbox" name="remove_logo" value="1"> Remove logo (uploaded or URL)
                    </label>
                </div>
                <div class="field">
                    <label class="label" for="invoice_logo_url">Or logo URL / path</label>
                    <input class="input" id="invoice_logo_url" name="invoice_logo_url" maxlength="500" value="<?= billo_e($val('invoice_logo_url')) ?>" placeholder="https://… (optional if you upload)">
                    <p class="hint">Use an <strong>https</strong> URL, or leave blank when using an uploaded file.</p>
                </div>
                <div class="field">
                    <label class="label" for="invoice_footer">Invoice footer</label>
                    <textarea class="input input--textarea" id="invoice_footer" name="invoice_footer" rows="4" placeholder="Bank details, payment terms, registration footnotes…"><?= billo_e($val('invoice_footer')) ?></textarea>
                </div>
                        </div>

                        <div class="org-settings-panel" role="tabpanel" id="org-panel-bank" aria-labelledby="org-tab-bank" data-org-panel="bank" hidden>
                <div class="org-bank-block" style="margin-top:0;padding-top:0;border-top:none">
                    <h2 class="org-invoicing-block__title">Bank details</h2>
                    <p class="hint org-invoicing-block__lead">Shown on invoice PDF, print, and the invoice page. Fields are optional if you prefer to describe payment only in the footer.</p>

                    <div class="field billo-combobox" data-billo-bank-combobox>
                        <label class="label" for="invoice_bank_name">Bank</label>
                        <input type="hidden" name="invoice_bank_code" id="invoice_bank_code" value="<?= billo_e($val('invoice_bank_code')) ?>">
                        <input class="input billo-combobox__search" type="text" name="invoice_bank_name" id="invoice_bank_name" maxlength="160" value="<?= billo_e($val('invoice_bank_name')) ?>" placeholder="Search Nigerian banks or type any name" autocomplete="off">
                        <ul class="billo-combobox__list" id="org-bank-suggestions" role="listbox" hidden></ul>
                        <p class="hint">If <strong>payments.paystack.secret_key</strong> is set in config, the dropdown list is loaded from Paystack (cached 24h); otherwise a built‑in list is used.</p>
                    </div>
                    <div class="field-grid">
                        <div class="field">
                            <label class="label" for="invoice_bank_account_name">Account name</label>
                            <input class="input" id="invoice_bank_account_name" name="invoice_bank_account_name" maxlength="160" value="<?= billo_e($val('invoice_bank_account_name')) ?>" autocomplete="off">
                        </div>
                        <div class="field">
                            <label class="label" for="invoice_bank_account_number">Account number</label>
                            <input class="input" id="invoice_bank_account_number" name="invoice_bank_account_number" maxlength="32" inputmode="numeric" value="<?= billo_e($val('invoice_bank_account_number')) ?>" autocomplete="off">
                        </div>
                    </div>
                </div>
                        </div>

                        <div class="org-settings-panel" role="tabpanel" id="org-panel-invoicing" aria-labelledby="org-tab-invoicing" data-org-panel="invoicing" hidden>
                <div class="org-invoicing-block" style="margin-top:0;padding-top:0;border-top:none">
                    <h2 class="org-invoicing-block__title">Invoicing &amp; PDF</h2>
                    <p class="hint org-invoicing-block__lead">These settings apply to new and existing invoices: print view, PDF download, and the line-item editor.</p>

                    <div class="field-toggle">
                        <div class="field-toggle__text">
                            <strong class="field-toggle__label">Tax on invoices</strong>
                            <p class="hint" style="margin:0.35rem 0 0">When off, tax columns and totals are hidden (lines save with 0% tax). Turn off if you do not charge VAT or similar on this workspace.</p>
                        </div>
                        <label class="field-toggle__control">
                            <input type="checkbox" name="invoice_tax_enabled" value="1" class="field-toggle__input" <?= $invoiceTaxOn ? ' checked' : '' ?>>
                            <span class="field-toggle__track" aria-hidden="true"><span class="field-toggle__thumb"></span></span>
                            <span class="sr-only">Enable tax on invoices</span>
                        </label>
                    </div>

                    <div class="field" style="margin-top:1.25rem;padding-top:1rem;border-top:1px solid var(--color-border, #e2e8f0)">
                        <p class="eyebrow" style="margin:0 0 0.5rem">Document tax (VAT / WHT)</p>
                        <p class="hint" style="margin:0 0 1rem">Used for <strong>new standard invoices</strong> (subtotal VAT + subtotal WHT). The <strong>VAT percentage</strong> is set only by platform operators under <strong>System → Tax templates</strong> (first active <em>additive</em> row, usually “VAT (standard)”). It is currently <strong><?= billo_e(rtrim(rtrim(sprintf('%.2f', $platVatOrg), '0'), '.')) ?>%</strong> for this workspace.</p>
                        <div class="field-toggle" style="margin-bottom:0.75rem">
                            <div class="field-toggle__text">
                                <strong class="field-toggle__label">Enable VAT by default</strong>
                                <p class="hint" style="margin:0.35rem 0 0">New invoices pre-check “Apply VAT” using the platform VAT rate. You can still turn VAT off per invoice.</p>
                            </div>
                            <label class="field-toggle__control">
                                <input type="checkbox" name="org_enable_vat" value="1" class="field-toggle__input" <?= $orgVatOn ? ' checked' : '' ?>>
                                <span class="field-toggle__track" aria-hidden="true"><span class="field-toggle__thumb"></span></span>
                            </label>
                        </div>
                        <div class="field-toggle" style="margin:1rem 0 0.75rem">
                            <div class="field-toggle__text">
                                <strong class="field-toggle__label">Enable WHT by default</strong>
                                <p class="hint" style="margin:0.35rem 0 0">New invoices pre-check “Apply WHT” when a default type is set.</p>
                            </div>
                            <label class="field-toggle__control">
                                <input type="checkbox" name="org_enable_wht" value="1" class="field-toggle__input" <?= $orgWhtOn ? ' checked' : '' ?>>
                                <span class="field-toggle__track" aria-hidden="true"><span class="field-toggle__thumb"></span></span>
                            </label>
                        </div>
                        <div class="field">
                            <label class="label" for="org_default_wht_id">Default WHT type</label>
                            <select class="input" id="org_default_wht_id" name="org_default_wht_id" style="max-width:24rem">
                                <option value="">— None —</option>
                                <?php foreach ($whtTypesOrg as $w): ?>
                                    <?php
                                    if (!is_array($w)) {
                                        continue;
                                    }
                                    $wid = (int) ($w['id'] ?? 0);
                                    if ($wid <= 0) {
                                        continue;
                                    }
                                    $sel = $wid === $orgWhtDef ? ' selected' : '';
                                    $lab = (string) ($w['name'] ?? 'WHT') . ' (' . rtrim(rtrim(sprintf('%.2f', (float) ($w['rate'] ?? 0)), '0'), '.') . '%)';
                                    ?>
                                    <option value="<?= $wid ?>"<?= $sel ?>><?= billo_e($lab) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <div class="field">
                        <label class="label" for="invoice_style">Invoice style</label>
                        <select class="input" id="invoice_style" name="invoice_style">
                            <?php foreach (InvoiceTheme::STYLES as $st): ?>
                                <option value="<?= billo_e($st) ?>"<?= $st === $invoiceStyleCurrent ? ' selected' : '' ?>><?= billo_e($styleLabels[$st] ?? ucfirst($st)) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <p class="hint">Layout emphasis for PDF and print (headings, table header, accents).</p>
                    </div>

                    <div class="field-grid org-brand-colors">
                        <div class="field">
                            <label class="label" for="invoice_brand_primary">Primary brand color</label>
                            <input class="input org-brand-colors__picker" type="color" id="invoice_brand_primary" name="invoice_brand_primary" value="<?= billo_e($brandPrimary) ?>">
                            <p class="hint">Titles, totals, and main accents.</p>
                        </div>
                        <div class="field">
                            <label class="label" for="invoice_brand_accent">Accent color</label>
                            <input class="input org-brand-colors__picker" type="color" id="invoice_brand_accent" name="invoice_brand_accent" value="<?= billo_e($brandAccent) ?>">
                            <p class="hint">Highlights, borders, and secondary accents.</p>
                        </div>
                    </div>
                </div>
                        </div>

                        <div class="org-settings-panel" role="tabpanel" id="org-panel-integrations" aria-labelledby="org-tab-integrations" data-org-panel="integrations" hidden>
                            <?php
                            $nrsPlanAllowed = !empty($nrs_plan_allowed);
                            $platformNrsOk = !empty($platform_nrs_configured);
                            $nrsRequiresTin = !empty($nrs_plan_requires_tax_id);
                            $nrsCanEnable = $nrsPlanAllowed && $platformNrsOk;
                            $nrsOn = (int) ($val('nrs_enabled') ?: '0') === 1;
                            ?>
                            <p class="org-settings-panel__lead"><strong>Nigeria Revenue Service (NRS)</strong> invoice sync uses API credentials configured by your platform operator. You only turn sync on and supply your tenant reference in NRS if needed.</p>
                            <?php if (!$nrsPlanAllowed): ?>
                                <div class="alert alert--error" role="alert" style="margin-bottom:1rem">Your current plan does not include NRS integration. Upgrade under <a href="<?= billo_e(billo_url('/billing')) ?>">Plans &amp; billing</a> if your operator offers it on another tier.</div>
                            <?php elseif (!$platformNrsOk): ?>
                                <div class="alert alert--error" role="alert" style="margin-bottom:1rem">NRS is not switched on for this platform yet. Ask a system operator to complete <strong>System → NRS integration</strong>.</div>
                            <?php endif; ?>
                            <?php if ($nrsRequiresTin && $nrsCanEnable): ?>
                                <p class="hint" style="margin-bottom:1rem">This plan expects a <strong>tax / TIN</strong> on the Business details tab before NRS sync can stay enabled.</p>
                            <?php endif; ?>

                            <input type="hidden" name="nrs_enabled" value="0">
                            <div class="field-toggle" style="margin-bottom:1rem">
                                <div class="field-toggle__text">
                                    <strong class="field-toggle__label">Enable NRS invoice sync</strong>
                                    <p class="hint" style="margin:0.35rem 0 0"><?= $nrsCanEnable ? 'After you send an invoice, Billo posts JSON to the platform-configured NRS endpoint.' : 'Unavailable until your plan and platform setup allow NRS.' ?></p>
                                </div>
                                <label class="field-toggle__control">
                                    <?php if ($nrsCanEnable): ?>
                                        <input type="checkbox" name="nrs_enabled" value="1" class="field-toggle__input" id="nrs_enabled"<?= $nrsOn ? ' checked' : '' ?>>
                                    <?php else: ?>
                                        <input type="checkbox" class="field-toggle__input" id="nrs_enabled" disabled<?= $nrsOn ? ' checked' : '' ?> aria-disabled="true">
                                    <?php endif; ?>
                                    <span class="field-toggle__track" aria-hidden="true"><span class="field-toggle__thumb"></span></span>
                                </label>
                            </div>
                            <div class="field">
                                <label class="label" for="nrs_tenant_external_id">Tenant / org ID in NRS (optional)</label>
                                <input class="input" id="nrs_tenant_external_id" name="nrs_tenant_external_id" maxlength="120" value="<?= billo_e($val('nrs_tenant_external_id')) ?>" placeholder="Your ID in the NRS portal">
                                <p class="hint">Included in the payload as <code>nrs_tenant_external_id</code> when sync runs.</p>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="org-settings-form-actions">
                    <p class="org-settings-form-actions__hint">Saves all tabs in one step.</p>
                    <button type="submit" class="btn btn--primary">Save all changes</button>
                </div>
            </form>
            <script type="application/json" id="billo-ng-banks-data"><?= $banksJson ?></script>
        </div>
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
<script>
(function () {
    var input = document.getElementById('logo_upload');
    var wrap = document.getElementById('org-logo-preview-wrap');
    var img = document.getElementById('org-logo-preview-img');
    var removeCb = document.querySelector('input[name="remove_logo"]');
    var maxBytes = 1048576;
    if (!input || !wrap || !img) return;
    input.addEventListener('change', function () {
        var f = input.files && input.files[0];
        if (!f) return;
        if (f.size > maxBytes) {
            alert('Logo must be 1 MB or smaller.');
            input.value = '';
            return;
        }
        var rd = new FileReader();
        rd.onload = function () {
            img.src = rd.result;
            wrap.hidden = false;
            if (removeCb) removeCb.checked = false;
        };
        rd.readAsDataURL(f);
    });
    if (removeCb) {
        removeCb.addEventListener('change', function () {
            if (removeCb.checked) {
                wrap.hidden = true;
                img.removeAttribute('src');
                if (input) input.value = '';
            }
        });
    }
})();
(function () {
    var root = document.querySelector('[data-org-settings-tabs]');
    if (!root) return;
    var tabs = root.querySelectorAll('[data-org-tab]');
    var panels = root.querySelectorAll('[data-org-panel]');
    var valid = { business: 1, branding: 1, bank: 1, invoicing: 1, integrations: 1 };
    function show(id) {
        if (!valid[id]) id = 'business';
        tabs.forEach(function (btn) {
            var on = (btn.getAttribute('data-org-tab') || '') === id;
            btn.setAttribute('aria-selected', on ? 'true' : 'false');
            btn.tabIndex = on ? 0 : -1;
        });
        panels.forEach(function (p) {
            var on = (p.getAttribute('data-org-panel') || '') === id;
            if (on) p.removeAttribute('hidden');
            else p.setAttribute('hidden', '');
        });
        try {
            var path = window.location.pathname || '';
            var search = window.location.search || '';
            if (window.history && window.history.replaceState) {
                window.history.replaceState(null, '', path + search + '#' + id);
            } else {
                window.location.hash = id;
            }
        } catch (e) { /* ignore */ }
    }
    tabs.forEach(function (btn) {
        btn.addEventListener('click', function () {
            show(btn.getAttribute('data-org-tab') || 'business');
        });
    });
    var hash = (window.location.hash || '').replace(/^#/, '').toLowerCase();
    if (valid[hash]) show(hash);
})();
</script>
<script src="<?= billo_e(billo_asset('js/app.js')) ?>" defer></script>
</body>
</html>
