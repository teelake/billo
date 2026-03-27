<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Request;
use App\Core\Session;

/** Switches sidebar between organization (tenant) and platform operator views for system admins. */
final class NavContextController extends \App\Core\Controller
{
    public function __construct(
        private Request $request,
    ) {
    }

    public function switchMode(): void
    {
        $ctx = $this->authContext();
        if ($ctx === null) {
            Session::flash('error', 'Sign in to continue.');
            $this->redirect('/login');
        }

        $mode = (string) ($this->request->input('mode', '') ?? '');
        if (!in_array($mode, ['organization', 'platform'], true)) {
            $this->redirect('/dashboard');
        }

        if ($mode === 'platform' && !billo_is_system_admin()) {
            Session::flash('error', 'You do not have platform operator access.');
            $this->redirect('/dashboard');
        }

        Session::set('app_nav_mode', $mode);
        $this->redirect('/dashboard');
    }
}
