<?php
declare(strict_types=1);

use App\Core\Csrf;

/** @var list<array<string, mixed>> $plan_rows */
/** @var array<int, list<array<string, mixed>>> $plan_items_by_plan */
/** @var bool $table_missing */
/** @var string $user_name */
/** @var string $role */
/** @var bool $show_team_nav */

$error = $error ?? '';
$success = $success ?? '';
$rows = isset($plan_rows) && is_array($plan_rows) ? $plan_rows : [];
$itemsByPlan = isset($plan_items_by_plan) && is_array($plan_items_by_plan) ? $plan_items_by_plan : [];
$tableMissing = !empty($table_missing);
$title = 'Subscription plans — billo';
ob_start();
?>
<section class="app-dashboard">
    <?php
    $active = 'system-plans';
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

        <div class="welcome-card" style="margin-bottom:1.25rem;display:grid;grid-template-columns:repeat(auto-fit,minmax(16rem,1fr));gap:1.25rem">
            <div>
                <p class="eyebrow" style="margin:0 0 0.5rem">Operator checklist — non‑taxable / informal plan</p>
                <ul class="hint" style="margin:0;padding-left:1.15rem">
                    <li style="margin:0.25rem 0"><strong>Plans</strong>: leave <strong>NRS</strong> off for this plan; optional plan items (“Core invoicing”, no filing sync).</li>
                    <li style="margin:0.25rem 0"><strong>Tax templates</strong>: VAT % still lives here if other plans need it; tenants can turn off <strong>Tax on invoices</strong> and VAT defaults in Business settings.</li>
                    <li style="margin:0.25rem 0"><strong>Integrations</strong>: platform NRS URL is optional; tenants on this plan will not get an NRS toggle.</li>
                </ul>
            </div>
            <div>
                <p class="eyebrow" style="margin:0 0 0.5rem">Operator checklist — taxable NRS plan</p>
                <ul class="hint" style="margin:0;padding-left:1.15rem">
                    <li style="margin:0.25rem 0"><strong>Tax templates</strong>: set the <strong>additive</strong> VAT row to the legal % (this is the only VAT figure tenants cannot override).</li>
                    <li style="margin:0.25rem 0"><strong>NRS integration</strong>: configure base URL, path, and token under <a href="<?= billo_e(billo_url('/system/integrations')) ?>">NRS integration</a>.</li>
                    <li style="margin:0.25rem 0"><strong>This plan</strong>: enable <strong>NRS → Allow</strong>; turn on <strong>TIN</strong> if orgs must have a tax ID before NRS stays on.</li>
                    <li style="margin:0.25rem 0"><strong>Plan items</strong>: bullets that mention NRS / compliance help tenants choose the right tier.</li>
                </ul>
            </div>
        </div>

        <div class="page-head">
            <div>
                <p class="eyebrow eyebrow--dark">platform operator</p>
                <h1 class="page-head__title">Subscription plans</h1>
                <p class="page-head__lead">What tenants see on <strong>Plans &amp; billing</strong>. Enable <strong>NRS</strong> per plan, edit marketing bullets, and configure the shared API under <a href="<?= billo_e(billo_url('/system/integrations')) ?>">Integrations</a>.</p>
            </div>
            <div class="page-head__actions">
                <a class="btn btn--secondary" href="<?= billo_e(billo_url('/system/integrations')) ?>">NRS</a>
                <a class="btn btn--secondary" href="<?= billo_e(billo_url('/system')) ?>">System</a>
            </div>
        </div>

        <?php if ($tableMissing): ?>
            <div class="welcome-card">
                <p>The <code>subscription_plans</code> table is missing. Run migrations <code>015_plans_nrs_subscriptions.sql</code> and <code>016_plan_items_platform_nrs.sql</code>, then reload.</p>
            </div>
        <?php else: ?>
            <form class="config-form" method="post" action="<?= billo_e(billo_url('/system/plans')) ?>">
                <input type="hidden" name="_csrf" value="<?= billo_e(Csrf::token()) ?>">

                <fieldset class="config-form__section welcome-card">
                    <legend class="config-form__legend">Existing plans</legend>
                    <div class="table-wrap" style="overflow-x:auto">
                        <table class="data-table data-table--comfortable">
                            <thead>
                            <tr>
                                <th class="num">ID</th>
                                <th>Slug</th>
                                <th>Name</th>
                                <th>Price</th>
                                <th>Interval</th>
                                <th class="num">Sort</th>
                                <th>Active</th>
                                <th>NRS</th>
                                <th>TIN</th>
                            </tr>
                            </thead>
                            <tbody>
                            <?php foreach ($rows as $r): ?>
                                <?php
                                if (!is_array($r)) {
                                    continue;
                                }
                                $pid = (int) ($r['id'] ?? 0);
                                if ($pid <= 0) {
                                    continue;
                                }
                                $slug = (string) ($r['slug'] ?? '');
                                $nm = (string) ($r['name'] ?? '');
                                $desc = (string) ($r['description'] ?? '');
                                $price = (float) ($r['price_amount'] ?? 0);
                                $cur = (string) ($r['currency'] ?? 'NGN');
                                $intv = (string) ($r['billing_interval'] ?? 'monthly');
                                $sort = (int) ($r['sort_order'] ?? 0);
                                $on = !empty($r['is_active']);
                                $nrsOk = !empty($r['nrs_integration_allowed']);
                                $nrsTax = !empty($r['nrs_requires_organization_tax_id']);
                                ?>
                                <tr>
                                    <td class="num"><?= $pid ?></td>
                                    <td>
                                        <label class="sr-only" for="plan-slug-<?= $pid ?>">Slug</label>
                                        <input class="input input--sm" id="plan-slug-<?= $pid ?>" name="plan_update[<?= $pid ?>][slug]" value="<?= billo_e($slug) ?>" maxlength="64" required pattern="[a-z0-9][a-z0-9-]*">
                                    </td>
                                    <td>
                                        <label class="sr-only" for="plan-name-<?= $pid ?>">Name</label>
                                        <input class="input input--sm" id="plan-name-<?= $pid ?>" name="plan_update[<?= $pid ?>][name]" value="<?= billo_e($nm) ?>" maxlength="200" required>
                                    </td>
                                    <td>
                                        <div class="field-grid" style="grid-template-columns:1fr 4rem;gap:0.35rem;margin:0">
                                            <label class="sr-only" for="plan-price-<?= $pid ?>">Price</label>
                                            <input class="input input--sm" id="plan-price-<?= $pid ?>" name="plan_update[<?= $pid ?>][price_amount]" inputmode="decimal" value="<?= billo_e(rtrim(rtrim(sprintf('%.2f', $price), '0'), '.') ?: '0') ?>">
                                            <label class="sr-only" for="plan-cur-<?= $pid ?>">Currency</label>
                                            <input class="input input--sm" id="plan-cur-<?= $pid ?>" name="plan_update[<?= $pid ?>][currency]" maxlength="3" value="<?= billo_e($cur) ?>">
                                        </div>
                                    </td>
                                    <td>
                                        <label class="sr-only" for="plan-intv-<?= $pid ?>">Billing interval</label>
                                        <select class="input input--sm" id="plan-intv-<?= $pid ?>" name="plan_update[<?= $pid ?>][billing_interval]">
                                            <option value="monthly"<?= $intv === 'monthly' ? ' selected' : '' ?>>monthly</option>
                                            <option value="yearly"<?= $intv === 'yearly' ? ' selected' : '' ?>>yearly</option>
                                            <option value="lifetime"<?= $intv === 'lifetime' ? ' selected' : '' ?>>lifetime</option>
                                        </select>
                                    </td>
                                    <td class="num">
                                        <label class="sr-only" for="plan-sort-<?= $pid ?>">Sort</label>
                                        <input class="input input--sm" id="plan-sort-<?= $pid ?>" name="plan_update[<?= $pid ?>][sort_order]" inputmode="numeric" value="<?= $sort ?>">
                                    </td>
                                    <td>
                                        <input type="hidden" name="plan_update[<?= $pid ?>][is_active]" value="0">
                                        <label><input type="checkbox" name="plan_update[<?= $pid ?>][is_active]" value="1"<?= $on ? ' checked' : '' ?>> On</label>
                                    </td>
                                    <td>
                                        <input type="hidden" name="plan_update[<?= $pid ?>][nrs_integration_allowed]" value="0">
                                        <label><input type="checkbox" name="plan_update[<?= $pid ?>][nrs_integration_allowed]" value="1"<?= $nrsOk ? ' checked' : '' ?>> Allow</label>
                                    </td>
                                    <td>
                                        <input type="hidden" name="plan_update[<?= $pid ?>][nrs_requires_organization_tax_id]" value="0">
                                        <label><input type="checkbox" name="plan_update[<?= $pid ?>][nrs_requires_organization_tax_id]" value="1"<?= $nrsTax ? ' checked' : '' ?>> Req.</label>
                                    </td>
                                </tr>
                                <tr>
                                    <td colspan="9" style="padding-top:0;border-top:none">
                                        <label class="sr-only" for="plan-desc-<?= $pid ?>">Description</label>
                                        <input class="input input--sm" id="plan-desc-<?= $pid ?>" name="plan_update[<?= $pid ?>][description]" maxlength="2000" value="<?= billo_e($desc) ?>" placeholder="Short description for billing page">
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <p class="hint" style="margin:0.75rem 0 0"><strong>TIN</strong>: when checked, workspaces on this plan must save a tax ID before NRS sync can stay on.</p>
                    <button type="submit" class="btn btn--primary" style="margin-top:1rem">Save plan changes</button>
                </fieldset>

                <fieldset class="config-form__section welcome-card" style="margin-top:1.25rem">
                    <legend class="config-form__legend">Add plan</legend>
                    <div class="config-form__grid">
                        <label class="config-form__field">
                            <span class="config-form__label">Slug</span>
                            <input type="text" name="plan_create[slug]" class="input" maxlength="64" pattern="[a-z0-9][a-z0-9-]*" placeholder="e.g. growth">
                        </label>
                        <label class="config-form__field">
                            <span class="config-form__label">Name</span>
                            <input type="text" name="plan_create[name]" class="input" maxlength="200" placeholder="Growth">
                        </label>
                        <label class="config-form__field config-form__field--full">
                            <span class="config-form__label">Description</span>
                            <textarea name="plan_create[description]" class="input" rows="2" maxlength="2000"></textarea>
                        </label>
                        <label class="config-form__field">
                            <span class="config-form__label">Price</span>
                            <input type="text" name="plan_create[price_amount]" class="input" inputmode="decimal" value="0" placeholder="0 for free">
                        </label>
                        <label class="config-form__field">
                            <span class="config-form__label">Currency</span>
                            <input type="text" name="plan_create[currency]" class="input" maxlength="3" value="NGN">
                        </label>
                        <label class="config-form__field">
                            <span class="config-form__label">Interval</span>
                            <select name="plan_create[billing_interval]" class="input">
                                <option value="monthly">monthly</option>
                                <option value="yearly">yearly</option>
                                <option value="lifetime">lifetime</option>
                            </select>
                        </label>
                        <label class="config-form__field">
                            <span class="config-form__label">Sort order</span>
                            <input type="number" name="plan_create[sort_order]" class="input" value="10">
                        </label>
                        <label class="config-form__field">
                            <span class="config-form__label">Active</span>
                            <label><input type="checkbox" name="plan_create[is_active]" value="1" checked> Listed for tenants</label>
                        </label>
                        <label class="config-form__field">
                            <span class="config-form__label">NRS integration</span>
                            <input type="hidden" name="plan_create[nrs_integration_allowed]" value="0">
                            <label><input type="checkbox" name="plan_create[nrs_integration_allowed]" value="1"> Allow for this plan</label>
                        </label>
                        <label class="config-form__field">
                            <span class="config-form__label">Require tax ID</span>
                            <input type="hidden" name="plan_create[nrs_requires_organization_tax_id]" value="0">
                            <label><input type="checkbox" name="plan_create[nrs_requires_organization_tax_id]" value="1"> Require org TIN for NRS</label>
                        </label>
                    </div>
                    <button type="submit" class="btn btn--secondary">Create plan</button>
                </fieldset>
            </form>

            <?php if ($rows !== []): ?>
            <form class="config-form" method="post" action="<?= billo_e(billo_url('/system/plan-items')) ?>" style="margin-top:1.5rem">
                <input type="hidden" name="_csrf" value="<?= billo_e(Csrf::token()) ?>">

                <fieldset class="config-form__section welcome-card">
                    <legend class="config-form__legend">Plan marketing bullets</legend>
                    <p class="hint" style="margin:0 0 1rem">These lines appear as extras on <strong>Plans &amp; billing</strong>. Leave “Add bullet” fields empty to skip creating new rows.</p>

                    <?php foreach ($rows as $pr): ?>
                        <?php
                        if (!is_array($pr)) {
                            continue;
                        }
                        $ppid = (int) ($pr['id'] ?? 0);
                        if ($ppid <= 0) {
                            continue;
                        }
                        $pn = (string) ($pr['name'] ?? 'Plan');
                        $ps = (string) ($pr['slug'] ?? '');
                        $pItems = $itemsByPlan[$ppid] ?? [];
                        ?>
                        <div class="welcome-card" style="margin:1rem 0;padding:1rem">
                            <p class="eyebrow" style="margin:0 0 0.5rem"><?= billo_e($pn) ?> <span class="hint">(<?= billo_e($ps) ?>)</span></p>
                            <?php if ($pItems !== []): ?>
                                <div class="table-wrap" style="overflow-x:auto;margin-bottom:0.75rem">
                                    <table class="data-table data-table--comfortable">
                                        <thead>
                                        <tr>
                                            <th class="num">ID</th>
                                            <th>Label</th>
                                            <th>Detail</th>
                                            <th class="num">Sort</th>
                                            <th>Del</th>
                                        </tr>
                                        </thead>
                                        <tbody>
                                        <?php foreach ($pItems as $it): ?>
                                            <?php
                                            if (!is_array($it)) {
                                                continue;
                                            }
                                            $iid = (int) ($it['id'] ?? 0);
                                            if ($iid <= 0) {
                                                continue;
                                            }
                                            $ilab = (string) ($it['label'] ?? '');
                                            $idet = (string) ($it['detail'] ?? '');
                                            $isort = (int) ($it['sort_order'] ?? 0);
                                            ?>
                                            <tr>
                                                <td class="num"><?= $iid ?></td>
                                                <td>
                                                    <input class="input input--sm" name="plan_item_update[<?= $iid ?>][label]" value="<?= billo_e($ilab) ?>" maxlength="200" required>
                                                </td>
                                                <td>
                                                    <input class="input input--sm" name="plan_item_update[<?= $iid ?>][detail]" value="<?= billo_e($idet) ?>" maxlength="2000">
                                                </td>
                                                <td class="num">
                                                    <input class="input input--sm" name="plan_item_update[<?= $iid ?>][sort_order]" inputmode="numeric" value="<?= $isort ?>">
                                                </td>
                                                <td>
                                                    <label><input type="checkbox" name="plan_item_delete[]" value="<?= $iid ?>"> </label>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php endif; ?>
                            <p style="margin:0 0 0.35rem;font-weight:600">Add bullet</p>
                            <div class="field-grid" style="grid-template-columns:1fr 1fr 5rem;gap:0.5rem;align-items:end">
                                <label class="field" style="margin:0">
                                    <span class="label">Label</span>
                                    <input class="input input--sm" name="plan_item_create[<?= $ppid ?>][label]" maxlength="200" placeholder="e.g. Unlimited invoices">
                                </label>
                                <label class="field" style="margin:0">
                                    <span class="label">Detail (optional)</span>
                                    <input class="input input--sm" name="plan_item_create[<?= $ppid ?>][detail]" maxlength="2000">
                                </label>
                                <label class="field" style="margin:0">
                                    <span class="label">Sort</span>
                                    <input class="input input--sm" name="plan_item_create[<?= $ppid ?>][sort_order]" inputmode="numeric" value="0">
                                </label>
                            </div>
                        </div>
                    <?php endforeach; ?>

                    <button type="submit" class="btn btn--primary" style="margin-top:1rem">Save plan items</button>
                </fieldset>
            </form>
            <?php endif; ?>
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
