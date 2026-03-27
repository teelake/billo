<?php
declare(strict_types=1);

use App\Core\Csrf;

/** @var list<array<string, mixed>> $clients */
/** @var bool $can_manage */
/** @var string $user_name */
/** @var string $role */
/** @var bool $show_team_nav */
/** @var string $error */
/** @var string $success */

$error = $error ?? '';
$success = $success ?? '';
$title = 'Clients — billo';
ob_start();
?>
<section class="app-dashboard">
    <?php
    $active = 'clients';
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
                <h1 class="page-head__title">Clients</h1>
                <p class="page-head__lead">People and businesses you invoice—scoped to your organization.</p>
            </div>
            <?php if ($can_manage): ?>
                <a class="btn btn--primary" href="<?= billo_e(billo_url('/clients/create')) ?>">Add client</a>
            <?php endif; ?>
        </div>

        <?php if (!$can_manage): ?>
            <p class="hint-banner">You have read-only access. Ask an owner, admin, or member to add or edit clients.</p>
        <?php endif; ?>

        <?php if (count($clients) === 0): ?>
            <div class="welcome-card">
                <p class="welcome-card__text">No clients yet.<?= $can_manage ? ' Add your first customer to get ready for invoicing.' : '' ?></p>
            </div>
        <?php else: ?>
            <div class="table-wrap welcome-card" data-billo-filter-table style="padding:0;border:none;box-shadow:none;background:transparent">
                <div class="welcome-card" style="padding:0.75rem 1rem 0;margin:0;border:none;box-shadow:none">
                    <label class="label" for="clients-table-filter" style="font-size:0.85rem">Filter rows</label>
                    <input type="search" id="clients-table-filter" class="input input--sm" data-billo-filter-input placeholder="Search ID, name, company, email…" autocomplete="off" style="max-width:24rem">
                </div>
                <div class="welcome-card" style="padding:0;overflow:hidden;margin-top:0.5rem">
                    <table class="data-table data-table--comfortable">
                        <thead>
                        <tr>
                            <th class="num">ID</th>
                            <th>Name</th>
                            <th>Company</th>
                            <th>Email</th>
                            <th>Phone</th>
                            <th>Location</th>
                            <?php if ($can_manage): ?>
                                <th class="data-table__actions">Actions</th>
                            <?php endif; ?>
                        </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($clients as $c): ?>
                            <?php
                            $cid = (int) ($c['id'] ?? 0);
                            $cname = (string) ($c['name'] ?? '');
                            $comp = isset($c['company_name']) ? (string) $c['company_name'] : '';
                            $em = isset($c['email']) ? (string) $c['email'] : '';
                            $ph = isset($c['phone']) ? (string) $c['phone'] : '';
                            $locBits = array_filter([$c['city'] ?? null, $c['state'] ?? null, $c['country'] ?? null]);
                            $loc = $locBits !== [] ? implode(' ', $locBits) : '';
                            $searchBlob = strtolower(implode(' ', array_filter([(string) $cid, $cname, $comp, $em, $ph, $loc])));
                            ?>
                            <tr data-billo-search="<?= billo_e($searchBlob) ?>">
                                <td class="num"><?= $cid ?></td>
                                <td><strong><?= billo_e($cname) ?></strong></td>
                                <td><?php
                                    $cn = isset($c['company_name']) && $c['company_name'] !== '' ? (string) $c['company_name'] : '—';
                                    echo billo_e($cn);
                                    ?></td>
                                <td><?php
                                    $em = isset($c['email']) && $c['email'] !== '' ? (string) $c['email'] : '—';
                                    echo billo_e($em);
                                    ?></td>
                                <td><?php
                                    $ph = isset($c['phone']) && $c['phone'] !== '' ? (string) $c['phone'] : '—';
                                    echo billo_e($ph);
                                    ?></td>
                                <td><?php
                                    $bits = array_filter([
                                    $c['city'] ?? null,
                                    $c['state'] ?? null,
                                    $c['country'] ?? null,
                                ]);
                                    echo billo_e($bits !== [] ? implode(', ', $bits) : '—');
                                    ?></td>
                                <?php if ($can_manage): ?>
                                    <td class="data-table__actions">
                                        <a class="btn btn--ghost btn--sm" href="<?= billo_e(billo_url('/clients/edit?id=' . (int) ($c['id'] ?? 0))) ?>">Edit</a>
                                        <form method="post" action="<?= billo_e(billo_url('/clients/delete')) ?>" class="inline-form" onsubmit="return confirm('Remove this client? This cannot be undone.');">
                                            <input type="hidden" name="_csrf" value="<?= billo_e(Csrf::token()) ?>">
                                            <input type="hidden" name="id" value="<?= (int) ($c['id'] ?? 0) ?>">
                                            <button type="submit" class="btn btn--ghost btn--sm" style="color:var(--color-danger)">Delete</button>
                                        </form>
                                    </td>
                                <?php endif; ?>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
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
