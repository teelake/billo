<?php
declare(strict_types=1);

use App\Core\Csrf;

/** @var array<string, mixed>|null $organization */
/** @var string $role */
/** @var string $user_name */
/** @var string $user_email */
/** @var bool $email_verified */
/** @var bool $show_team_nav */
/** @var bool $can_manage_clients */
/** @var bool $is_platform_operator */
/** @var array<string, mixed>|null $platform_summary */
/** @var array<string, mixed>|null $tenant_summary */
/** @var string $error */
/** @var string $success */
$can_manage_clients = !empty($can_manage_clients);
$is_platform_operator = !empty($is_platform_operator);
$platform_summary = is_array($platform_summary ?? null) ? $platform_summary : null;
$tenant_summary = is_array($tenant_summary ?? null) ? $tenant_summary : null;

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

        <?php if ($is_platform_operator && $platform_summary !== null && function_exists('billo_app_nav_mode') && billo_app_nav_mode() === 'platform'): ?>
            <div class="platform-command">
                <div class="platform-command__head">
                    <div>
                        <p class="eyebrow eyebrow--dark">Platform operator</p>
                        <h2 class="platform-command__title">Live platform snapshot</h2>
                    </div>
                    <div class="platform-command__actions">
                        <a class="btn btn--primary" href="<?= billo_e(billo_url('/system/analytics')) ?>">Platform analytics</a>
                        <a class="btn btn--secondary" href="<?= billo_e(billo_url('/system/reports')) ?>">Reports</a>
                        <a class="btn btn--secondary" href="<?= billo_e(billo_url('/system/operators')) ?>">Operators</a>
                        <a class="btn btn--secondary" href="<?= billo_e(billo_url('/system/configuration')) ?>">Configuration</a>
                    </div>
                </div>
                <div class="platform-stat-grid">
                    <div class="platform-stat">
                        <p class="platform-stat__label">Organizations</p>
                        <p class="platform-stat__value"><?= (int) ($platform_summary['organizations'] ?? 0) ?></p>
                    </div>
                    <div class="platform-stat">
                        <p class="platform-stat__label">Users</p>
                        <p class="platform-stat__value"><?= (int) ($platform_summary['users'] ?? 0) ?></p>
                    </div>
                    <div class="platform-stat">
                        <p class="platform-stat__label">Invoices</p>
                        <p class="platform-stat__value"><?= (int) ($platform_summary['invoices'] ?? 0) ?></p>
                    </div>
                    <div class="platform-stat platform-stat--accent">
                        <p class="platform-stat__label">Collected (paid)</p>
                        <p class="platform-stat__value"><?= billo_e((string) ($platform_summary['revenue_paid'] ?? '0.00')) ?></p>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <?php if ($tenant_summary !== null): ?>
            <div class="tenant-overview">
                <div class="tenant-overview__head">
                    <h2 class="tenant-overview__title">Your organization at a glance</h2>
                    <a class="btn btn--secondary btn--sm" href="<?= billo_e(billo_url('/analytics')) ?>">Open analytics</a>
                </div>
                <div class="tenant-stat-grid">
                    <div class="tenant-stat">
                        <span class="tenant-stat__val"><?= (int) ($tenant_summary['clients'] ?? 0) ?></span>
                        <span class="tenant-stat__lab">Clients</span>
                    </div>
                    <div class="tenant-stat">
                        <span class="tenant-stat__val"><?= (int) ($tenant_summary['invoices'] ?? 0) ?></span>
                        <span class="tenant-stat__lab">Invoices</span>
                    </div>
                    <div class="tenant-stat">
                        <span class="tenant-stat__val"><?= (int) ($tenant_summary['invoices_paid'] ?? 0) ?></span>
                        <span class="tenant-stat__lab">Paid</span>
                    </div>
                    <div class="tenant-stat tenant-stat--accent">
                        <span class="tenant-stat__val"><?= billo_e((string) ($tenant_summary['revenue_paid'] ?? '0.00')) ?></span>
                        <span class="tenant-stat__lab">Revenue (paid)</span>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <div class="welcome-card">
            <p class="eyebrow eyebrow--dark">You’re in</p>
            <h1 class="welcome-card__title"><?= billo_e($orgName) ?></h1>
            <p class="welcome-card__text">
                Signed in as <strong><?= billo_e($user_email) ?></strong>. Create invoices, track clients, and keep your org data in one place.
            </p>
            <div class="welcome-card__actions">
                <a class="btn btn--primary" href="<?= billo_e(billo_url('/invoices')) ?>">Invoices</a>
                <a class="btn btn--secondary" href="<?= billo_e(billo_url('/clients')) ?>">Clients</a>
                <?php if ($can_manage_clients): ?>
                    <a class="btn btn--secondary" href="<?= billo_e(billo_url('/invoices/create')) ?>">New invoice</a>
                <?php endif; ?>
            </div>
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
