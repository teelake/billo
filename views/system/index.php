<?php
declare(strict_types=1);

/** @var int $user_count */
/** @var int $organization_count */
/** @var int $invoice_count */
/** @var string $user_name */
/** @var string $role */
/** @var bool $show_team_nav */

$error = $error ?? '';
$success = $success ?? '';
$title = 'System — billo';
ob_start();
?>
<section class="app-dashboard">
    <?php
    $active = 'system';
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
                <p class="eyebrow eyebrow--dark">system operator</p>
                <h1 class="page-head__title">Platform overview</h1>
                <p class="page-head__lead">Cross-tenant view. Day-to-day work stays in your organization dashboard.</p>
            </div>
            <a class="btn btn--secondary" href="<?= billo_e(billo_url('/dashboard')) ?>">Dashboard</a>
        </div>

        <div class="welcome-card" style="margin-bottom:1.25rem">
            <h2 class="invoice-detail-card__h">What you can do here</h2>
            <ul class="checklist" style="margin:.75rem 0 0">
                <li><a href="<?= billo_e(billo_url('/system/analytics')) ?>">Platform analytics</a> — trends, charts, org leaderboard, org-level CSV export</li>
                <li><a href="<?= billo_e(billo_url('/system/reports')) ?>">Platform reports</a> — filterable organizations, invoices, and users with pagination + export</li>
                <li><a href="<?= billo_e(billo_url('/system/operators')) ?>">Platform operators</a> — grant or revoke system access (no separate passwords)</li>
                <li><a href="<?= billo_e(billo_url('/system/configuration')) ?>">Configuration</a> — edit platform settings stored in the database (overrides file config)</li>
                <li><a href="<?= billo_e(billo_url('/platform/landing')) ?>">Edit public landing / marketing copy</a></li>
            </ul>
        </div>

        <div class="feature-grid" style="grid-template-columns:repeat(auto-fit,minmax(11rem,1fr));gap:1rem">
            <div class="welcome-card">
                <p class="eyebrow">Organizations</p>
                <p style="font-size:1.75rem;font-weight:700;margin:0"><?= (int) $organization_count ?></p>
            </div>
            <div class="welcome-card">
                <p class="eyebrow">Users</p>
                <p style="font-size:1.75rem;font-weight:700;margin:0"><?= (int) $user_count ?></p>
            </div>
            <div class="welcome-card">
                <p class="eyebrow">Invoices</p>
                <p style="font-size:1.75rem;font-weight:700;margin:0"><?= (int) $invoice_count ?></p>
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
