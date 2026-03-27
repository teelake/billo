<?php
declare(strict_types=1);

use App\Core\Csrf;

/** @var list<array{user_id:int,email:string,name:string,granted_at:string,expires_at:?string,granted_by_user_id:?int}> $operators */
/** @var int $active_count */
/** @var int $current_user_id */
/** @var string $user_name */
/** @var string $role */
/** @var bool $show_team_nav */
/** @var string $error */
/** @var string $success */

$error = $error ?? '';
$success = $success ?? '';
$title = 'Platform operators — billo';
ob_start();
?>
<section class="app-dashboard">
    <?php
    $active = 'system-operators';
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
                <h1 class="page-head__title">Platform operators</h1>
                <p class="page-head__lead">Grant or revoke cross-tenant access (<code>/system</code>, configuration, reports). Users sign in with their normal password; this only controls the operator grant.</p>
            </div>
            <a class="btn btn--secondary" href="<?= billo_e(billo_url('/system')) ?>">Overview</a>
        </div>

        <div class="hint-banner" style="margin-bottom:1.25rem">
            <strong>Safety:</strong> you cannot revoke the last active operator. New operators must already have a Billo account (same email as login).
        </div>

        <div class="welcome-card" style="margin-bottom:1.25rem">
            <h2 class="invoice-detail-card__h">Grant access</h2>
            <form class="config-form config-form--inline" method="post" action="<?= billo_e(billo_url('/system/operators/grant')) ?>" style="margin-top:.75rem">
                <input type="hidden" name="_csrf" value="<?= billo_e(Csrf::token()) ?>">
                <div class="config-form__grid">
                    <label class="config-form__field">
                        <span class="config-form__label">User email</span>
                        <input type="email" name="email" class="input" required autocomplete="off" placeholder="name@company.com">
                    </label>
                    <label class="config-form__field">
                        <span class="config-form__label">Optional expiry (local time)</span>
                        <input type="datetime-local" name="expires_at" class="input" autocomplete="off">
                    </label>
                    <label class="config-form__field config-form__field--full">
                        <span class="config-form__label">Notes (optional)</span>
                        <input type="text" name="notes" class="input" maxlength="500" placeholder="Why / ticket">
                    </label>
                </div>
                <div class="config-form__submit" style="margin-top:.75rem">
                    <button type="submit" class="btn btn--primary">Grant operator</button>
                </div>
            </form>
        </div>

        <p class="reports-meta"><?= (int) $active_count ?> active operator<?= (int) $active_count !== 1 ? 's' : '' ?></p>

        <div class="table-scroll">
            <table class="data-table data-table--comfortable">
                <thead>
                    <tr>
                        <th class="num">User ID</th>
                        <th>Email</th>
                        <th>Name</th>
                        <th>Granted</th>
                        <th>Expires</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($operators as $op): ?>
                        <tr>
                            <td class="num"><?= (int) $op['user_id'] ?></td>
                            <td><?= billo_e($op['email']) ?><?= (int) $op['user_id'] === (int) $current_user_id ? ' <span class="chip">you</span>' : '' ?></td>
                            <td><?= billo_e($op['name']) ?></td>
                            <td><?= billo_e($op['granted_at']) ?></td>
                            <td><?= $op['expires_at'] !== null ? billo_e($op['expires_at']) : '—' ?></td>
                            <td class="data-table__actions">
                                <?php if ((int) $active_count > 1): ?>
                                    <form method="post" action="<?= billo_e(billo_url('/system/operators/revoke')) ?>" class="inline-form" onsubmit="return confirm('Revoke platform access for this user?');">
                                        <input type="hidden" name="_csrf" value="<?= billo_e(Csrf::token()) ?>">
                                        <input type="hidden" name="user_id" value="<?= (int) $op['user_id'] ?>">
                                        <button type="submit" class="btn btn--secondary btn--sm">Revoke</button>
                                    </form>
                                <?php else: ?>
                                    <span class="config-form__label" style="margin:0">Last operator</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (count($operators) === 0): ?>
                        <tr><td colspan="6" class="reports-empty">No active operators. Grant one above (requires migration 010).</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
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
