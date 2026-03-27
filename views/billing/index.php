<?php
declare(strict_types=1);

use App\Core\Csrf;

/** @var list<array<string, mixed>> $plans */
/** @var array<int, list<array<string, mixed>>> $plan_items_by_plan */
/** @var array<string, mixed>|null $current */
/** @var string $user_name */
/** @var string $role */
/** @var bool $show_team_nav */
/** @var string $error */
/** @var string $success */

$error = $error ?? '';
$success = $success ?? '';
$plans = isset($plans) && is_array($plans) ? $plans : [];
$cur = isset($current) && is_array($current) ? $current : null;
$currentPlanId = $cur !== null ? (int) ($cur['plan_id'] ?? 0) : 0;
$title = 'Plans & billing — billo';
ob_start();
?>
<section class="app-dashboard">
    <?php
    $active = 'billing';
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
                <h1 class="page-head__title">Plans & billing</h1>
                <p class="page-head__lead">Choose a workspace plan. Free plans apply immediately; paid plans checkout with Paystack when your operator has configured payments.</p>
            </div>
            <a class="btn btn--secondary" href="<?= billo_e(billo_url('/dashboard')) ?>">Dashboard</a>
        </div>

        <?php if ($cur !== null): ?>
            <div class="welcome-card" style="margin-bottom:1.25rem">
                <p class="eyebrow" style="margin:0 0 0.35rem">Current plan</p>
                <p style="margin:0;font-size:1.05rem"><strong><?= billo_e((string) ($cur['plan_name'] ?? '—')) ?></strong>
                    <?php if (!empty($cur['plan_slug'])): ?>
                        <span class="hint">(<?= billo_e((string) $cur['plan_slug']) ?>)</span>
                    <?php endif; ?>
                </p>
                <?php if (!empty($cur['plan_description'])): ?>
                    <p class="hint" style="margin:0.5rem 0 0"><?= billo_e((string) $cur['plan_description']) ?></p>
                <?php endif; ?>
            </div>
        <?php elseif ($plans === []): ?>
            <div class="welcome-card">
                <p>Subscription plans are not available yet. Ask a platform operator to run migrations <code>015_plans_nrs_subscriptions.sql</code> and <code>016_plan_items_platform_nrs.sql</code>.</p>
            </div>
        <?php endif; ?>

        <?php if ($plans !== []): ?>
            <div class="field-grid" style="grid-template-columns:repeat(auto-fill,minmax(16rem,1fr));gap:1rem">
                <?php foreach ($plans as $p): ?>
                    <?php
                    if (!is_array($p)) {
                        continue;
                    }
                    $pid = (int) ($p['id'] ?? 0);
                    if ($pid <= 0) {
                        continue;
                    }
                    $name = (string) ($p['name'] ?? 'Plan');
                    $slug = (string) ($p['slug'] ?? '');
                    $desc = isset($p['description']) ? trim((string) $p['description']) : '';
                    $price = (float) ($p['price_amount'] ?? 0);
                    $curCode = (string) ($p['currency'] ?? 'NGN');
                    $intv = (string) ($p['billing_interval'] ?? 'monthly');
                    $isCurrent = $pid === $currentPlanId && $currentPlanId > 0;
                    $bullets = $itemsByPlan[$pid] ?? [];
                    ?>
                    <div class="welcome-card" style="margin:0;display:flex;flex-direction:column;gap:0.75rem">
                        <h2 class="invoice-lines-title" style="margin:0"><?= billo_e($name) ?></h2>
                        <?php if ($desc !== ''): ?>
                            <p class="hint" style="margin:0"><?= billo_e($desc) ?></p>
                        <?php endif; ?>
                        <?php if ($bullets !== []): ?>
                            <ul class="hint" style="margin:0;padding-left:1.15rem">
                                <?php foreach ($bullets as $bi): ?>
                                    <?php
                                    if (!is_array($bi)) {
                                        continue;
                                    }
                                    $bl = trim((string) ($bi['label'] ?? ''));
                                    $bd = trim((string) ($bi['detail'] ?? ''));
                                    if ($bl === '') {
                                        continue;
                                    }
                                    ?>
                                    <li style="margin:0.2rem 0"><?= billo_e($bl) ?><?= $bd !== '' ? ' — ' . billo_e($bd) : '' ?></li>
                                <?php endforeach; ?>
                            </ul>
                        <?php endif; ?>
                        <p style="margin:0;font-size:1.15rem;font-weight:600">
                            <?php if ($price < 0.01): ?>
                                Free
                            <?php else: ?>
                                <?= billo_e($curCode) ?> <?= billo_e(number_format($price, 2)) ?>
                                <span class="hint" style="font-weight:500;font-size:0.9rem"> / <?= billo_e($intv) ?></span>
                            <?php endif; ?>
                        </p>
                        <?php if ($isCurrent): ?>
                            <span class="status-pill status-pill--paid">Current</span>
                        <?php else: ?>
                            <form method="post" action="<?= billo_e(billo_url('/billing/subscribe')) ?>">
                                <input type="hidden" name="_csrf" value="<?= billo_e(Csrf::token()) ?>">
                                <input type="hidden" name="plan_id" value="<?= $pid ?>">
                                <button type="submit" class="btn btn--primary btn--block">
                                    <?= $price < 0.01 ? 'Switch to this plan' : 'Buy / upgrade' ?>
                                </button>
                            </form>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
            <p class="hint" style="margin-top:1.25rem">Downgrades and upgrades use the same flow: pick another plan. If you already paid for a period, contact support for proration rules you want enforced.</p>
        <?php endif; ?>
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
