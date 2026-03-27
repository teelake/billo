<?php
declare(strict_types=1);

use App\Core\Csrf;

/** @var array<string, mixed> $user */
/** @var string $user_name */
/** @var string $user_email */
/** @var string $role */
/** @var bool $show_team_nav */
/** @var string $active */
/** @var string $error */
/** @var string $success */

$error = $error ?? '';
$success = $success ?? '';
$u = $user;
$nameVal = isset($u['name']) && is_scalar($u['name']) ? (string) $u['name'] : '';
$emailVal = isset($u['email']) && is_scalar($u['email']) ? (string) $u['email'] : '';
$verified = !empty($u['email_verified_at']);
$title = 'Profile — billo';
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
                <h1 class="page-head__title">Your profile</h1>
                <p class="page-head__lead">Update the name and email on your account.</p>
            </div>
            <a class="btn btn--secondary" href="<?= billo_e(billo_url('/dashboard')) ?>">Dashboard</a>
        </div>

        <div class="welcome-card" style="max-width:44rem">
            <?php if (!$verified): ?>
                <p class="hint-banner" style="margin-bottom:1rem">Your email is not verified yet. On the <a href="<?= billo_e(billo_url('/dashboard')) ?>">dashboard</a>, use <strong>Resend email</strong> in the yellow banner.</p>
            <?php endif; ?>
            <form class="form form--spaced" method="post" action="<?= billo_e(billo_url('/account/profile')) ?>">
                <input type="hidden" name="_csrf" value="<?= billo_e(Csrf::token()) ?>">
                <div class="field">
                    <label class="label" for="profile-name">Full name</label>
                    <input class="input" id="profile-name" name="name" type="text" autocomplete="name" required maxlength="120" value="<?= billo_e($nameVal) ?>">
                </div>
                <div class="field">
                    <label class="label" for="profile-email">Email</label>
                    <input class="input" id="profile-email" name="email" type="email" autocomplete="email" required maxlength="255" value="<?= billo_e($emailVal) ?>">
                    <p class="hint">Changing your email sends a new confirmation link and signs you out of “verified” status until you confirm.</p>
                </div>
                <div class="form-actions">
                    <button type="submit" class="btn btn--primary">Save profile</button>
                    <a class="btn btn--secondary" href="<?= billo_e(billo_url('/account/password')) ?>">Change password</a>
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
