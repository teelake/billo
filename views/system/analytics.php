<?php
declare(strict_types=1);

/** @var array<string, mixed> $summary */
/** @var array<string, int> $status */
/** @var array<string, mixed> $trends */
/** @var list<string> $trend_labels */
/** @var list<array<string, mixed>> $top_orgs */

$error = $error ?? '';
$success = $success ?? '';
$title = 'Platform analytics — billo';
$summary = isset($summary) && is_array($summary) ? $summary : [];
$status = isset($status) && is_array($status) ? $status : [];
$trends = isset($trends) && is_array($trends) ? $trends : [
    'months' => [], 'new_users' => [], 'new_orgs' => [], 'new_invoices' => [], 'paid_totals' => [],
];
$trend_labels = isset($trend_labels) && is_array($trend_labels) ? $trend_labels : [];
$top_orgs = isset($top_orgs) && is_array($top_orgs) ? $top_orgs : [];

ob_start();
?>
<section class="app-dashboard">
    <?php
    $active = 'system-analytics';
    include dirname(__DIR__) . '/partials/app_topbar.php';
    ?>
    <div class="container app-dashboard__body app-analytics">
        <?php if ($error !== ''): ?>
            <div class="alert alert--error analytic-alert" role="alert"><?= billo_e($error) ?></div>
        <?php endif; ?>
        <?php if ($success !== ''): ?>
            <div class="alert alert--success analytic-alert" role="alert"><?= billo_e($success) ?></div>
        <?php endif; ?>

        <header class="analytics-hero">
            <div class="analytics-hero__copy">
                <p class="analytics-hero__eyebrow">Platform intelligence</p>
                <h1 class="analytics-hero__title">Cross-tenant analytics</h1>
            </div>
            <div class="analytics-hero__actions">
                <a class="btn btn--secondary" href="<?= billo_e(billo_url('/system')) ?>">Overview</a>
                <a class="btn btn--primary" href="<?= billo_e(billo_url('/system/analytics/export')) ?>">Export report</a>
            </div>
        </header>

        <div class="analytics-kpi-grid">
            <article class="analytics-kpi analytics-kpi--navy">
                <p class="analytics-kpi__label">Organizations</p>
                <p class="analytics-kpi__value"><?= (int) ($summary['organizations'] ?? 0) ?></p>
                <p class="analytics-kpi__hint">Tenants on Billo</p>
            </article>
            <article class="analytics-kpi analytics-kpi--emerald">
                <p class="analytics-kpi__label">Users</p>
                <p class="analytics-kpi__value"><?= (int) ($summary['users'] ?? 0) ?></p>
                <p class="analytics-kpi__hint">Accounts (all orgs)</p>
            </article>
            <article class="analytics-kpi analytics-kpi--amber">
                <p class="analytics-kpi__label">Clients</p>
                <p class="analytics-kpi__value"><?= (int) ($summary['clients'] ?? 0) ?></p>
                <p class="analytics-kpi__hint">Global address book</p>
            </article>
            <article class="analytics-kpi analytics-kpi--slate">
                <p class="analytics-kpi__label">Invoices</p>
                <p class="analytics-kpi__value"><?= (int) ($summary['invoices'] ?? 0) ?></p>
                <p class="analytics-kpi__hint"><?= (int) ($summary['invoices_paid'] ?? 0) ?> paid</p>
            </article>
            <article class="analytics-kpi analytics-kpi--revenue">
                <p class="analytics-kpi__label">Paid total</p>
                <p class="analytics-kpi__value"><span class="analytics-kpi__currency">Σ</span><?= billo_e($summary['revenue_paid'] ?? '0.00') ?></p>
                <p class="analytics-kpi__hint">Sum of paid invoice totals</p>
            </article>
            <article class="analytics-kpi analytics-kpi--line">
                <p class="analytics-kpi__label">Line items</p>
                <p class="analytics-kpi__value"><?= (int) ($summary['invoice_line_items'] ?? 0) ?></p>
                <p class="analytics-kpi__hint">Detail rows platform-wide</p>
            </article>
        </div>

        <div class="analytics-panels">
            <section class="analytics-panel" aria-labelledby="pipe-heading">
                <div class="analytics-panel__head">
                    <h2 id="pipe-heading" class="analytics-panel__title">Invoice pipeline</h2>
                    <p class="analytics-panel__sub">Standard invoices by status</p>
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

            <section class="analytics-panel analytics-panel--table">
                <div class="analytics-panel__head">
                    <h2 class="analytics-panel__title">Leading organizations</h2>
                    <p class="analytics-panel__sub">By invoice count</p>
                </div>
                <div class="analytics-table-wrap">
                    <table class="analytics-table">
                        <thead>
                            <tr>
                                <th>Organization</th>
                                <th>Members</th>
                                <th>Invoices</th>
                                <th class="analytics-table__num">Paid total</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($top_orgs === []): ?>
                                <tr><td colspan="4" class="analytics-table__empty">No data yet.</td></tr>
                            <?php endif; ?>
                            <?php foreach ($top_orgs as $row): ?>
                                <tr>
                                    <td><strong><?= billo_e((string) ($row['name'] ?? '')) ?></strong></td>
                                    <td><?= (int) ($row['member_count'] ?? 0) ?></td>
                                    <td><?= (int) ($row['invoice_count'] ?? 0) ?></td>
                                    <td class="analytics-table__num"><?= billo_e(number_format((float) ($row['paid_total'] ?? 0), 2, '.', ',')) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </section>
        </div>

        <div class="analytics-charts-grid">
            <?php
            $chart_title = 'New users by month';
            $chart_labels = $trend_labels;
            $chart_values = $trends['new_users'] ?? [];
            $chart_format = 'int';
            $chart_accent = 'navy';
            include dirname(__DIR__) . '/partials/analytics_bar_chart.php';
            ?>
            <?php
            $chart_title = 'New organizations';
            $chart_labels = $trend_labels;
            $chart_values = $trends['new_orgs'] ?? [];
            $chart_format = 'int';
            $chart_accent = 'emerald';
            include dirname(__DIR__) . '/partials/analytics_bar_chart.php';
            ?>
            <?php
            $chart_title = 'Invoices created';
            $chart_labels = $trend_labels;
            $chart_values = $trends['new_invoices'] ?? [];
            $chart_format = 'int';
            $chart_accent = 'amber';
            include dirname(__DIR__) . '/partials/analytics_bar_chart.php';
            ?>
            <?php
            $chart_title = 'Paid amounts by month';
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
    <meta name="theme-color" content="#1E3A8A">
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
