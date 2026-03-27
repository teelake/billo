<?php
declare(strict_types=1);

use App\Core\Csrf;

/** @var string $user_name */
/** @var string $user_email */
/** @var string $role */
/** @var bool $show_team_nav */
/** @var string $active */
/** @var string $error */
/** @var string $success */

$error = $error ?? '';
$success = $success ?? '';
$title = 'Change password — billo';
ob_start();
?>
<section class="app-dashboard">
    <?php
    $active = $active ?? '';
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
                <h1 class="page-head__title">Change password</h1>
                <p class="page-head__lead">Use a strong password you don’t reuse on other sites.</p>
            </div>
            <div class="page-head__actions" style="display:flex;flex-wrap:wrap;gap:0.5rem;align-items:center">
                <a class="btn btn--secondary" href="<?= billo_e(billo_url('/account/profile')) ?>">Profile</a>
                <a class="btn btn--ghost btn--sm" href="<?= billo_e(billo_url('/forgot-password')) ?>">Forgot current password?</a>
            </div>
        </div>

        <div class="welcome-card" style="max-width:44rem">
            <form class="form form--spaced" method="post" action="<?= billo_e(billo_url('/account/password')) ?>" data-password-reset-form>
                <input type="hidden" name="_csrf" value="<?= billo_e(Csrf::token()) ?>">
                <div class="field">
                    <label class="label" for="current_password">Current password</label>
                    <input class="input" id="current_password" name="current_password" type="password" autocomplete="current-password" required minlength="1" maxlength="128">
                </div>
                <div class="field">
                    <label class="label" for="password">New password</label>
                    <input class="input" id="password" name="password" type="password" autocomplete="new-password" required minlength="10" maxlength="128" aria-describedby="pw-hint password-strength">
                    <p class="hint" id="pw-hint">10+ characters, letters and numbers, plus an uppercase letter or symbol.</p>
                    <p class="field-feedback" id="password-strength" role="status" aria-live="polite"></p>
                </div>
                <div class="field">
                    <label class="label" for="password_confirm">Confirm new password</label>
                    <input class="input" id="password_confirm" name="password_confirm" type="password" autocomplete="new-password" required minlength="10" maxlength="128" aria-describedby="password-confirm-feedback">
                    <p class="field-feedback" id="password-confirm-feedback" role="status" aria-live="polite"></p>
                </div>
                <div class="form-actions">
                    <button type="submit" class="btn btn--primary">Update password</button>
                    <a class="btn btn--secondary" href="<?= billo_e(billo_url('/dashboard')) ?>">Cancel</a>
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
