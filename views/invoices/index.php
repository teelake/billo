<?php
declare(strict_types=1);

/** @var list<array<string, mixed>> $invoices */
/** @var bool $can_manage */
/** @var string $user_name */
/** @var string $role */
/** @var bool $show_team_nav */
/** @var string $error */
/** @var string $success */

$error = $error ?? '';
$success = $success ?? '';
$title = 'Invoices — billo';
ob_start();
?>
<section class="app-dashboard">
    <?php
    $active = 'invoices';
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
                <h1 class="page-head__title">Invoices</h1>
                <p class="page-head__lead">Draft, send, and track payments—scoped to your organization.</p>
            </div>
            <?php if ($can_manage): ?>
                <a class="btn btn--primary" href="<?= billo_e(billo_url('/invoices/create')) ?>">New invoice</a>
            <?php endif; ?>
        </div>

        <?php if (!$can_manage): ?>
            <p class="hint-banner">You have read-only access. Ask an owner, admin, or member to create or change invoices.</p>
        <?php endif; ?>

        <?php if (count($invoices) === 0): ?>
            <div class="welcome-card">
                <p class="welcome-card__text">No invoices yet.<?= $can_manage ? ' Create a draft and add line items when you’re ready.' : '' ?></p>
            </div>
        <?php else: ?>
            <div class="welcome-card" data-billo-filter-table style="padding:0;overflow:hidden">
                <div style="padding:0.75rem 1rem;border-bottom:1px solid var(--color-border, #e2e8f0)">
                    <label class="label" for="invoices-table-filter" style="font-size:0.85rem">Filter rows</label>
                    <input type="search" id="invoices-table-filter" class="input input--sm" data-billo-filter-input placeholder="Search ID, number, client, status…" autocomplete="off" style="max-width:24rem">
                </div>
                <table class="data-table data-table--comfortable">
                    <thead>
                    <tr>
                        <th class="num">ID</th>
                        <th>Number</th>
                        <th>Type</th>
                        <th>Client</th>
                        <th>Status</th>
                        <th>Issue date</th>
                        <th class="num">Total</th>
                        <th></th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($invoices as $inv): ?>
                        <?php
                        $iid = (int) ($inv['id'] ?? 0);
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
                        $typeLabel = $ik === 'credit_note' ? 'Credit' : 'Invoice';
                        $invNo = (string) ($inv['invoice_number'] ?? '');
                        $issueD = (string) ($inv['issue_date'] ?? '');
                        $searchBlob = strtolower(implode(' ', array_filter([
                            (string) $iid,
                            $invNo,
                            $typeLabel,
                            $clientLabel,
                            $status,
                            $issueD,
                        ])));
                        ?>
                        <tr data-billo-search="<?= billo_e($searchBlob) ?>">
                            <td class="num"><?= $iid ?></td>
                            <td><strong><?= billo_e($invNo) ?></strong></td>
                            <td><span class="status-pill status-pill--<?= $ik === 'credit_note' ? 'draft' : 'sent' ?>"><?= billo_e($typeLabel) ?></span></td>
                            <td><?= billo_e($clientLabel) ?></td>
                            <td><span class="status-pill status-pill--<?= billo_e($status) ?>"><?= billo_e($status) ?></span></td>
                            <td><?= billo_e((string) ($inv['issue_date'] ?? '')) ?></td>
                            <td class="num"><?= billo_e($cur) ?>&nbsp;<?= billo_e(number_format((float) $total, 2)) ?></td>
                            <td class="data-table__actions">
                                <a class="btn btn--ghost btn--sm" href="<?= billo_e(billo_url('/invoices/show?id=' . (int) ($inv['id'] ?? 0))) ?>">View</a>
                                <?php if ($can_manage && $status === 'draft'): ?>
                                    <a class="btn btn--ghost btn--sm" href="<?= billo_e(billo_url('/invoices/edit?id=' . (int) ($inv['id'] ?? 0))) ?>">Edit</a>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
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
