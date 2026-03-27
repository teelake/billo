<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Csrf;
use App\Core\Request;
use App\Core\Session;
use App\Core\View;
use App\Repositories\EmailVerificationRepository;
use App\Repositories\UserRepository;
use App\Services\AuthService;

final class AccountController extends Controller
{
    public function __construct(
        private Request $request,
        private UserRepository $users = new UserRepository(),
        private AuthService $auth = new AuthService(),
        private EmailVerificationRepository $emailVerification = new EmailVerificationRepository(),
    ) {
    }

    public function profile(): void
    {
        $ctx = $this->requireAuth();
        $user = $this->users->findById($ctx['user_id']);
        if ($user === null) {
            Session::flash('error', 'Account not found.');
            $this->redirect('/login');
        }

        View::render('account/profile', [
            'user' => $user,
            'user_name' => (string) Session::get('user_name', ''),
            'user_email' => (string) Session::get('user_email', ''),
            'role' => $ctx['role'],
            'show_team_nav' => in_array($ctx['role'], ['owner', 'admin'], true),
            'active' => '',
            'error' => Session::flash('error') ?? '',
            'success' => Session::flash('success') ?? '',
        ]);
    }

    public function profileSave(): void
    {
        $ctx = $this->requireAuth();
        if (!$this->validateCsrf()) {
            Session::flash('error', 'Invalid session. Please try again.');
            $this->redirect('/account/profile');
        }

        $name = trim((string) $this->request->input('name', ''));
        $email = trim((string) $this->request->input('email', ''));
        if ($name === '' || (function_exists('mb_strlen') ? mb_strlen($name, 'UTF-8') : strlen($name)) > 120) {
            Session::flash('error', 'Please enter your name (max 120 characters).');
            $this->redirect('/account/profile');
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            Session::flash('error', 'Please enter a valid email address.');
            $this->redirect('/account/profile');
        }

        $row = $this->users->findById($ctx['user_id']);
        if ($row === null) {
            Session::flash('error', 'Account not found.');
            $this->redirect('/login');
        }

        $oldEmail = strtolower((string) $row['email']);
        $newEmail = strtolower($email);
        $emailChanged = $newEmail !== $oldEmail;

        if ($emailChanged && $this->users->emailExistsForOtherUser($email, $ctx['user_id'])) {
            Session::flash('error', 'That email address is already in use.');
            $this->redirect('/account/profile');
        }

        $this->users->updateName($ctx['user_id'], $name);
        Session::set('user_name', $name);

        if ($emailChanged) {
            $this->emailVerification->deleteForUser($ctx['user_id']);
            $this->users->updateEmail($ctx['user_id'], $email);
            Session::set('user_email', strtolower(trim($email)));
            $this->auth->sendEmailVerificationForUser($ctx['user_id']);
            Session::flash('success', 'Profile updated. Confirm your new email address from the message we sent.');
            $this->redirect('/account/profile');
        }

        Session::flash('success', 'Profile saved.');
        $this->redirect('/account/profile');
    }

    public function password(): void
    {
        $ctx = $this->requireAuth();

        View::render('account/password', [
            'user_name' => (string) Session::get('user_name', ''),
            'user_email' => (string) Session::get('user_email', ''),
            'role' => $ctx['role'],
            'show_team_nav' => in_array($ctx['role'], ['owner', 'admin'], true),
            'active' => '',
            'has_password' => $this->users->hasPasswordHash($ctx['user_id']),
            'error' => Session::flash('error') ?? '',
            'success' => Session::flash('success') ?? '',
        ]);
    }

    public function passwordSave(): void
    {
        $ctx = $this->requireAuth();
        if (!$this->validateCsrf()) {
            Session::flash('error', 'Invalid session. Please try again.');
            $this->redirect('/account/password');
        }

        $new = (string) $this->request->input('password', '');
        $confirm = (string) $this->request->input('password_confirm', '');
        $hasPassword = $this->users->hasPasswordHash($ctx['user_id']);

        if ($hasPassword) {
            $current = (string) $this->request->input('current_password', '');
            if (!$this->auth->verifyPasswordForUser($ctx['user_id'], $current)) {
                Session::flash('error', 'Current password is incorrect.');
                $this->redirect('/account/password');
            }
        }

        if ($new !== $confirm) {
            Session::flash('error', 'New passwords do not match.');
            $this->redirect('/account/password');
        }

        $err = $hasPassword
            ? $this->auth->changePassword($ctx['user_id'], $new)
            : $this->auth->setInitialPasswordForOAuthUser($ctx['user_id'], $new);
        if ($err !== null) {
            Session::flash('error', $err);
            $this->redirect('/account/password');
        }

        Session::flash('success', $hasPassword
            ? 'Password updated. Use your new password next time you log in on other devices.'
            : 'Password saved. You can now sign in with email and password as well as Google.');
        $this->redirect('/account/password');
    }

    private function validateCsrf(): bool
    {
        return Csrf::validate($this->request->input('_csrf'));
    }
}
