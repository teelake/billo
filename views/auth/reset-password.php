<?php
declare(strict_types=1);

use App\Core\Csrf;

/** @var string $error */
/** @var string $token */
$error = $error ?? '';
$title = 'New password — billo';
ob_start();
?>
<div class="auth-card">
    <h1 class="auth-card__title">Choose a new password</h1>
    <p class="auth-card__subtitle">Use at least 10 characters.</p>
    <?php if ($error !== ''): ?>
        <div class="alert alert--error" role="alert"><?= billo_e($error) ?></div>
    <?php endif; ?>
    <form class="form" method="post" action="<?= billo_e(billo_url('/reset-password')) ?>" novalidate>
        <input type="hidden" name="_csrf" value="<?= billo_e(Csrf::token()) ?>">
        <input type="hidden" name="token" value="<?= billo_e($token) ?>">
        <div class="field-grid">
            <div class="field">
                <label class="label" for="password">New password</label>
                <input class="input" id="password" name="password" type="password" autocomplete="new-password" required minlength="10">
            </div>
            <div class="field">
                <label class="label" for="password_confirm">Confirm password</label>
                <input class="input" id="password_confirm" name="password_confirm" type="password" autocomplete="new-password" required minlength="10">
            </div>
        </div>
        <button class="btn btn--primary btn--block" type="submit">Update password</button>
    </form>
</div>
<?php
$content = ob_get_clean();
require dirname(__DIR__) . '/layouts/auth.php';
