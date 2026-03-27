<?php
declare(strict_types=1);

use App\Core\Csrf;

/** @var string $active dashboard|team */
/** @var bool $show_team_nav */
/** @var string $user_name */
/** @var string $role */
?>
<header class="app-topbar">
    <div class="container app-topbar__inner">
        <div class="app-topbar__left">
            <a class="wordmark" href="<?= billo_e(billo_url('/dashboard')) ?>">billo</a>
            <nav class="app-subnav" aria-label="App">
                <a class="app-subnav__link<?= ($active ?? '') === 'dashboard' ? ' is-active' : '' ?>" href="<?= billo_e(billo_url('/dashboard')) ?>">Dashboard</a>
                <?php if (!empty($show_team_nav)): ?>
                    <a class="app-subnav__link<?= ($active ?? '') === 'team' ? ' is-active' : '' ?>" href="<?= billo_e(billo_url('/team')) ?>">Team</a>
                <?php endif; ?>
            </nav>
        </div>
        <div class="app-topbar__user">
            <span class="app-topbar__meta"><?= billo_e($user_name) ?> · <span class="capitalize"><?= billo_e($role) ?></span></span>
            <form method="post" action="<?= billo_e(billo_url('/logout')) ?>" class="inline-form">
                <input type="hidden" name="_csrf" value="<?= billo_e(Csrf::token()) ?>">
                <button type="submit" class="btn btn--ghost btn--sm">Log out</button>
            </form>
        </div>
    </div>
</header>
