<?php
declare(strict_types=1);

use App\Core\Csrf;
use App\Core\Session;

/** @var string $active dashboard|team|billing|clients|invoices|organization|system|system-analytics|system-reports|system-operators|system-configuration|system-integrations|system-plans|system-taxes|platform|analytics */
/** @var bool $show_team_nav */
/** @var string $user_name */
/** @var string $role */

$active = $active ?? '';
$role = $role ?? '';
$userName = $user_name ?? '';
$userEmail = isset($user_email) && is_string($user_email) ? $user_email : (string) Session::get('user_email', '');
$showTeam = !empty($show_team_nav);
$canOrg = in_array($role, ['owner', 'admin'], true);
$canPlatform = (function_exists('billo_is_platform_admin') && billo_is_platform_admin())
    || (function_exists('billo_is_system_admin') && billo_is_system_admin());
$isSystem = function_exists('billo_is_system_admin') && billo_is_system_admin();
$operatorOnly = function_exists('billo_operator_without_tenant') && billo_operator_without_tenant();
$navMode = function_exists('billo_app_nav_mode') ? billo_app_nav_mode() : 'organization';
$showOrgNav = !$operatorOnly && (!$isSystem || $navMode === 'organization');
$showPlatformOperatorNav = $isSystem && ($operatorOnly || $navMode === 'platform');

$initials = 'B';
if ($userName !== '') {
    $parts = preg_split('/\s+/u', trim($userName)) ?: [];
    $initials = strtoupper(
        mb_substr($parts[0] ?? '?', 0, 1, 'UTF-8')
        . mb_substr($parts[count($parts) > 1 ? count($parts) - 1 : 0] ?? '', 0, 1, 'UTF-8')
    );
} elseif ($userEmail !== '') {
    $initials = strtoupper(mb_substr($userEmail, 0, 1, 'UTF-8'));
}
if (function_exists('mb_strlen') && mb_strlen($initials, 'UTF-8') > 2) {
    $initials = mb_substr($initials, 0, 2, 'UTF-8');
}
?>
<aside class="app-sidebar" id="app-sidebar" aria-label="Main navigation">
    <div class="app-sidebar__brand">
        <a class="app-sidebar__wordmark" href="<?= billo_e(billo_url('/dashboard')) ?>"><?= billo_e(billo_brand_name()) ?></a>
    </div>
    <nav class="app-sidebar__nav" aria-label="App sections">
        <a class="app-sidebar__link<?= $active === 'dashboard' ? ' is-active' : '' ?>" href="<?= billo_e(billo_url('/dashboard')) ?>">
            <span class="app-sidebar__icon" aria-hidden="true">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg>
            </span>
            Dashboard
        </a>
        <?php if ($showOrgNav): ?>
            <a class="app-sidebar__link<?= $active === 'clients' ? ' is-active' : '' ?>" href="<?= billo_e(billo_url('/clients')) ?>">
                <span class="app-sidebar__icon" aria-hidden="true">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75"/></svg>
                </span>
                Clients
            </a>
            <a class="app-sidebar__link<?= $active === 'invoices' ? ' is-active' : '' ?>" href="<?= billo_e(billo_url('/invoices')) ?>">
                <span class="app-sidebar__icon" aria-hidden="true">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg>
                </span>
                Invoices
            </a>
            <?php if ($canOrg): ?>
                <a class="app-sidebar__link<?= $active === 'analytics' ? ' is-active' : '' ?>" href="<?= billo_e(billo_url('/analytics')) ?>">
                    <span class="app-sidebar__icon" aria-hidden="true">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="20" x2="18" y2="10"/><line x1="12" y1="20" x2="12" y2="4"/><line x1="6" y1="20" x2="6" y2="14"/></svg>
                    </span>
                    Analytics
                </a>
                <a class="app-sidebar__link<?= $active === 'organization' ? ' is-active' : '' ?>" href="<?= billo_e(billo_url('/organization')) ?>">
                    <span class="app-sidebar__icon" aria-hidden="true">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="7" width="20" height="14" rx="2"/><path d="M16 21V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v16"/></svg>
                    </span>
                    Business
                </a>
                <a class="app-sidebar__link<?= $active === 'billing' ? ' is-active' : '' ?>" href="<?= billo_e(billo_url('/billing')) ?>">
                    <span class="app-sidebar__icon" aria-hidden="true">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="1" y="4" width="22" height="16" rx="2"/><line x1="1" y1="10" x2="23" y2="10"/></svg>
                    </span>
                    Plans &amp; billing
                </a>
            <?php endif; ?>
            <?php if ($showTeam): ?>
                <a class="app-sidebar__link<?= $active === 'team' ? ' is-active' : '' ?>" href="<?= billo_e(billo_url('/team')) ?>">
                    <span class="app-sidebar__icon" aria-hidden="true">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
                    </span>
                    Team
                </a>
            <?php endif; ?>
        <?php endif; ?>

        <?php if ($canPlatform): ?>
            <a class="app-sidebar__link<?= $active === 'platform' ? ' is-active' : '' ?>" href="<?= billo_e(billo_url('/platform/landing')) ?>">
                <span class="app-sidebar__icon" aria-hidden="true">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"/><path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"/><line x1="10" y1="8" x2="16" y2="8"/><line x1="10" y1="12" x2="16" y2="12"/></svg>
                </span>
                Landing page
            </a>
        <?php endif; ?>

        <?php if ($showPlatformOperatorNav): ?>
            <a class="app-sidebar__link<?= $active === 'system-analytics' ? ' is-active' : '' ?>" href="<?= billo_e(billo_url('/system/analytics')) ?>">
                <span class="app-sidebar__icon" aria-hidden="true">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 3v18h18"/><path d="M18 17V9"/><path d="M13 17V5"/><path d="M8 17v-3"/></svg>
                </span>
                Platform analytics
            </a>
            <a class="app-sidebar__link<?= $active === 'system-reports' ? ' is-active' : '' ?>" href="<?= billo_e(billo_url('/system/reports')) ?>">
                <span class="app-sidebar__icon" aria-hidden="true">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14.5 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7.5L14.5 2z"/><polyline points="14 2 14 8 20 8"/><line x1="8" y1="13" x2="16" y2="13"/><line x1="8" y1="17" x2="16" y2="17"/></svg>
                </span>
                Reports
            </a>
            <a class="app-sidebar__link<?= $active === 'system-operators' ? ' is-active' : '' ?>" href="<?= billo_e(billo_url('/system/operators')) ?>">
                <span class="app-sidebar__icon" aria-hidden="true">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
                </span>
                Operators
            </a>
            <a class="app-sidebar__link<?= $active === 'system-configuration' ? ' is-active' : '' ?>" href="<?= billo_e(billo_url('/system/configuration')) ?>">
                <span class="app-sidebar__icon" aria-hidden="true">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 15a3 3 0 1 0 0-6 3 3 0 0 0 0 6Z"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-4 0v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 1 1-2.83-2.83l.06-.06A1.65 1.65 0 0 0 4.6 15a1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1 0-4h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 1 1 2.83-2.83l.06.06A1.65 1.65 0 0 0 9 4.6a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 4 0v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 1 1 2.83 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9c.26.604.852.997 1.51 1H21a2 2 0 0 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1Z"/></svg>
                </span>
                Configuration
            </a>
            <a class="app-sidebar__link<?= $active === 'system-integrations' ? ' is-active' : '' ?>" href="<?= billo_e(billo_url('/system/integrations')) ?>">
                <span class="app-sidebar__icon" aria-hidden="true">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 2L2 7l10 5 10-5-10-5z"/><path d="M2 17l10 5 10-5"/><path d="M2 12l10 5 10-5"/></svg>
                </span>
                NRS integration
            </a>
            <a class="app-sidebar__link<?= $active === 'system-plans' ? ' is-active' : '' ?>" href="<?= billo_e(billo_url('/system/plans')) ?>">
                <span class="app-sidebar__icon" aria-hidden="true">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M16 4h2a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h2"/><rect x="8" y="2" width="8" height="4" rx="1"/></svg>
                </span>
                Subscription plans
            </a>
            <a class="app-sidebar__link<?= $active === 'system-taxes' ? ' is-active' : '' ?>" href="<?= billo_e(billo_url('/system/taxes')) ?>">
                <span class="app-sidebar__icon" aria-hidden="true">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 2v4"/><path d="M12 18v4"/><path d="M4.93 4.93l2.83 2.83"/><path d="M16.24 16.24l2.83 2.83"/><path d="M2 12h4"/><path d="M18 12h4"/><path d="M4.93 19.07l2.83-2.83"/><path d="M16.24 7.76l2.83-2.83"/><circle cx="12" cy="12" r="3"/></svg>
                </span>
                Tax templates
            </a>
            <a class="app-sidebar__link<?= $active === 'system' ? ' is-active' : '' ?>" href="<?= billo_e(billo_url('/system')) ?>">
                <span class="app-sidebar__icon" aria-hidden="true">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="3"/><path d="M12 1v2M12 21v2M4.22 4.22l1.42 1.42M18.36 18.36l1.42 1.42M1 12h2M21 12h2M4.22 19.78l1.42-1.42M18.36 5.64l1.42-1.42"/></svg>
                </span>
                System
            </a>
        <?php endif; ?>

        <?php if ($isSystem && !$operatorOnly): ?>
            <div class="app-sidebar__mode" role="group" aria-label="Navigation context">
                <span class="app-sidebar__mode-label"><?= $navMode === 'platform' ? 'Platform view' : 'Organization view' ?></span>
                <?php if ($navMode === 'organization'): ?>
                    <a class="app-sidebar__mode-toggle" href="<?= billo_e(billo_url('/session/app-mode?mode=platform')) ?>">Switch to platform</a>
                <?php else: ?>
                    <a class="app-sidebar__mode-toggle" href="<?= billo_e(billo_url('/session/app-mode?mode=organization')) ?>">Switch to organization</a>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </nav>
    <button type="button" class="app-sidebar__scrim" id="app-sidebar-scrim" hidden aria-label="Close menu" tabindex="-1"></button>
</aside>

<header class="app-topstrip">
    <div class="app-topstrip__inner">
        <button type="button" class="app-sidebar-toggle" id="app-sidebar-toggle" aria-expanded="false" aria-controls="app-sidebar" aria-label="Open menu">
            <span class="nav-toggle__bar"></span>
            <span class="nav-toggle__bar"></span>
        </button>
        <div class="app-topstrip__spacer" aria-hidden="true"></div>
        <details class="app-profile">
            <summary class="app-profile__trigger" aria-label="Account menu">
                <span class="app-profile__avatar" aria-hidden="true"><?= billo_e($initials) ?></span>
            </summary>
            <div class="app-profile__dropdown">
                <p class="app-profile__name"><?= billo_e($userName !== '' ? $userName : 'Account') ?></p>
                <?php if ($userEmail !== ''): ?>
                    <p class="app-profile__email"><?= billo_e($userEmail) ?></p>
                <?php endif; ?>
                <p class="app-profile__role"><span class="capitalize"><?= billo_e($role) ?></span></p>
                <hr class="app-profile__rule">
                <div class="app-profile__links">
                    <a class="app-profile__link" href="<?= billo_e(billo_url('/account/profile')) ?>">Edit profile</a>
                    <a class="app-profile__link" href="<?= billo_e(billo_url('/account/password')) ?>">Change password</a>
                </div>
                <hr class="app-profile__rule">
                <form method="post" action="<?= billo_e(billo_url('/logout')) ?>" class="app-profile__logout">
                    <input type="hidden" name="_csrf" value="<?= billo_e(Csrf::token()) ?>">
                    <button type="submit" class="btn btn--ghost btn--sm btn--block">Log out</button>
                </form>
            </div>
        </details>
    </div>
</header>
