<?php
declare(strict_types=1);

use App\Core\Csrf;

/** @var array<string, mixed>|null $organization */
/** @var list<array<string, mixed>> $members */
/** @var list<array<string, mixed>> $pending_invitations */
/** @var string $error */
/** @var string $success */
/** @var string $user_name */
/** @var string $role */

$orgName = is_array($organization) ? (string) ($organization['name'] ?? 'Organization') : 'Organization';
$title = 'Team — billo';
ob_start();
?>
<section class="app-dashboard">
    <?php
    $active = 'team';
    $show_team_nav = true;
    include dirname(__DIR__) . '/partials/app_topbar.php';
    ?>
    <div class="container app-dashboard__body">
        <?php if ($error !== ''): ?>
            <div class="alert alert--error" role="alert" style="margin-bottom:1rem"><?= billo_e($error) ?></div>
        <?php endif; ?>
        <?php if ($success !== ''): ?>
            <div class="alert alert--success" role="alert" style="margin-bottom:1rem"><?= billo_e($success) ?></div>
        <?php endif; ?>

        <div class="welcome-card" style="margin-bottom:1.5rem">
            <h1 class="welcome-card__title">Team · <?= billo_e($orgName) ?></h1>
            <p class="welcome-card__text">Invite people by email. They’ll receive a link to join this organization.</p>
        </div>

        <div class="team-grid">
            <div class="welcome-card">
                <h2 class="team-card__title">Invite someone</h2>
                <form class="form" method="post" action="<?= billo_e(billo_url('/team/invite')) ?>">
                    <input type="hidden" name="_csrf" value="<?= billo_e(Csrf::token()) ?>">
                    <div class="field">
                        <label class="label" for="invite_email">Email</label>
                        <input class="input" id="invite_email" name="email" type="email" required autocomplete="off">
                    </div>
                    <div class="field">
                        <label class="label" for="invite_role">Role</label>
                        <select class="input" id="invite_role" name="role">
                            <option value="member">Member</option>
                            <option value="admin">Admin</option>
                            <option value="viewer">Viewer</option>
                        </select>
                    </div>
                    <button class="btn btn--primary" type="submit">Send invitation</button>
                </form>
            </div>

            <div class="welcome-card">
                <h2 class="team-card__title">Pending invitations</h2>
                <?php if (count($pending_invitations) === 0): ?>
                    <p class="welcome-card__text">No open invites.</p>
                <?php else: ?>
                    <ul class="team-list">
                        <?php foreach ($pending_invitations as $inv): ?>
                            <li class="team-list__item">
                                <div>
                                    <strong><?= billo_e((string) ($inv['email'] ?? '')) ?></strong>
                                    <span class="chip" style="margin-left:0.5rem"><?= billo_e(ucfirst((string) ($inv['role'] ?? ''))) ?></span>
                                    <div class="hint">Invited by <?= billo_e((string) ($inv['invited_by_name'] ?? '—')) ?></div>
                                </div>
                                <form method="post" action="<?= billo_e(billo_url('/team/invites/revoke')) ?>" class="inline-form">
                                    <input type="hidden" name="_csrf" value="<?= billo_e(Csrf::token()) ?>">
                                    <input type="hidden" name="invitation_id" value="<?= (int) ($inv['id'] ?? 0) ?>">
                                    <button type="submit" class="btn btn--ghost btn--sm">Cancel</button>
                                </form>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>
        </div>

        <div class="welcome-card" style="margin-top:1.5rem">
            <h2 class="team-card__title">Members</h2>
            <div class="table-wrap">
                <table class="data-table">
                    <thead>
                    <tr>
                        <th>Name</th>
                        <th>Email</th>
                        <th>Role</th>
                        <th>Verified</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($members as $m): ?>
                        <tr>
                            <td><?= billo_e((string) ($m['name'] ?? '')) ?></td>
                            <td><?= billo_e((string) ($m['email'] ?? '')) ?></td>
                            <td class="capitalize"><?= billo_e((string) ($m['role'] ?? '')) ?></td>
                            <td><?= !empty($m['email_verified_at']) ? 'Yes' : 'No' ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
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
