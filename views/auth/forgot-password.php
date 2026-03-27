<?php
declare(strict_types=1);

use App\Core\Csrf;

/** @var string $error */
/** @var string $success */
/** @var string $email */
$error = $error ?? '';
$success = $success ?? '';
$email = $email ?? '';
$title = 'Forgot password — billo';
ob_start();
?>
<div class="auth-card">
    <h1 class="auth-card__title">Reset your password</h1>
    <p class="auth-card__subtitle">We’ll email you a link if an account exists for that address.</p>
    <?php if ($error !== ''): ?>
        <div class="alert alert--error" role="alert"><?= billo_e($error) ?></div>
    <?php endif; ?>
    <?php if ($success !== ''): ?>
        <div class="alert alert--success" role="alert"><?= billo_e($success) ?></div>
    <?php endif; ?>
    <form class="form" method="post" action="<?= billo_e(billo_url('/forgot-password')) ?>" novalidate>
        <input type="hidden" name="_csrf" value="<?= billo_e(Csrf::token()) ?>">
        <div class="field">
            <label class="label" for="email">Email</label>
            <input class="input" id="email" name="email" type="email" autocomplete="email" required value="<?= billo_e($email) ?>">
        </div>
        <button class="btn btn--primary btn--block" type="submit">Send reset link</button>
    </form>
    <p class="auth-card__footer"><a href="<?= billo_e(billo_url('/login')) ?>">Back to log in</a></p>
</div>
<?php
$content = ob_get_clean();
require dirname(__DIR__) . '/layouts/auth.php';
