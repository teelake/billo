<?php
declare(strict_types=1);

use App\Core\Csrf;

/** @var array<string, mixed>|null $client */
/** @var bool $is_edit */
/** @var string $user_name */
/** @var string $role */
/** @var bool $show_team_nav */
/** @var string $error */

$error = $error ?? '';
$c = is_array($client) ? $client : [];
$id = isset($c['id']) ? (int) $c['id'] : 0;
$val = static function (string $key) use ($c): string {
    $v = $c[$key] ?? '';

    return is_scalar($v) ? (string) $v : '';
};

$title = ($is_edit ? 'Edit client' : 'New client') . ' — billo';
ob_start();
?>
<section class="app-dashboard">
    <?php
    $active = 'clients';
    include dirname(__DIR__) . '/partials/app_topbar.php';
    ?>
    <div class="container app-dashboard__body">
        <div class="page-head">
            <div>
                <h1 class="page-head__title"><?= $is_edit ? 'Edit client' : 'New client' ?></h1>
                <p class="page-head__lead"><?= $is_edit ? 'Update billing details.' : 'Add someone you’ll send invoices to.' ?></p>
            </div>
            <a class="btn btn--secondary" href="<?= billo_e(billo_url('/clients')) ?>">Back to list</a>
        </div>

        <?php if ($error !== ''): ?>
            <div class="alert alert--error" role="alert" style="margin-bottom:1rem"><?= billo_e($error) ?></div>
        <?php endif; ?>

        <div class="welcome-card" style="max-width:42rem">
            <form class="form form--spaced" method="post" action="<?= billo_e(billo_url($is_edit ? '/clients/update' : '/clients')) ?>">
                <input type="hidden" name="_csrf" value="<?= billo_e(Csrf::token()) ?>">
                <?php if ($is_edit): ?>
                    <input type="hidden" name="id" value="<?= $id ?>">
                <?php endif; ?>

                <div class="field">
                    <label class="label" for="name">Display name <span class="label__req">*</span></label>
                    <input class="input" id="name" name="name" required maxlength="200" value="<?= billo_e($val('name')) ?>" placeholder="e.g. Jane Doe or Acme Ltd">
                </div>
                <div class="field">
                    <label class="label" for="company_name">Company name</label>
                    <input class="input" id="company_name" name="company_name" maxlength="200" value="<?= billo_e($val('company_name')) ?>">
                </div>
                <div class="field-grid">
                    <div class="field">
                        <label class="label" for="email">Email</label>
                        <input class="input" id="email" name="email" type="email" maxlength="255" value="<?= billo_e($val('email')) ?>">
                    </div>
                    <div class="field">
                        <label class="label" for="phone">Phone</label>
                        <input class="input" id="phone" name="phone" maxlength="40" value="<?= billo_e($val('phone')) ?>">
                    </div>
                </div>
                <div class="field">
                    <label class="label" for="address_line1">Address line 1</label>
                    <input class="input" id="address_line1" name="address_line1" maxlength="255" value="<?= billo_e($val('address_line1')) ?>">
                </div>
                <div class="field">
                    <label class="label" for="address_line2">Address line 2</label>
                    <input class="input" id="address_line2" name="address_line2" maxlength="255" value="<?= billo_e($val('address_line2')) ?>">
                </div>
                <div class="field-grid">
                    <div class="field">
                        <label class="label" for="city">City</label>
                        <input class="input" id="city" name="city" maxlength="120" value="<?= billo_e($val('city')) ?>">
                    </div>
                    <div class="field">
                        <label class="label" for="state">State / region</label>
                        <input class="input" id="state" name="state" maxlength="120" value="<?= billo_e($val('state')) ?>">
                    </div>
                </div>
                <div class="field-grid">
                    <div class="field">
                        <label class="label" for="country">Country (ISO code)</label>
                        <input class="input" id="country" name="country" maxlength="2" value="<?= billo_e($val('country') !== '' ? $val('country') : 'NG') ?>" aria-describedby="country-hint">
                        <p class="hint" id="country-hint">Two letters, e.g. NG</p>
                    </div>
                    <div class="field">
                        <label class="label" for="tax_id">Tax ID (TIN)</label>
                        <input class="input" id="tax_id" name="tax_id" maxlength="64" value="<?= billo_e($val('tax_id')) ?>">
                    </div>
                </div>
                <div class="field">
                    <label class="label" for="notes">Notes</label>
                    <textarea class="input input--textarea" id="notes" name="notes" rows="3" maxlength="8192"><?= billo_e($val('notes')) ?></textarea>
                </div>

                <div class="form-actions">
                    <button class="btn btn--primary" type="submit"><?= $is_edit ? 'Save changes' : 'Create client' ?></button>
                    <a class="btn btn--ghost" href="<?= billo_e(billo_url('/clients')) ?>">Cancel</a>
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
