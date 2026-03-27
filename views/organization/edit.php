<?php
declare(strict_types=1);

use App\Core\Csrf;

/** @var array<string, mixed> $organization */
/** @var string $user_name */
/** @var string $role */
/** @var bool $show_team_nav */
/** @var string $error */
/** @var string $success */

$error = $error ?? '';
$success = $success ?? '';
$o = $organization;
$val = static function (string $key) use ($o): string {
    $v = $o[$key] ?? '';

    return is_scalar($v) ? (string) $v : '';
};
$logoDisplayUrl = billo_organization_logo_display_url($o);
$hasStoredLogo = $logoDisplayUrl !== null;

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

        <div class="welcome-card" style="max-width:44rem">
            <form class="form form--spaced" method="post" action="<?= billo_e(billo_url('/organization')) ?>" enctype="multipart/form-data">
                <input type="hidden" name="_csrf" value="<?= billo_e(Csrf::token()) ?>">

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

                <div class="form-actions">
                    <button type="submit" class="btn btn--primary">Save</button>
                </div>
            </form>
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
</script>
<script src="<?= billo_e(billo_asset('js/app.js')) ?>" defer></script>
</body>
</html>
