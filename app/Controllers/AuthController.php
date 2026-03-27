<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Csrf;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Core\View;
use App\Services\AuthService;
use App\Services\GoogleOAuthService;
use App\Support\PasswordRules;
use PDOException;

final class AuthController extends Controller
{
    public function __construct(
        private Request $request,
        private AuthService $auth = new AuthService(),
    ) {
    }

    public function showLogin(): void
    {
        if ($this->authContext() !== null) {
            $this->redirect('/dashboard');
        }
        $oldEmail = Session::get('old_login_email');
        Session::remove('old_login_email');
        View::render('auth/login', [
            'error' => Session::flash('error') ?? '',
            'success' => Session::flash('success') ?? '',
            'email' => is_string($oldEmail) ? $oldEmail : '',
            'invited' => $this->request->input('invited', '') === '1',
        ]);
    }

    public function login(): void
    {
        if (!$this->validateCsrf()) {
            Session::flash('error', 'Invalid session. Please try again.');
            $this->redirect('/login');
        }

        $email = $this->request->input('email', '');
        $password = $this->request->input('password', '');
        if ($email === '' || $password === '') {
            Session::flash('error', 'Enter your email and password.');
            Session::set('old_login_email', $email ?? '');
            $this->redirect('/login');
        }

        try {
            if (!$this->auth->attemptLogin($email, $password ?? '')) {
                Session::flash('error', 'Invalid email or password.');
                Session::set('old_login_email', $email ?? '');
                $this->redirect('/login');
            }

            $hadInvitePending = $this->auth->hasPendingInvitationInSession();
            $userId = (int) Session::get('user_id');
            $userEmail = (string) Session::get('user_email');
            $inviteError = $this->auth->completePendingInvitationForUser($userId, $userEmail);
            if ($inviteError !== null) {
                Session::flash('error', $inviteError);
            } elseif ($hadInvitePending) {
                Session::flash('success', 'You’ve joined the organization.');
            }
        } catch (PDOException $e) {
            error_log('Auth login: ' . $e->getMessage());
            Session::flash('error', 'Cannot connect to the database. Check MySQL and config/config.php.');
            Session::set('old_login_email', $email ?? '');
            $this->redirect('/login');
        }

        $this->redirect('/dashboard');
    }

    public function showSignup(): void
    {
        try {
            if ($this->authContext() !== null) {
                $this->redirect('/dashboard');
            }

            if ($this->auth->hasPendingInvitationInSession() && $this->request->input('invited', '') !== '1') {
                $this->redirect('/signup?invited=1');
            }

            if ($this->request->input('invited', '') === '1' && !$this->auth->hasPendingInvitationInSession()) {
                Session::flash('error', 'Open the invitation link from your email first.');
                $this->redirect('/signup');
            }

            $old = Session::get('old_signup');
            Session::remove('old_signup');
            $defaults = is_array($old) ? $old : [];
            $invite = $this->auth->getPendingInvitationForSignupDisplay();
            $emailDefault = isset($defaults['email']) && is_string($defaults['email']) ? $defaults['email'] : '';
            if (is_array($invite) && isset($invite['email'])) {
                $emailDefault = (string) $invite['email'];
            }

            View::render('auth/signup', [
                'error' => Session::flash('error') ?? '',
                'name' => isset($defaults['name']) && is_string($defaults['name']) ? $defaults['name'] : '',
                'email' => $emailDefault,
                'organization_name' => isset($defaults['organization_name']) && is_string($defaults['organization_name'])
                    ? $defaults['organization_name'] : '',
                'invite' => $invite,
            ]);
        } catch (PDOException $e) {
            error_log('Auth showSignup: ' . $e->getMessage());
            View::render('auth/signup', [
                'error' => 'Cannot connect to the database. Check that MySQL is running and config/config.php (db.*) is correct.',
                'name' => '',
                'email' => '',
                'organization_name' => '',
                'invite' => null,
            ]);
        }
    }

    public function signup(): void
    {
        if (!$this->validateCsrf()) {
            Session::flash('error', 'Invalid session. Please try again.');
            $this->redirect('/signup');
        }

        $name = $this->request->input('name', '');
        $email = $this->request->input('email', '');
        $organizationName = $this->request->input('organization_name', '');
        $password = $this->request->input('password', '');
        $passwordConfirm = $this->request->input('password_confirm', '');

        $preserveOld = function () use ($name, $email, $organizationName): void {
            Session::set('old_signup', [
                'name' => $name ?? '',
                'email' => $email ?? '',
                'organization_name' => $organizationName ?? '',
            ]);
        };

        $pwdErr = PasswordRules::validate($password ?? '');
        if ($pwdErr !== null) {
            Session::flash('error', $pwdErr);
            $preserveOld();
            $this->redirect('/signup');
        }

        if (($password ?? '') !== ($passwordConfirm ?? '')) {
            Session::flash('error', 'Passwords do not match.');
            $preserveOld();
            $this->redirect('/signup');
        }

        try {
            if ($this->auth->hasPendingInvitationInSession()) {
                $result = $this->auth->registerWithInvitation($email ?? '', $password ?? '', $name ?? '');
            } else {
                $result = $this->auth->register($email ?? '', $password ?? '', $name ?? '', $organizationName ?? '');
            }

            if ($result !== true) {
                Session::flash('error', $result);
                $preserveOld();
                $this->redirect('/signup');
            }
        } catch (PDOException $e) {
            error_log('Auth signup: ' . $e->getMessage());
            Session::flash('error', 'Cannot connect to the database. Check MySQL and config/config.php.');
            $preserveOld();
            $this->redirect('/signup');
        }

        $this->redirect('/dashboard');
    }

    public function googleStart(): void
    {
        if ($this->authContext() !== null) {
            $this->redirect('/dashboard');
        }
        $oauth = new GoogleOAuthService();
        if (!$oauth->isEnabled()) {
            Session::flash('error', 'Google sign-in is not available.');
            $this->redirect('/login');
        }
        $intentRaw = (string) $this->request->input('intent', 'login');
        $intent = $intentRaw === 'signup' ? 'signup' : 'login';
        $state = bin2hex(random_bytes(16));
        Session::set('google_oauth_state', $state);
        Session::set('google_oauth_intent', $intent);
        Response::redirect($oauth->buildAuthorizeUrl($state));
    }

    public function googleCallback(): void
    {
        if ($this->authContext() !== null) {
            $this->redirect('/dashboard');
        }
        $oauth = new GoogleOAuthService();
        if (!$oauth->isEnabled()) {
            Session::flash('error', 'Google sign-in is not configured.');
            $this->redirect('/login');
        }

        $errParam = (string) $this->request->input('error', '');
        if ($errParam !== '') {
            Session::flash('error', 'Google sign-in was cancelled or failed.');
            $this->redirect('/login');
        }

        $state = (string) $this->request->input('state', '');
        $expected = Session::get('google_oauth_state');
        Session::remove('google_oauth_state');
        if (!is_string($expected) || $expected === '' || $state === '' || !hash_equals($expected, $state)) {
            Session::flash('error', 'Invalid sign-in session. Please try again.');
            $this->redirect('/login');
        }

        $intent = Session::get('google_oauth_intent', 'login');
        Session::remove('google_oauth_intent');
        $intent = is_string($intent) && $intent === 'signup' ? 'signup' : 'login';

        $code = (string) $this->request->input('code', '');
        $profile = $oauth->exchangeCodeForProfile($code);
        if (is_string($profile)) {
            Session::flash('error', $profile);
            $this->redirect($intent === 'signup' ? '/signup' : '/login');
        }

        $hadInvitePending = $this->auth->hasPendingInvitationInSession();

        try {
            $result = $this->auth->processGoogleOAuthProfile($profile, $intent);
        } catch (PDOException $e) {
            error_log('Google OAuth callback: ' . $e->getMessage());
            Session::flash('error', 'Could not complete sign-in. Check the database connection and try again.');
            $this->redirect('/login');
        }

        if (is_string($result)) {
            Session::flash('error', $result);
            $this->redirect($intent === 'signup' ? '/signup' : '/login');
        }

        if ($result['next'] === 'signup_google') {
            $this->redirect('/signup/google');
        }

        $userId = (int) Session::get('user_id');
        $userEmail = (string) Session::get('user_email');
        $inviteError = $this->auth->completePendingInvitationForUser($userId, $userEmail);
        if ($inviteError !== null) {
            Session::flash('error', $inviteError);
        } elseif ($hadInvitePending && !$this->auth->hasPendingInvitationInSession()) {
            Session::flash('success', 'You’ve joined the organization.');
        }

        $this->redirect('/dashboard');
    }

    public function showSignupGoogle(): void
    {
        if ($this->authContext() !== null) {
            $this->redirect('/dashboard');
        }
        $raw = Session::get('oauth_google_profile');
        if (!is_array($raw)) {
            Session::flash('error', 'Start by choosing Continue with Google on the sign-up page.');
            $this->redirect('/signup');
        }
        $email = isset($raw['email']) ? (string) $raw['email'] : '';
        $name = isset($raw['name']) ? (string) $raw['name'] : '';
        $old = Session::get('old_signup_google');
        Session::remove('old_signup_google');
        $orgDefault = is_array($old) && isset($old['organization_name']) && is_string($old['organization_name'])
            ? $old['organization_name'] : '';

        View::render('auth/signup-google', [
            'error' => Session::flash('error') ?? '',
            'email' => $email,
            'name' => $name,
            'organization_name' => $orgDefault,
        ]);
    }

    public function signupGoogleComplete(): void
    {
        if ($this->authContext() !== null) {
            $this->redirect('/dashboard');
        }
        if (!$this->validateCsrf()) {
            Session::flash('error', 'Invalid session. Please try again.');
            $this->redirect('/signup/google');
        }

        $organizationName = (string) $this->request->input('organization_name', '');

        try {
            $result = $this->auth->completeGoogleWorkspaceSignup($organizationName);
        } catch (PDOException $e) {
            error_log('signupGoogleComplete: ' . $e->getMessage());
            Session::flash('error', 'Cannot connect to the database. Check MySQL and config/config.php.');
            Session::set('old_signup_google', ['organization_name' => $organizationName]);
            $this->redirect('/signup/google');
        }

        if ($result !== true) {
            Session::flash('error', $result);
            Session::set('old_signup_google', ['organization_name' => $organizationName]);
            $this->redirect('/signup/google');
        }

        $this->redirect('/dashboard');
    }

    public function logout(): void
    {
        if (!$this->validateCsrf()) {
            $this->redirect('/dashboard');
        }
        $this->auth->logout();
        Session::start();
        Session::flash('error', 'You have been signed out.');
        $this->redirect('/login');
    }

    public function showForgotPassword(): void
    {
        if ($this->authContext() !== null) {
            $this->redirect('/dashboard');
        }
        View::render('auth/forgot-password', [
            'error' => Session::flash('error') ?? '',
            'success' => Session::flash('success') ?? '',
            'email' => $this->request->input('email', '') ?? '',
        ]);
    }

    public function forgotPassword(): void
    {
        if (!$this->validateCsrf()) {
            Session::flash('error', 'Invalid session. Please try again.');
            $this->redirect('/forgot-password');
        }
        $email = $this->request->input('email', '');
        $this->auth->sendPasswordResetEmail($email ?? '');
        Session::flash('success', 'If an account exists for that email, we’ve sent reset instructions.');
        $this->redirect('/forgot-password');
    }

    public function showResetPassword(): void
    {
        if ($this->authContext() !== null) {
            $this->redirect('/dashboard');
        }
        $token = $this->request->input('token', '');
        if ($token === '') {
            Session::flash('error', 'Invalid or missing reset link.');
            $this->redirect('/login');
        }
        View::render('auth/reset-password', [
            'error' => Session::flash('error') ?? '',
            'token' => $token,
        ]);
    }

    public function resetPassword(): void
    {
        if (!$this->validateCsrf()) {
            Session::flash('error', 'Invalid session. Please try again.');
            $this->redirect('/login');
        }
        $token = $this->request->input('token', '');
        $password = $this->request->input('password', '');
        $passwordConfirm = $this->request->input('password_confirm', '');

        $pwdErr = PasswordRules::validate($password ?? '');
        if ($pwdErr !== null) {
            Session::flash('error', $pwdErr);
            $this->redirect('/reset-password?token=' . rawurlencode($token ?? ''));
        }

        if (($password ?? '') !== ($passwordConfirm ?? '')) {
            Session::flash('error', 'Passwords do not match.');
            $this->redirect('/reset-password?token=' . rawurlencode($token ?? ''));
        }

        $result = $this->auth->resetPasswordWithPlainToken($token ?? '', $password ?? '');
        if ($result !== true) {
            Session::flash('error', $result);
            $this->redirect('/reset-password?token=' . rawurlencode($token ?? ''));
        }

        Session::flash('success', 'Your password has been updated. You can sign in now.');
        $this->redirect('/login');
    }

    public function verifyEmail(): void
    {
        $token = $this->request->input('token', '');
        $result = $this->auth->verifyEmailWithPlainToken($token ?? '');
        if ($result === true) {
            Session::flash('success', 'Your email is verified.');
        } else {
            Session::flash('error', $result);
        }

        $ctx = $this->authContext();
        if ($ctx !== null) {
            $this->redirect('/dashboard');
        }
        $this->redirect('/login');
    }

    public function resendVerificationEmail(): void
    {
        $ctx = $this->requireAuth();
        if (!$this->validateCsrf()) {
            Session::flash('error', 'Invalid session. Please try again.');
            $this->redirect('/dashboard');
        }
        $this->auth->resendVerificationEmail($ctx['user_id']);
        Session::flash('success', 'Verification email sent. Check your inbox.');
        $this->redirect('/dashboard');
    }

    public function acceptInvitation(): void
    {
        $token = $this->request->input('token', '');
        $begin = $this->auth->beginInvitationFromPlainToken($token ?? '');
        if ($begin !== true) {
            Session::flash('error', $begin);
            $this->redirect('/login');
        }

        $ctx = $this->authContext();
        if ($ctx !== null) {
            $err = $this->auth->completePendingInvitationForUser(
                $ctx['user_id'],
                (string) Session::get('user_email', '')
            );
            if ($err !== null) {
                Session::flash('error', $err);
            } else {
                Session::flash('success', 'You’ve joined the organization.');
            }
            $this->redirect('/dashboard');
        }

        $this->redirect('/login?invited=1');
    }

    private function validateCsrf(): bool
    {
        return Csrf::validate($this->request->input('_csrf'));
    }
}
