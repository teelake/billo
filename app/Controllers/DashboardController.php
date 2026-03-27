<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Session;
use App\Core\View;
use App\Repositories\OrganizationRepository;
use App\Repositories\UserRepository;

final class DashboardController extends Controller
{
    public function __construct(
        private OrganizationRepository $organizations = new OrganizationRepository(),
        private UserRepository $users = new UserRepository(),
    ) {
    }

    public function index(): void
    {
        $ctx = $this->requireAuth();
        $org = $this->organizations->findById($ctx['organization_id']);
        $user = $this->users->findById($ctx['user_id']);
        $emailVerified = $user !== null && !empty($user['email_verified_at']);

        View::render('dashboard/index', [
            'organization' => $org,
            'role' => $ctx['role'],
            'user_name' => (string) Session::get('user_name', ''),
            'user_email' => (string) Session::get('user_email', ''),
            'email_verified' => $emailVerified,
            'show_team_nav' => in_array($ctx['role'], ['owner', 'admin'], true),
            'error' => Session::flash('error') ?? '',
            'success' => Session::flash('success') ?? '',
        ]);
    }
}
