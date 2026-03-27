<?php
declare(strict_types=1);

use App\Core\Config;
use App\Core\Csrf;

/** @var list<string> $db_setting_keys */
/** @var string $user_name */
/** @var string $role */
/** @var bool $show_team_nav */
/** @var string $error */
/** @var string $success */

$error = $error ?? '';
$success = $success ?? '';
$title = 'Platform configuration — billo';

$dbKeys = $db_setting_keys ?? [];
$hasDb = static function (string $k) use ($dbKeys): bool {
    return in_array($k, $dbKeys, true);
};

$adminEmails = Config::get('platform.admin_emails', []);
$adminText = is_array($adminEmails) ? implode("\n", array_map('strval', $adminEmails)) : '';

$fBool = static function (mixed $v): bool {
    return $v === true || $v === 1 || $v === '1';
};

ob_start();
?>
<section class="app-dashboard">
    <?php
    $active = 'system-configuration';
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
                <h1 class="page-head__title">Configuration</h1>
            </div>
            <div class="page-head__actions">
                <a class="btn btn--secondary" href="<?= billo_e(billo_url('/platform/landing')) ?>">Landing</a>
            </div>
        </div>

        <form class="config-form" method="post" action="<?= billo_e(billo_url('/system/configuration')) ?>">
            <input type="hidden" name="_csrf" value="<?= billo_e(Csrf::token()) ?>">

            <fieldset class="config-form__section welcome-card">
                <legend class="config-form__legend">Application</legend>
                <div class="config-form__grid">
                    <label class="config-form__field">
                        <span class="config-form__label">Public URL (no trailing slash)</span>
                        <input type="url" name="cfg_app_public_url" class="input" value="<?= billo_e((string) Config::get('app.url', '')) ?>" placeholder="https://example.com" autocomplete="off">
                    </label>
                    <label class="config-form__field">
                        <span class="config-form__label">Base path (e.g. /billo or empty)</span>
                        <input type="text" name="cfg_app_base_path" class="input" value="<?= billo_e((string) Config::get('app.base_path', '')) ?>" placeholder="/billo" autocomplete="off">
                    </label>
                    <label class="config-form__field">
                        <span class="config-form__label">Assets URL segment</span>
                        <input type="text" name="cfg_app_assets_url_segment" class="input" value="<?= billo_e((string) Config::get('app.assets_url_segment', '')) ?>" placeholder="usually empty" autocomplete="off">
                    </label>
                    <label class="config-form__field">
                        <span class="config-form__label">Environment label</span>
                        <input type="text" name="cfg_app_env" class="input" value="<?= billo_e((string) Config::get('app.env', '')) ?>" placeholder="production" maxlength="32" autocomplete="off">
                    </label>
                    <label class="config-form__field">
                        <span class="config-form__label">Debug mode</span>
                        <?php $dbg = $fBool(Config::get('app.debug', false)); ?>
                        <select name="cfg_app_debug" class="input">
                            <option value=""<?= !$hasDb('app.debug') ? ' selected' : '' ?>>— File default —</option>
                            <option value="1"<?= $hasDb('app.debug') && $dbg ? ' selected' : '' ?>>On</option>
                            <option value="0"<?= $hasDb('app.debug') && !$dbg ? ' selected' : '' ?>>Off</option>
                        </select>
                    </label>
                </div>
            </fieldset>

            <fieldset class="config-form__section welcome-card">
                <legend class="config-form__legend">Brand</legend>
                <div class="config-form__grid">
                    <label class="config-form__field">
                        <span class="config-form__label">Product / app name</span>
                        <input type="text" name="cfg_brand_name" class="input" value="<?= billo_e((string) Config::get('app.name', '')) ?>" maxlength="120" autocomplete="off">
                    </label>
                    <label class="config-form__field config-form__field--full">
                        <span class="config-form__label">Tagline</span>
                        <textarea name="cfg_brand_tagline" class="input" rows="2" maxlength="500"><?= billo_e((string) Config::get('brand.tagline', '')) ?></textarea>
                    </label>
                    <label class="config-form__field">
                        <span class="config-form__label">Support email</span>
                        <input type="email" name="cfg_brand_support_email" class="input" value="<?= billo_e((string) Config::get('brand.support_email', '')) ?>" autocomplete="off">
                    </label>
                </div>
            </fieldset>

            <fieldset class="config-form__section welcome-card">
                <legend class="config-form__legend">Mail</legend>
                <div class="config-form__grid">
                    <label class="config-form__field">
                        <span class="config-form__label">Driver</span>
                        <select name="cfg_mail_driver" class="input">
                            <?php $md = (string) Config::get('mail.driver', ''); ?>
                            <option value=""<?= !$hasDb('mail.driver') ? ' selected' : '' ?>>— File default —</option>
                            <option value="log"<?= $hasDb('mail.driver') && $md === 'log' ? ' selected' : '' ?>>log</option>
                            <option value="mail"<?= $hasDb('mail.driver') && $md === 'mail' ? ' selected' : '' ?>>mail</option>
                            <option value="smtp"<?= $hasDb('mail.driver') && $md === 'smtp' ? ' selected' : '' ?>>smtp</option>
                        </select>
                    </label>
                    <label class="config-form__field">
                        <span class="config-form__label">From address</span>
                        <input type="email" name="cfg_mail_from_address" class="input" value="<?= billo_e((string) Config::get('mail.from_address', '')) ?>" autocomplete="off">
                    </label>
                    <label class="config-form__field">
                        <span class="config-form__label">From name</span>
                        <input type="text" name="cfg_mail_from_name" class="input" value="<?= billo_e((string) Config::get('mail.from_name', '')) ?>" maxlength="120" autocomplete="off">
                    </label>
                    <label class="config-form__field">
                        <span class="config-form__label">SMTP host</span>
                        <input type="text" name="cfg_mail_smtp_host" class="input" value="<?= billo_e((string) Config::get('mail.smtp.host', '')) ?>" autocomplete="off">
                    </label>
                    <label class="config-form__field">
                        <span class="config-form__label">SMTP port</span>
                        <input type="number" name="cfg_mail_smtp_port" class="input" value="<?= billo_e((string) Config::get('mail.smtp.port', '')) ?>" min="1" max="65535">
                    </label>
                    <label class="config-form__field">
                        <span class="config-form__label">SMTP encryption</span>
                        <?php $me = (string) Config::get('mail.smtp.encryption', ''); ?>
                        <select name="cfg_mail_smtp_encryption" class="input">
                            <option value=""<?= !$hasDb('mail.smtp.encryption') ? ' selected' : '' ?>>— File default —</option>
                            <option value="tls"<?= $hasDb('mail.smtp.encryption') && $me === 'tls' ? ' selected' : '' ?>>tls</option>
                            <option value="ssl"<?= $hasDb('mail.smtp.encryption') && $me === 'ssl' ? ' selected' : '' ?>>ssl</option>
                        </select>
                    </label>
                    <label class="config-form__field">
                        <span class="config-form__label">SMTP username</span>
                        <input type="text" name="cfg_mail_smtp_username" class="input" value="<?= billo_e((string) Config::get('mail.smtp.username', '')) ?>" autocomplete="off">
                    </label>
                    <label class="config-form__field">
                        <span class="config-form__label">SMTP password</span>
                        <input type="password" name="cfg_mail_smtp_password" class="input" value="" autocomplete="new-password" placeholder="Leave blank to keep current">
                        <label class="config-form__clear"><input type="checkbox" name="clr_mail_smtp_password" value="1"> Remove stored password (use file)</label>
                    </label>
                    <label class="config-form__field">
                        <span class="config-form__label">SMTP timeout (seconds)</span>
                        <input type="number" name="cfg_mail_smtp_timeout" class="input" value="<?= billo_e((string) Config::get('mail.smtp.timeout', '')) ?>" min="1" max="300">
                    </label>
                </div>
            </fieldset>

            <fieldset class="config-form__section welcome-card">
                <legend class="config-form__legend">Payments</legend>
                <div class="config-form__grid">
                    <label class="config-form__field">
                        <span class="config-form__label">Provider</span>
                        <?php $pr = (string) Config::get('payments.provider', ''); ?>
                        <select name="cfg_payments_provider" class="input">
                            <option value=""<?= !$hasDb('payments.provider') ? ' selected' : '' ?>>— File default —</option>
                            <option value="none"<?= $hasDb('payments.provider') && $pr === 'none' ? ' selected' : '' ?>>none</option>
                            <option value="paystack"<?= $hasDb('payments.provider') && $pr === 'paystack' ? ' selected' : '' ?>>paystack</option>
                            <option value="stripe"<?= $hasDb('payments.provider') && $pr === 'stripe' ? ' selected' : '' ?>>stripe</option>
                        </select>
                    </label>
                    <label class="config-form__field">
                        <span class="config-form__label">Fallback payer email</span>
                        <input type="email" name="cfg_payments_fallback_payer_email" class="input" value="<?= billo_e((string) Config::get('payments.fallback_payer_email', '')) ?>" autocomplete="off">
                    </label>
                    <label class="config-form__field config-form__field--full">
                        <span class="config-form__label">Pay link signing secret</span>
                        <input type="password" name="cfg_payments_link_signing_secret" class="input" value="" autocomplete="off" placeholder="Leave blank to keep current">
                        <label class="config-form__clear"><input type="checkbox" name="clr_payments_link_signing_secret" value="1"> Remove from database</label>
                    </label>
                    <label class="config-form__field config-form__field--full">
                        <span class="config-form__label">Paystack secret key</span>
                        <input type="password" name="cfg_payments_paystack_secret_key" class="input" value="" autocomplete="off" placeholder="Leave blank to keep current">
                        <label class="config-form__clear"><input type="checkbox" name="clr_payments_paystack_secret_key" value="1"> Remove from database</label>
                    </label>
                    <label class="config-form__field config-form__field--full">
                        <span class="config-form__label">Paystack public key</span>
                        <input type="password" name="cfg_payments_paystack_public_key" class="input" value="" autocomplete="off" placeholder="Leave blank to keep current">
                        <label class="config-form__clear"><input type="checkbox" name="clr_payments_paystack_public_key" value="1"> Remove from database</label>
                    </label>
                    <label class="config-form__field config-form__field--full">
                        <span class="config-form__label">Stripe secret key</span>
                        <input type="password" name="cfg_payments_stripe_secret_key" class="input" value="" autocomplete="off" placeholder="Leave blank to keep current">
                        <label class="config-form__clear"><input type="checkbox" name="clr_payments_stripe_secret_key" value="1"> Remove from database</label>
                    </label>
                    <label class="config-form__field config-form__field--full">
                        <span class="config-form__label">Stripe webhook secret</span>
                        <input type="password" name="cfg_payments_stripe_webhook_secret" class="input" value="" autocomplete="off" placeholder="Leave blank to keep current">
                        <label class="config-form__clear"><input type="checkbox" name="clr_payments_stripe_webhook_secret" value="1"> Remove from database</label>
                    </label>
                </div>
            </fieldset>

            <fieldset class="config-form__section welcome-card">
                <legend class="config-form__legend">Session cookie</legend>
                <div class="config-form__grid">
                    <label class="config-form__field">
                        <span class="config-form__label">Cookie name</span>
                        <input type="text" name="cfg_session_name" class="input" value="<?= billo_e((string) Config::get('session.name', '')) ?>" pattern="[a-zA-Z0-9_-]+" maxlength="64" autocomplete="off">
                    </label>
                    <label class="config-form__field">
                        <span class="config-form__label">Lifetime (seconds)</span>
                        <input type="number" name="cfg_session_lifetime" class="input" value="<?= billo_e((string) Config::get('session.lifetime', '')) ?>" min="60">
                    </label>
                    <label class="config-form__field">
                        <span class="config-form__label">SameSite</span>
                        <?php $ss = (string) Config::get('session.samesite', 'Lax'); ?>
                        <select name="cfg_session_samesite" class="input">
                            <option value=""<?= !$hasDb('session.samesite') ? ' selected' : '' ?>>— File default —</option>
                            <option value="Lax"<?= $hasDb('session.samesite') && $ss === 'Lax' ? ' selected' : '' ?>>Lax</option>
                            <option value="Strict"<?= $hasDb('session.samesite') && $ss === 'Strict' ? ' selected' : '' ?>>Strict</option>
                            <option value="None"<?= $hasDb('session.samesite') && $ss === 'None' ? ' selected' : '' ?>>None</option>
                        </select>
                    </label>
                    <label class="config-form__field">
                        <span class="config-form__label">Secure (HTTPS only)</span>
                        <?php $sec = $fBool(Config::get('session.secure', false)); ?>
                        <select name="cfg_session_secure" class="input">
                            <option value=""<?= !$hasDb('session.secure') ? ' selected' : '' ?>>— File default —</option>
                            <option value="1"<?= $hasDb('session.secure') && $sec ? ' selected' : '' ?>>Yes</option>
                            <option value="0"<?= $hasDb('session.secure') && !$sec ? ' selected' : '' ?>>No</option>
                        </select>
                    </label>
                    <label class="config-form__field">
                        <span class="config-form__label">HttpOnly</span>
                        <?php $ho = $fBool(Config::get('session.httponly', true)); ?>
                        <select name="cfg_session_httponly" class="input">
                            <option value=""<?= !$hasDb('session.httponly') ? ' selected' : '' ?>>— File default —</option>
                            <option value="1"<?= $hasDb('session.httponly') && $ho ? ' selected' : '' ?>>Yes</option>
                            <option value="0"<?= $hasDb('session.httponly') && !$ho ? ' selected' : '' ?>>No</option>
                        </select>
                    </label>
                </div>
            </fieldset>

            <fieldset class="config-form__section welcome-card">
                <legend class="config-form__legend">Auth TTLs</legend>
                <div class="config-form__grid">
                    <label class="config-form__field">
                        <span class="config-form__label">Password reset (minutes)</span>
                        <input type="number" name="cfg_auth_password_reset_ttl_minutes" class="input" value="<?= billo_e((string) Config::get('auth.password_reset_ttl_minutes', '')) ?>" min="5" max="10080">
                    </label>
                    <label class="config-form__field">
                        <span class="config-form__label">Email verification (hours)</span>
                        <input type="number" name="cfg_auth_email_verification_ttl_hours" class="input" value="<?= billo_e((string) Config::get('auth.email_verification_ttl_hours', '')) ?>" min="1" max="720">
                    </label>
                    <label class="config-form__field">
                        <span class="config-form__label">Team invitation (days)</span>
                        <input type="number" name="cfg_auth_invitation_ttl_days" class="input" value="<?= billo_e((string) Config::get('auth.invitation_ttl_days', '')) ?>" min="1" max="90">
                    </label>
                </div>
            </fieldset>

            <fieldset class="config-form__section welcome-card">
                <legend class="config-form__legend">Platform</legend>
                <label class="config-form__field config-form__field--full">
                    <span class="config-form__label">Landing editor allowed emails (one per line or comma-separated)</span>
                    <textarea name="cfg_platform_admin_emails" class="input" rows="4" placeholder="ops@example.com"><?= billo_e($adminText) ?></textarea>
                </label>
            </fieldset>

            <div class="config-form__submit">
                <button type="submit" class="btn btn--primary">Save configuration</button>
            </div>
        </form>
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
