<?php
declare(strict_types=1);

/** @var string $report_type */
/** @var list<mixed> $rows */
/** @var int $total */
/** @var int $page */
/** @var int $per_page */
/** @var int $total_pages */
/** @var string $q */
/** @var string $status */
/** @var int $organization_id */
/** @var string $from */
/** @var string $to */
/** @var array<string, string> $query_for_links */
/** @var string $user_name */
/** @var string $role */
/** @var bool $show_team_nav */
/** @var string $error */
/** @var string $success */

$error = $error ?? '';
$success = $success ?? '';
$title = 'Platform reports — billo';

$buildUrl = static function (array $base, int $p): string {
    $base['page'] = (string) $p;

    return billo_url('/system/reports?' . http_build_query($base));
};

$exportQuery = $query_for_links;
unset($exportQuery['page']);
$exportUrl = billo_url('/system/reports/export?' . http_build_query($exportQuery));

ob_start();
?>
<section class="app-dashboard">
    <?php
    $active = 'system-reports';
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
                <h1 class="page-head__title">Platform reports</h1>
            </div>
            <div class="page-head__actions">
                <a class="btn btn--secondary" href="<?= billo_e(billo_url('/system/analytics')) ?>">Full analytics</a>
                <a class="btn btn--primary" href="<?= billo_e($exportUrl) ?>">Export CSV</a>
            </div>
        </div>

        <form class="reports-toolbar" method="get" action="<?= billo_e(billo_url('/system/reports')) ?>">
            <div class="reports-toolbar__row">
                <label class="reports-field">
                    <span class="reports-field__label">Dataset</span>
                    <select name="type" class="reports-field__control" onchange="this.form.submit()">
                        <option value="organizations"<?= $report_type === 'organizations' ? ' selected' : '' ?>>Organizations</option>
                        <option value="invoices"<?= $report_type === 'invoices' ? ' selected' : '' ?>>Invoices</option>
                        <option value="users"<?= $report_type === 'users' ? ' selected' : '' ?>>Users</option>
                    </select>
                </label>
                <label class="reports-field">
                    <span class="reports-field__label">Rows per page</span>
                    <select name="per_page" class="reports-field__control" onchange="this.form.submit()">
                        <?php foreach ([10, 25, 50, 100] as $n): ?>
                            <option value="<?= $n ?>"<?= $per_page === $n ? ' selected' : '' ?>><?= $n ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
            </div>
            <?php if ($report_type === 'organizations' || $report_type === 'users'): ?>
                <label class="reports-field reports-field--grow">
                    <span class="reports-field__label">Search<?= $report_type === 'users' ? ' (email or name)' : ' (name or slug)' ?></span>
                    <input type="search" name="q" class="reports-field__control" value="<?= billo_e($q) ?>" placeholder="Filter…" autocomplete="off">
                </label>
            <?php else: ?>
                <div class="reports-toolbar__row reports-toolbar__row--wrap">
                    <label class="reports-field">
                        <span class="reports-field__label">Status</span>
                        <select name="status" class="reports-field__control">
                            <option value=""<?= $status === '' ? ' selected' : '' ?>>All</option>
                            <option value="draft"<?= $status === 'draft' ? ' selected' : '' ?>>Draft</option>
                            <option value="sent"<?= $status === 'sent' ? ' selected' : '' ?>>Sent</option>
                            <option value="paid"<?= $status === 'paid' ? ' selected' : '' ?>>Paid</option>
                            <option value="void"<?= $status === 'void' ? ' selected' : '' ?>>Void</option>
                        </select>
                    </label>
                    <label class="reports-field">
                        <span class="reports-field__label">Organization ID</span>
                        <input type="text" name="organization_id" class="reports-field__control" inputmode="numeric" value="<?= $organization_id > 0 ? (string) (int) $organization_id : '' ?>" placeholder="Any">
                    </label>
                    <label class="reports-field">
                        <span class="reports-field__label">Issue from</span>
                        <input type="date" name="from" class="reports-field__control" value="<?= billo_e($from) ?>">
                    </label>
                    <label class="reports-field">
                        <span class="reports-field__label">Issue to</span>
                        <input type="date" name="to" class="reports-field__control" value="<?= billo_e($to) ?>">
                    </label>
                </div>
            <?php endif; ?>
            <div class="reports-toolbar__actions">
                <button type="submit" class="btn btn--primary">Apply</button>
                <a class="btn btn--ghost" href="<?= billo_e(billo_url('/system/reports?type=' . urlencode($report_type))) ?>">Reset</a>
            </div>
        </form>

        <p class="reports-meta"><?= number_format((int) $total) ?> result<?= $total !== 1 ? 's' : '' ?> · page <?= (int) $page ?> of <?= (int) $total_pages ?></p>

        <div class="table-scroll">
            <?php if ($report_type === 'organizations'): ?>
                <table class="data-table data-table--comfortable">
                    <thead>
                        <tr>
                            <th class="num">ID</th>
                            <th>Name</th>
                            <th>Slug</th>
                            <th>Created</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($rows as $r): ?>
                            <?php if (!is_array($r)) {
                                continue;
                            } ?>
                            <tr>
                                <td class="num"><?= (int) ($r['id'] ?? 0) ?></td>
                                <td><?= billo_e((string) ($r['name'] ?? '')) ?></td>
                                <td><code><?= billo_e((string) ($r['slug'] ?? '')) ?></code></td>
                                <td><?= billo_e((string) ($r['created_at'] ?? '')) ?></td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (count($rows) === 0): ?>
                            <tr><td colspan="4" class="reports-empty">No rows match these filters.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            <?php elseif ($report_type === 'invoices'): ?>
                <table class="data-table data-table--comfortable">
                    <thead>
                        <tr>
                            <th class="num">ID</th>
                            <th class="num">Org</th>
                            <th>Organization</th>
                            <th>Invoice #</th>
                            <th>Status</th>
                            <th class="num">Total</th>
                            <th>Issued</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($rows as $r): ?>
                            <?php if (!is_array($r)) {
                                continue;
                            } ?>
                            <tr>
                                <td class="num"><?= (int) ($r['id'] ?? 0) ?></td>
                                <td class="num"><?= (int) ($r['organization_id'] ?? 0) ?></td>
                                <td><?= billo_e((string) ($r['org_name'] ?? '')) ?></td>
                                <td><code><?= billo_e((string) ($r['invoice_number'] ?? '')) ?></code></td>
                                <td><span class="chip"><?= billo_e((string) ($r['status'] ?? '')) ?></span></td>
                                <td class="num"><?= billo_e((string) ($r['total'] ?? '')) ?> <?= billo_e((string) ($r['currency'] ?? '')) ?></td>
                                <td><?= billo_e((string) ($r['issue_date'] ?? '')) ?></td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (count($rows) === 0): ?>
                            <tr><td colspan="7" class="reports-empty">No rows match these filters.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <table class="data-table data-table--comfortable">
                    <thead>
                        <tr>
                            <th class="num">ID</th>
                            <th>Email</th>
                            <th>Name</th>
                            <th>Platform operator</th>
                            <th>Created</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($rows as $r): ?>
                            <?php if (!is_array($r)) {
                                continue;
                            } ?>
                            <tr>
                                <td class="num"><?= (int) ($r['id'] ?? 0) ?></td>
                                <td><?= billo_e((string) ($r['email'] ?? '')) ?></td>
                                <td><?= billo_e((string) ($r['name'] ?? '')) ?></td>
                                <td><?= !empty($r['platform_operator']) ? 'yes' : '—' ?></td>
                                <td><?= billo_e((string) ($r['created_at'] ?? '')) ?></td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (count($rows) === 0): ?>
                            <tr><td colspan="5" class="reports-empty">No rows match these filters.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>

        <?php if ($total_pages > 1): ?>
            <nav class="pagination" aria-label="Report pages">
                <?php if ($page > 1): ?>
                    <a class="pagination__link" href="<?= billo_e($buildUrl($query_for_links, $page - 1)) ?>">Previous</a>
                <?php else: ?>
                    <span class="pagination__muted">Previous</span>
                <?php endif; ?>
                <span class="pagination__status"><?= (int) $page ?> / <?= (int) $total_pages ?></span>
                <?php if ($page < $total_pages): ?>
                    <a class="pagination__link" href="<?= billo_e($buildUrl($query_for_links, $page + 1)) ?>">Next</a>
                <?php else: ?>
                    <span class="pagination__muted">Next</span>
                <?php endif; ?>
            </nav>
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
