<?php
declare(strict_types=1);

/** @var array<string, mixed> $summary */
/** @var array<string, int> $status */
/** @var array<string, mixed> $trends */
/** @var list<string> $trend_labels */

$error = $error ?? '';
$success = $success ?? '';
$title = 'Analytics — billo';
$summary = isset($summary) && is_array($summary) ? $summary : [];
$status = isset($status) && is_array($status) ? $status : [];
$trends = isset($trends) && is_array($trends) ? $trends : [
    'months' => [], 'new_invoices' => [], 'paid_totals' => [],
];
$trend_labels = isset($trend_labels) && is_array($trend_labels) ? $trend_labels : [];

ob_start();
?>
<section class="app-dashboard">
    <?php
    $active = 'analytics';
    include dirname(__DIR__) . '/partials/app_topbar.php';
    ?>
    <div class="container app-dashboard__body app-analytics app-analytics--tenant">
        <?php if ($error !== ''): ?>
            <div class="alert alert--error analytic-alert" role="alert"><?= billo_e($error) ?></div>
        <?php endif; ?>
        <?php if ($success !== ''): ?>
            <div class="alert alert--success analytic-alert" role="alert"><?= billo_e($success) ?></div>
        <?php endif; ?>

        <header class="analytics-hero analytics-hero--tenant">
            <div class="analytics-hero__copy">
                <p class="analytics-hero__eyebrow">Your organization</p>
                <h1 class="analytics-hero__title">Business analytics</h1>
                <p class="analytics-hero__lead">Invoice health, pipeline value, and trends for the workspace you’re signed into.</p>
            </div>
            <div class="analytics-hero__actions">
                <a class="btn btn--secondary" href="<?= billo_e(billo_url('/dashboard')) ?>">Dashboard</a>
                <a class="btn btn--primary" href="<?= billo_e(billo_url('/invoices')) ?>">Invoices</a>
            </div>
        </header>

        <div class="analytics-kpi-grid analytics-kpi-grid--tenant">
            <article class="analytics-kpi analytics-kpi--emerald">
                <p class="analytics-kpi__label">Clients</p>
                <p class="analytics-kpi__value"><?= (int) ($summary['clients'] ?? 0) ?></p>
                <p class="analytics-kpi__hint">Active in CRM</p>
            </article>
            <article class="analytics-kpi analytics-kpi--navy">
                <p class="analytics-kpi__label">Invoices</p>
                <p class="analytics-kpi__value"><?= (int) ($summary['invoices'] ?? 0) ?></p>
                <p class="analytics-kpi__hint"><?= (int) ($summary['invoices_paid'] ?? 0) ?> paid</p>
            </article>
            <article class="analytics-kpi analytics-kpi--revenue">
                <p class="analytics-kpi__label">Collected</p>
                <p class="analytics-kpi__value"><?= billo_e($summary['revenue_paid'] ?? '0.00') ?></p>
                <p class="analytics-kpi__hint">Paid invoice totals</p>
            </article>
            <article class="analytics-kpi analytics-kpi--amber">
                <p class="analytics-kpi__label">Outstanding (sent)</p>
                <p class="analytics-kpi__value"><?= billo_e($summary['sent_value'] ?? '0.00') ?></p>
                <p class="analytics-kpi__hint">Awaiting payment</p>
            </article>
            <article class="analytics-kpi analytics-kpi--slate">
                <p class="analytics-kpi__label">Draft value</p>
                <p class="analytics-kpi__value"><?= billo_e($summary['draft_value'] ?? '0.00') ?></p>
                <p class="analytics-kpi__hint">Not yet issued</p>
            </article>
        </div>

        <section class="analytics-panel">
            <div class="analytics-panel__head">
                <h2 class="analytics-panel__title">Pipeline</h2>
                <p class="analytics-panel__sub">Status counts</p>
            </div>
            <div class="analytics-status-badges">
                <?php foreach (['draft' => 'Draft', 'sent' => 'Sent', 'paid' => 'Paid', 'void' => 'Void'] as $key => $lab): ?>
                    <div class="analytics-status-badge analytics-status-badge--<?= billo_e($key) ?>">
                        <span class="analytics-status-badge__label"><?= billo_e($lab) ?></span>
                        <span class="analytics-status-badge__value"><?= (int) ($status[$key] ?? 0) ?></span>
                    </div>
                <?php endforeach; ?>
            </div>
        </section>

        <div class="analytics-charts-grid analytics-charts-grid--tenant">
            <?php
            $chart_title = 'Invoices issued (monthly)';
            $chart_labels = $trend_labels;
            $chart_values = $trends['new_invoices'] ?? [];
            $chart_format = 'int';
            $chart_accent = 'navy';
            include dirname(__DIR__) . '/partials/analytics_bar_chart.php';
            ?>
            <?php
            $chart_title = 'Paid volume (monthly)';
            $chart_labels = $trend_labels;
            $chart_values = $trends['paid_totals'] ?? [];
            $chart_format = 'money';
            $chart_accent = 'emerald';
            include dirname(__DIR__) . '/partials/analytics_bar_chart.php';
            ?>
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
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Plus+Jakarta+Sans:wght@600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= billo_e(billo_asset('css/app.css')) ?>">
</head>
<body class="<?= billo_e($bodyClass) ?>">
<?= $content ?>
<script src="<?= billo_e(billo_asset('js/app.js')) ?>" defer></script>
</body>
</html>
