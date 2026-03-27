<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Config;
use App\Core\Database;
use App\Core\Session;
use App\Repositories\EmailVerificationRepository;
use App\Repositories\InvitationRepository;
use App\Repositories\MemberRepository;
use App\Repositories\PlatformAdminGrantRepository;
use App\Repositories\OrganizationRepository;
use App\Repositories\PasswordResetRepository;
use App\Repositories\UserRepository;
use App\Support\PasswordRules;
use App\Support\SecureToken;
use DateInterval;
use DateTimeImmutable;
use PDOException;

final class AuthService
{
    public function __construct(
        private UserRepository $users = new UserRepository(),
        private OrganizationRepository $organizations = new OrganizationRepository(),
        private MemberRepository $members = new MemberRepository(),
        private PasswordResetRepository $passwordResets = new PasswordResetRepository(),
        private EmailVerificationRepository $emailVerification = new EmailVerificationRepository(),
        private InvitationRepository $invitations = new InvitationRepository(),
        private EmailNotifications $emails = new EmailNotifications(),
    ) {
    }

    public function attemptLogin(string $email, string $password): bool
    {
        $user = $this->users->findByEmail($email);
        if ($user === null || !password_verify($password, $user['password_hash'])) {
            return false;
        }

        $userId = (int) $user['id'];
        $pref = isset($user['active_organization_id']) && $user['active_organization_id'] !== null
            ? (int) $user['active_organization_id'] : null;
        $membership = $this->members->membershipForUserPreferringOrg($userId, $pref);
        if ($membership === null) {
            return false;
        }

        $this->users->setActiveOrganization($userId, $membership['organization_id']);
        $this->establishSession($userId, $user, $membership);

        return true;
    }

    public function logout(): void
    {
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $p = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
        }
        session_destroy();
    }

    /**
     * @return true|string
     */
    public function register(string $email, string $password, string $name, string $organizationName): true|string
    {
        if (!$this->isValidEmail($email)) {
            return 'Please enter a valid email address.';
        }
        $pwdErr = PasswordRules::validate($password);
        if ($pwdErr !== null) {
            return $pwdErr;
        }
        if ($name === '' || self::len($name) > 120) {
            return 'Please enter your name.';
        }
        if ($organizationName === '' || self::len($organizationName) > 200) {
            return 'Please enter your organization name.';
        }

        if ($this->users->findByEmail($email) !== null) {
            return 'An account with this email already exists.';
        }

        $slug = $this->uniqueSlugFromName($organizationName);
        $hash = password_hash($password, PASSWORD_DEFAULT);
        if ($hash === false) {
            return 'Could not process password. Please try again.';
        }

        $pdo = Database::pdo();
        try {
            $pdo->beginTransaction();
            $userId = $this->users->create($email, $hash, $name);
            $orgId = $this->organizations->create($organizationName, $slug);
            $this->members->attach($orgId, $userId, 'owner');
            $this->users->setActiveOrganization($userId, $orgId);
            $pdo->commit();
        } catch (PDOException $e) {
            $pdo->rollBack();
            error_log('Billo register failed: ' . $e->getMessage());

            return 'Could not create account. Please try again.';
        }

        $userRow = $this->users->findById($userId);
        if ($userRow === null) {
            return 'Could not create account. Please try again.';
        }

        $membership = ['organization_id' => $orgId, 'role' => 'owner'];
        $this->establishSession($userId, $userRow, $membership);
        $this->issueVerificationEmail($userId);

        return true;
    }

    /**
     * Sign up into an existing organization via invitation (session holds pending_invitation_id).
     *
     * @return true|string
     */
    public function registerWithInvitation(string $email, string $password, string $name): true|string
    {
        $inviteId = Session::get('pending_invitation_id');
        if (!is_numeric($inviteId)) {
            return 'Invitation session expired. Open your invitation link again.';
        }

        $invite = $this->invitations->findPendingById((int) $inviteId);
        if ($invite === null) {
            Session::remove('pending_invitation_id');

            return 'This invitation is no longer valid.';
        }

        if (strtolower(trim($email)) !== strtolower((string) $invite['email'])) {
            return 'Use the same email address this invitation was sent to.';
        }

        if (!$this->isValidEmail($email)) {
            return 'Please enter a valid email address.';
        }
        $pwdErr = PasswordRules::validate($password);
        if ($pwdErr !== null) {
            return $pwdErr;
        }
        if ($name === '' || self::len($name) > 120) {
            return 'Please enter your name.';
        }

        if ($this->users->findByEmail($email) !== null) {
            return 'An account already exists for this email. Log in to accept the invite.';
        }

        $passHash = password_hash($password, PASSWORD_DEFAULT);
        if ($passHash === false) {
            return 'Could not process password. Please try again.';
        }

        $orgId = (int) $invite['organization_id'];
        $role = (string) $invite['role'];
        $invitationRowId = (int) $invite['id'];

        $pdo = Database::pdo();
        try {
            $pdo->beginTransaction();
            $userId = $this->users->create($email, $passHash, $name);
            $this->members->attachIfNotMember($orgId, $userId, $role);
            $this->users->setActiveOrganization($userId, $orgId);
            $this->invitations->markAccepted($invitationRowId);
            $pdo->commit();
        } catch (PDOException $e) {
            $pdo->rollBack();
            error_log('Billo invite register failed: ' . $e->getMessage());

            return 'Could not create account. Please try again.';
        }

        Session::remove('pending_invitation_id');

        $userRow = $this->users->findById($userId);
        if ($userRow === null) {
            return 'Could not create account. Please try again.';
        }

        $membership = ['organization_id' => $orgId, 'role' => $role];
        $this->establishSession($userId, $userRow, $membership);
        $this->issueVerificationEmail($userId);

        return true;
    }

    /** Begin invite flow from email link (guest or authenticated). */
    public function beginInvitationFromPlainToken(string $plainToken): true|string
    {
        $plainToken = trim($plainToken);
        if ($plainToken === '') {
            return 'Missing invitation token.';
        }
        $row = $this->invitations->findValidByTokenHash(SecureToken::hash($plainToken));
        if ($row === null) {
            return 'This invitation is invalid or has expired.';
        }
        Session::set('pending_invitation_id', (int) $row['id']);

        return true;
    }

    /**
     * Complete a pending invitation for the logged-in user. Returns an error message or null on success.
     */
    public function completePendingInvitationForUser(int $userId, string $userEmail): ?string
    {
        $inviteId = Session::get('pending_invitation_id');
        if (!is_numeric($inviteId)) {
            return null;
        }

        $invite = $this->invitations->findPendingById((int) $inviteId);
        if ($invite === null) {
            Session::remove('pending_invitation_id');

            return null;
        }

        if (strtolower(trim($userEmail)) !== strtolower((string) $invite['email'])) {
            return 'This invitation was sent to ' . $invite['email'] . '. Sign in with that email address.';
        }

        $orgId = (int) $invite['organization_id'];
        $role = (string) $invite['role'];
        $invitationRowId = (int) $invite['id'];

        $this->members->attachIfNotMember($orgId, $userId, $role);
        $this->invitations->markAccepted($invitationRowId);
        $this->users->setActiveOrganization($userId, $orgId);
        Session::remove('pending_invitation_id');

        $roleNow = $this->members->findMembership($userId, $orgId) ?? $role;
        Session::set('organization_id', $orgId);
        Session::set('role', $roleNow);

        return null;
    }

    public function sendPasswordResetEmail(string $email): void
    {
        if (!$this->isValidEmail($email)) {
            return;
        }
        $user = $this->users->findByEmail($email);
        if ($user === null) {
            return;
        }

        $plain = SecureToken::plain();
        $hash = SecureToken::hash($plain);
        $mins = (int) Config::get('auth.password_reset_ttl_minutes', 60);
        $exp = (new DateTimeImmutable())->add(new DateInterval('PT' . max(5, $mins) . 'M'));

        $this->passwordResets->deleteForEmail($email);
        $this->passwordResets->create($email, $hash, $exp);

        $sent = $this->emails->sendPasswordReset((string) $user['email'], $plain);
        if (!$sent) {
            error_log('Billo: password reset email failed to send.');
        }
    }

    /**
     * @return true|string
     */
    public function resetPasswordWithPlainToken(string $plainToken, string $newPassword): true|string
    {
        $plainToken = trim($plainToken);
        if ($plainToken === '') {
            return 'Invalid reset link.';
        }
        $pwdErr = PasswordRules::validate($newPassword);
        if ($pwdErr !== null) {
            return $pwdErr;
        }

        $row = $this->passwordResets->findValidEmailByTokenHash(SecureToken::hash($plainToken));
        if ($row === null) {
            return 'This reset link is invalid or has expired.';
        }

        $user = $this->users->findByEmail($row['email']);
        if ($user === null) {
            return 'Could not reset password for this account.';
        }

        $hash = password_hash($newPassword, PASSWORD_DEFAULT);
        if ($hash === false) {
            return 'Could not update password. Please try again.';
        }

        $this->users->updatePassword((int) $user['id'], $hash);
        $this->passwordResets->deleteForEmail($row['email']);
        $this->emailVerification->deleteForUser((int) $user['id']);

        return true;
    }

    /**
     * @return true|string
     */
    public function verifyEmailWithPlainToken(string $plainToken): true|string
    {
        $plainToken = trim($plainToken);
        if ($plainToken === '') {
            return 'Invalid verification link.';
        }

        $row = $this->emailVerification->findValidUserIdByTokenHash(SecureToken::hash($plainToken));
        if ($row === null) {
            return 'This verification link is invalid or has expired.';
        }

        $userId = (int) $row['user_id'];
        $this->users->setEmailVerified($userId);
        $this->emailVerification->deleteForUser($userId);

        return true;
    }

    public function resendVerificationEmail(int $userId): void
    {
        $user = $this->users->findById($userId);
        if ($user === null || $user['email_verified_at'] !== null) {
            return;
        }
        $this->issueVerificationEmail($userId);
    }

    public function hasPendingInvitationInSession(): bool
    {
        return is_numeric(Session::get('pending_invitation_id'));
    }

    /** @return array<string, mixed>|null Invitation row for signup UI */
    public function getPendingInvitationForSignupDisplay(): ?array
    {
        $inviteId = Session::get('pending_invitation_id');
        if (!is_numeric($inviteId)) {
            return null;
        }

        return $this->invitations->findPendingById((int) $inviteId);
    }

    /**
     * @param array<string, mixed> $userRow
     * @param array{organization_id:int,role:string} $membership
     */
    private function establishSession(int $userId, array $userRow, array $membership): void
    {
        Session::regenerate();
        Session::set('user_id', $userId);
        Session::set('organization_id', $membership['organization_id']);
        Session::set('role', $membership['role']);
        Session::set('user_name', (string) $userRow['name']);
        Session::set('user_email', (string) $userRow['email']);
        Session::set('is_system_admin', $this->platformAdminGrants->userHasActiveGrant($userId));
    }

    private function issueVerificationEmail(int $userId): void
    {
        $user = $this->users->findById($userId);
        if ($user === null || $user['email_verified_at'] !== null) {
            return;
        }

        $plain = SecureToken::plain();
        $hash = SecureToken::hash($plain);
        $hours = (int) Config::get('auth.email_verification_ttl_hours', 48);
        $hours = max(1, $hours);
        $exp = (new DateTimeImmutable())->add(new DateInterval('PT' . $hours . 'H'));
        $this->emailVerification->upsert($userId, $hash, $exp);

        $ok = $this->emails->sendVerifyEmail((string) $user['email'], $plain);
        if (!$ok) {
            error_log('Billo: verification email failed to send for user ' . $userId);
        }
    }

    private function isValidEmail(string $email): bool
    {
        if (self::len($email) > 255) {
            return false;
        }

        return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
    }

    private static function len(string $value): int
    {
        if (function_exists('mb_strlen')) {
            return (int) mb_strlen($value, 'UTF-8');
        }

        return strlen($value);
    }

    private function uniqueSlugFromName(string $name): string
    {
        $base = strtolower(preg_replace('/[^a-z0-9]+/i', '-', $name) ?? '');
        $base = trim($base, '-');
        if ($base === '') {
            $base = 'org';
        }
        if (strlen($base) > 80) {
            $base = substr($base, 0, 80);
        }
        $slug = $base;
        $n = 0;
        while ($this->organizations->slugExists($slug)) {
            $n++;
            $suffix = '-' . $n . '-' . bin2hex(random_bytes(2));
            $slug = substr($base, 0, max(1, 80 - strlen($suffix))) . $suffix;
        }

        return $slug;
    }
}
