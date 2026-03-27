<?php
declare(strict_types=1);

use App\Core\Csrf;

/** @var array<string, mixed>|null $organization */
/** @var string $role */
/** @var string $user_name */
/** @var string $user_email */
/** @var bool $email_verified */
/** @var bool $show_team_nav */
/** @var string $error */
/** @var string $success */

$orgName = is_array($organization) ? (string) ($organization['name'] ?? 'Your organization') : 'Your organization';
$title = 'Dashboard — billo';
ob_start();
?>
<section class="app-dashboard">
    <?php
    $active = 'dashboard';
    include dirname(__DIR__) . '/partials/app_topbar.php';
    ?>
    <div class="container app-dashboard__body">
        <?php if ($error !== ''): ?>
            <div class="alert alert--error" role="alert" style="margin-bottom:1rem"><?= billo_e($error) ?></div>
        <?php endif; ?>
        <?php if ($success !== ''): ?>
            <div class="alert alert--success" role="alert" style="margin-bottom:1rem"><?= billo_e($success) ?></div>
        <?php endif; ?>

        <?php if (empty($email_verified)): ?>
            <div class="alert alert--verify" role="status">
                <strong>Verify your email</strong> so we can reach you for invoices and security alerts.
                <form method="post" action="<?= billo_e(billo_url('/email/verification-notification')) ?>" class="inline-form verify-form">
                    <input type="hidden" name="_csrf" value="<?= billo_e(Csrf::token()) ?>">
                    <button type="submit" class="btn btn--secondary btn--sm">Resend email</button>
                </form>
            </div>
        <?php endif; ?>

        <div class="welcome-card">
            <p class="eyebrow eyebrow--dark">You’re in</p>
            <h1 class="welcome-card__title"><?= billo_e($orgName) ?></h1>
            <p class="welcome-card__text">
                Signed in as <strong><?= billo_e($user_email) ?></strong>. Invoice tools, branding, and FIRS-ready workflows will plug in here next.
            </p>
            <div class="welcome-card__chips">
                <span class="chip">organization_id · <?= isset($organization['id']) ? (int) $organization['id'] : '—' ?></span>
                <span class="chip">Role · <?= billo_e($role) ?></span>
            </div>
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
    <meta name="theme-color" content="#16A34A">
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
