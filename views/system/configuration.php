<?php
declare(strict_types=1);

/** @var array<string, array<string, string>> $config_snapshot */
/** @var string $user_name */
/** @var string $role */
/** @var bool $show_team_nav */
/** @var string $error */
/** @var string $success */

$error = $error ?? '';
$success = $success ?? '';
$title = 'Platform configuration — billo';
ob_start();
?>
<section class="app-dashboard">
    <?php
    $active = 'system-configuration';
    $user_email = (string) \App\Core\Session::get('user_email', '');
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
                <p class="eyebrow eyebrow--dark">platform operator</p>
                <h1 class="page-head__title">Configuration snapshot</h1>
                <p class="page-head__lead">Read-only view of runtime settings. Secrets are masked. To change values, edit <code>config/config.php</code> (and optional <code>config/local.php</code>) on the server, then reload PHP.</p>
            </div>
            <a class="btn btn--secondary" href="<?= billo_e(billo_url('/platform/landing')) ?>">Landing content</a>
        </div>

        <div class="config-grid">
            <?php foreach ($config_snapshot as $sectionTitle => $rows): ?>
                <div class="welcome-card config-card">
                    <h2 class="config-card__title"><?= billo_e(ucfirst((string) $sectionTitle)) ?></h2>
                    <dl class="config-kv">
                        <?php foreach ($rows as $label => $value): ?>
                            <dt><?= billo_e((string) $label) ?></dt>
                            <dd><?= billo_e((string) $value) ?></dd>
                        <?php endforeach; ?>
                    </dl>
                </div>
            <?php endforeach; ?>
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
