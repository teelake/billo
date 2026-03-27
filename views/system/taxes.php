<?php
declare(strict_types=1);

use App\Core\Csrf;

/** @var list<array<string, mixed>> $tax_rows */
/** @var bool $table_missing */
/** @var string $user_name */
/** @var string $role */
/** @var bool $show_team_nav */

$error = $error ?? '';
$success = $success ?? '';
$rows = isset($tax_rows) && is_array($tax_rows) ? $tax_rows : [];
$tableMissing = !empty($table_missing);
$title = 'Tax templates — billo';
ob_start();
?>
<section class="app-dashboard">
    <?php
    $active = 'system-taxes';
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
                <h1 class="page-head__title">Tax templates</h1>
                <p class="page-head__lead">Platform-wide tax definitions. The <strong>VAT percentage</strong> is the first <em>active additive</em> row below (typically “VAT (standard)”) and is the only place that rate is set—tenants cannot change the %, only whether VAT applies on each invoice.</p>
            </div>
            <a class="btn btn--secondary" href="<?= billo_e(billo_url('/system')) ?>">System</a>
        </div>

        <?php if ($tableMissing): ?>
            <div class="welcome-card">
                <p>The <code>tax_configs</code> table is missing. Run migration <code>014_invoice_document_tax_module.sql</code> (or apply the full schema), then reload this page.</p>
            </div>
        <?php else: ?>
            <div class="welcome-card" style="margin-bottom:1.25rem">
                <p class="eyebrow" style="margin:0 0 0.5rem">Platform VAT rate</p>
                <p class="hint" style="margin:0">Keep exactly one primary <strong>additive</strong> template active for document VAT unless you intentionally use competing rates. The app uses the lowest sort order, then smallest ID.</p>
            </div>
            <div class="welcome-card" data-billo-filter-table style="overflow-x:auto">
                <div style="padding:0.75rem 1rem;border-bottom:1px solid var(--color-border, #e2e8f0)">
                    <label class="label" for="taxes-table-filter" style="font-size:0.85rem">Filter rows</label>
                    <input type="search" id="taxes-table-filter" class="input input--sm" data-billo-filter-input placeholder="Search by ID, name, type…" autocomplete="off" style="max-width:24rem">
                </div>
                <table class="data-table data-table--comfortable">
                    <thead>
                    <tr>
                        <th class="num">ID</th>
                        <th>Name</th>
                        <th>Type</th>
                        <th class="num">Rate %</th>
                        <th>Active</th>
                        <th>Save</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($rows as $r): ?>
                        <?php
                        if (!is_array($r)) {
                            continue;
                        }
                        $tid = (int) ($r['id'] ?? 0);
                        if ($tid <= 0) {
                            continue;
                        }
                        $nm = (string) ($r['name'] ?? '');
                        $tp = (string) ($r['type'] ?? 'additive');
                        $rt = (float) ($r['rate'] ?? 0);
                        $on = !empty($r['is_active']);
                        $searchBlob = strtolower(implode(' ', [(string) $tid, $nm, $tp]));
                        ?>
                        <tr data-billo-search="<?= billo_e($searchBlob) ?>">
                            <td colspan="6" style="padding:0;border:none">
                                <form method="post" action="<?= billo_e(billo_url('/system/taxes')) ?>" class="config-form config-form--inline" style="display:table;width:100%;table-layout:fixed;margin:0">
                                    <input type="hidden" name="_csrf" value="<?= billo_e(Csrf::token()) ?>">
                                    <input type="hidden" name="tax_action" value="update">
                                    <input type="hidden" name="id" value="<?= $tid ?>">
                                    <div style="display:table-row">
                                        <div style="display:table-cell;padding:.5rem;vertical-align:middle;width:4%" class="num"><strong><?= $tid ?></strong></div>
                                        <div style="display:table-cell;padding:.5rem;vertical-align:middle;width:24%">
                                            <label class="sr-only" for="tax-name-<?= $tid ?>">Name</label>
                                            <input class="input input--sm" id="tax-name-<?= $tid ?>" name="name" value="<?= billo_e($nm) ?>" maxlength="120">
                                        </div>
                                        <div style="display:table-cell;padding:.5rem;vertical-align:middle;width:16%">
                                            <label class="sr-only" for="tax-type-<?= $tid ?>">Type</label>
                                            <select class="input input--sm" id="tax-type-<?= $tid ?>" name="type">
                                                <option value="additive"<?= $tp === 'additive' ? ' selected' : '' ?>>Additive (VAT)</option>
                                                <option value="deductive"<?= $tp === 'deductive' ? ' selected' : '' ?>>Deductive (WHT)</option>
                                            </select>
                                        </div>
                                        <div style="display:table-cell;padding:.5rem;vertical-align:middle;width:12%" class="num">
                                            <label class="sr-only" for="tax-rate-<?= $tid ?>">Rate</label>
                                            <input class="input input--sm" id="tax-rate-<?= $tid ?>" name="rate" inputmode="decimal" value="<?= billo_e(rtrim(rtrim(sprintf('%.4f', $rt), '0'), '.') ?: '0') ?>">
                                        </div>
                                        <div style="display:table-cell;padding:.5rem;vertical-align:middle;width:14%">
                                            <label><input type="checkbox" name="is_active" value="1"<?= $on ? ' checked' : '' ?>> Active</label>
                                        </div>
                                        <div style="display:table-cell;padding:.5rem;vertical-align:middle;width:12%">
                                            <button type="submit" class="btn btn--secondary btn--sm">Update</button>
                                        </div>
                                    </div>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <div class="welcome-card" style="margin-top:1.25rem">
                <h2 class="invoice-detail-card__h" style="margin-top:0">Add tax template</h2>
                <form class="form form--spaced" method="post" action="<?= billo_e(billo_url('/system/taxes')) ?>" style="max-width:32rem">
                    <input type="hidden" name="_csrf" value="<?= billo_e(Csrf::token()) ?>">
                    <input type="hidden" name="tax_action" value="create">
                    <div class="field">
                        <label class="label" for="tax-create-name">Name</label>
                        <input class="input" id="tax-create-name" name="name" maxlength="120" required placeholder="e.g. WHT — Professional services">
                    </div>
                    <div class="field">
                        <label class="label" for="tax-create-type">Type</label>
                        <select class="input" id="tax-create-type" name="type">
                            <option value="additive">Additive (VAT)</option>
                            <option value="deductive">Deductive (WHT)</option>
                        </select>
                    </div>
                    <div class="field">
                        <label class="label" for="tax-create-rate">Rate (%)</label>
                        <input class="input" id="tax-create-rate" name="rate" inputmode="decimal" required placeholder="7.5">
                    </div>
                    <div class="field">
                        <label><input type="checkbox" name="is_active" value="1" checked> Active</label>
                    </div>
                    <button type="submit" class="btn btn--primary">Create</button>
                </form>
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
