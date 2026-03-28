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
/** @var bool $operator_without_tenant */
/** @var array<string, mixed>|null $platform_summary */
/** @var array<string, mixed>|null $tenant_summary */
/** @var array<string, int>|null $invoice_status_breakdown */
/** @var list<array<string, mixed>> $recent_invoices */
/** @var bool $can_manage_invoices */
/** @var string $error */
/** @var string $success */
$can_manage_clients = !empty($can_manage_clients);
$can_manage_invoices = !empty($can_manage_invoices);
$is_platform_operator = !empty($is_platform_operator);
$operator_without_tenant = !empty($operator_without_tenant);
$platform_summary = is_array($platform_summary ?? null) ? $platform_summary : null;
$tenant_summary = is_array($tenant_summary ?? null) ? $tenant_summary : null;
$invoice_status_breakdown = is_array($invoice_status_breakdown ?? null) ? $invoice_status_breakdown : null;
$recent_invoices = is_array($recent_invoices ?? null) ? $recent_invoices : [];

$orgName = is_array($organization) ? (string) ($organization['name'] ?? 'Your organization') : 'Your organization';
$firstName = trim(explode(' ', (string) $user_name, 2)[0] ?? '');
$greetName = $firstName !== '' ? $firstName : (trim((string) $user_email) !== '' ? explode('@', (string) $user_email, 2)[0] : 'there');
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

        <?php if ($is_platform_operator && $platform_summary !== null && ($operator_without_tenant || (function_exists('billo_app_nav_mode') && billo_app_nav_mode() === 'platform'))): ?>
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

        <div class="dashboard-hero">
            <div class="dashboard-hero__main">
                <p class="eyebrow eyebrow--dark"><?= $operator_without_tenant ? 'Platform' : 'Dashboard' ?></p>
                <h1 class="dashboard-hero__title">
                    <?php if ($operator_without_tenant): ?>
                        Hi <?= billo_e($greetName) ?>, you’re signed in as a platform operator
                    <?php else: ?>
                        Hi <?= billo_e($greetName) ?>, here’s <?= billo_e($orgName) ?>
                    <?php endif; ?>
                </h1>
                <p class="dashboard-hero__meta">
                    Signed in as <?= billo_e($user_email) ?>
                    <span class="dashboard-hero__dot" aria-hidden="true">·</span>
                    <span class="capitalize"><?= billo_e($role) ?></span>
                </p>
            </div>
            <?php if (!$operator_without_tenant): ?>
                <div class="dashboard-hero__actions">
                    <a class="btn btn--primary" href="<?= billo_e(billo_url('/invoices')) ?>">All invoices</a>
                    <a class="btn btn--secondary" href="<?= billo_e(billo_url('/clients')) ?>">Clients</a>
                    <?php if ($can_manage_clients): ?>
                        <a class="btn btn--secondary" href="<?= billo_e(billo_url('/invoices/create')) ?>">New invoice</a>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>

        <?php if (!$operator_without_tenant && $tenant_summary !== null): ?>
            <div class="dashboard-pulse tenant-overview">
                <div class="tenant-overview__head dashboard-pulse__head">
                    <div>
                        <h2 class="tenant-overview__title">Business pulse</h2>
                        <p class="dashboard-pulse__lede">Counts and money on the line—prioritize what to send or chase next.</p>
                    </div>
                    <a class="btn btn--secondary btn--sm" href="<?= billo_e(billo_url('/analytics')) ?>">Full analytics</a>
                </div>
                <?php if ($invoice_status_breakdown !== null): ?>
                    <div class="dashboard-pipeline" role="group" aria-label="Invoice status counts">
                        <?php
                        $pipe = [
                            'draft' => 'Drafts',
                            'sent' => 'Awaiting payment',
                            'paid' => 'Paid',
                            'void' => 'Void',
                        ];
                        foreach ($pipe as $key => $lab):
                            $n = (int) ($invoice_status_breakdown[$key] ?? 0);
                            ?>
                            <div class="dashboard-pipeline__item">
                                <span class="dashboard-pipeline__n"><?= $n ?></span>
                                <span class="dashboard-pipeline__lab"><?= billo_e($lab) ?></span>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
                <div class="tenant-stat-grid dashboard-pulse__grid">
                    <div class="tenant-stat">
                        <span class="tenant-stat__val"><?= (int) ($tenant_summary['clients'] ?? 0) ?></span>
                        <span class="tenant-stat__lab">Active clients</span>
                    </div>
                    <div class="tenant-stat">
                        <span class="tenant-stat__val"><?= (int) ($tenant_summary['invoices'] ?? 0) ?></span>
                        <span class="tenant-stat__lab">Invoices issued</span>
                    </div>
                    <div class="tenant-stat">
                        <span class="tenant-stat__val"><?= (int) ($tenant_summary['invoices_paid'] ?? 0) ?></span>
                        <span class="tenant-stat__lab">Paid (count)</span>
                    </div>
                    <div class="tenant-stat tenant-stat--accent">
                        <span class="tenant-stat__val"><?= billo_e((string) ($tenant_summary['revenue_paid'] ?? '0.00')) ?></span>
                        <span class="tenant-stat__lab">Cash in (paid total)</span>
                    </div>
                    <div class="tenant-stat tenant-stat--warn">
                        <span class="tenant-stat__val"><?= billo_e((string) ($tenant_summary['sent_value'] ?? '0.00')) ?></span>
                        <span class="tenant-stat__lab">Outstanding (sent)</span>
                    </div>
                    <div class="tenant-stat">
                        <span class="tenant-stat__val"><?= billo_e((string) ($tenant_summary['draft_value'] ?? '0.00')) ?></span>
                        <span class="tenant-stat__lab">Draft pipeline</span>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <?php if (!$operator_without_tenant): ?>
        <section class="dashboard-recent" aria-labelledby="recent-invoices-heading">
            <div class="dashboard-recent__head">
                <div>
                    <h2 id="recent-invoices-heading" class="dashboard-recent__title">Recent invoices</h2>
                    <p class="dashboard-recent__sub">Newest activity—open a record or jump to the full list.</p>
                </div>
                <a class="btn btn--ghost btn--sm" href="<?= billo_e(billo_url('/invoices')) ?>">View all</a>
            </div>
            <?php if (!$can_manage_invoices): ?>
                <p class="hint-banner" style="margin-bottom:1rem">Read-only: you can view invoices but not edit them.</p>
            <?php endif; ?>
            <?php if (count($recent_invoices) === 0): ?>
                <div class="dashboard-recent__empty">
                    <p class="dashboard-recent__empty-text">No invoices yet. When you create and send them, they’ll show up here for quick follow-ups.</p>
                    <?php if ($can_manage_invoices): ?>
                        <a class="btn btn--primary" href="<?= billo_e(billo_url('/invoices/create')) ?>">Create first invoice</a>
                    <?php endif; ?>
                </div>
            <?php else: ?>
                <div class="table-scroll dashboard-recent__table">
                    <table class="data-table data-table--dashboard">
                        <thead>
                        <tr>
                            <th scope="col">Document</th>
                            <th scope="col">Client</th>
                            <th scope="col">Status</th>
                            <th scope="col">Due</th>
                            <th scope="col" class="num">Amount</th>
                            <th scope="col"><span class="sr-only">Actions</span></th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($recent_invoices as $inv): ?>
                            <?php
                            $status = (string) ($inv['status'] ?? '');
                            $cid = (int) ($inv['client_id'] ?? 0);
                            $clientLabel = '—';
                            if ($cid > 0) {
                                $cn = (string) ($inv['client_company'] ?? '');
                                $nm = (string) ($inv['client_name'] ?? '');
                                $clientLabel = $cn !== '' ? $cn : $nm;
                            }
                            $cur = (string) ($inv['currency'] ?? 'NGN');
                            $total = $inv['total'] ?? '0';
                            $ik = (string) ($inv['invoice_kind'] ?? 'invoice');
                            $num = (string) ($inv['invoice_number'] ?? '');
                            $docLabel = $ik === 'credit_note' ? 'Credit ' . $num : $num;
                            $dueRaw = (string) ($inv['due_date'] ?? '');
                            $dueLabel = $dueRaw !== '' && $dueRaw !== '0000-00-00' ? $dueRaw : '—';
                            $dueClass = '';
                            if ($status === 'sent' && $dueRaw !== '' && $dueRaw !== '0000-00-00') {
                                try {
                                    $dueDt = new \DateTimeImmutable($dueRaw);
                                    if ($dueDt < new \DateTimeImmutable('today')) {
                                        $dueClass = ' dashboard-recent__due--overdue';
                                    }
                                } catch (\Throwable) {
                                }
                            }
                            ?>
                            <tr>
                                <td><strong><?= billo_e($docLabel) ?></strong></td>
                                <td><?= billo_e($clientLabel) ?></td>
                                <td><span class="status-pill status-pill--<?= billo_e($status) ?>"><?= billo_e($status) ?></span></td>
                                <td class="dashboard-recent__due<?= $dueClass ?>"><?= billo_e($dueLabel) ?></td>
                                <td class="num"><?= billo_e($cur) ?>&nbsp;<?= billo_e(number_format((float) $total, 2)) ?></td>
                                <td class="data-table__actions">
                                    <a class="btn btn--ghost btn--sm" href="<?= billo_e(billo_url('/invoices/show?id=' . (int) ($inv['id'] ?? 0))) ?>">Open</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </section>
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
