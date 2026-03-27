<?php
declare(strict_types=1);

use App\Core\Csrf;

/** @var string $error */
/** @var string $email */
/** @var string $name */
/** @var string $organization_name */
$error = $error ?? '';
$email = $email ?? '';
$name = $name ?? '';
$organization_name = $organization_name ?? '';
$title = 'Finish sign up — billo';
ob_start();
?>
<div class="auth-card auth-card--signup">
    <h1 class="auth-card__title">Name your workspace</h1>
    <p class="auth-card__subtitle">You’re signed in with Google as <strong><?= billo_e($email) ?></strong>. Choose an organization name to finish creating your account.</p>
    <?php if ($error !== ''): ?>
        <div class="alert alert--error" role="alert"><?= billo_e($error) ?></div>
    <?php endif; ?>
    <form class="form" method="post" action="<?= billo_e(billo_url('/signup/google')) ?>" novalidate>
        <input type="hidden" name="_csrf" value="<?= billo_e(Csrf::token()) ?>">
        <div class="field">
            <label class="label" for="organization_name">Organization name</label>
            <input class="input" id="organization_name" name="organization_name" type="text" autocomplete="organization" required maxlength="200" value="<?= billo_e($organization_name) ?>">
        </div>
        <p class="hint" style="margin:0;font-size:0.875rem;color:var(--color-muted)">Signed in as <?= billo_e($name !== '' ? $name : $email) ?> — to use a different Google account, <a href="<?= billo_e(billo_url('/signup')) ?>">go back</a>.</p>
        <button class="btn btn--primary btn--block" type="submit">Create workspace</button>
    </form>
    <p class="auth-card__footer">Wrong account? <a href="<?= billo_e(billo_url('/signup')) ?>">Start over</a></p>
</div>
<?php
$content = ob_get_clean();
$auth_body_class = 'layout-auth--signup';
require dirname(__DIR__) . '/layouts/auth.php';
