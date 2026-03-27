<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Csrf;
use App\Core\Request;
use App\Core\Session;
use App\Core\View;
use App\Services\AuthService;

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

        $this->redirect('/dashboard');
    }

    public function showSignup(): void
    {
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

        if ($password !== $passwordConfirm) {
            Session::flash('error', 'Passwords do not match.');
            $preserveOld();
            $this->redirect('/signup');
        }

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

        if ($password !== $passwordConfirm) {
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
