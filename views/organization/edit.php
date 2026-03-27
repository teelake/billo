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
            <form class="form form--spaced" method="post" action="<?= billo_e(billo_url('/organization')) ?>">
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
                <div class="field">
                    <label class="label" for="invoice_logo_url">Logo URL or path</label>
                    <input class="input" id="invoice_logo_url" name="invoice_logo_url" maxlength="500" value="<?= billo_e($val('invoice_logo_url')) ?>" placeholder="https://… or storage/branding/logo.png">
                    <p class="hint">Use an <strong>https</strong> image URL, or a path under the project (e.g. <code>storage/branding/logo.png</code>). No file upload yet.</p>
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
<script src="<?= billo_e(billo_asset('js/app.js')) ?>" defer></script>
</body>
</html>
