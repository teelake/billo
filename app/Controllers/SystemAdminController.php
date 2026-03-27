<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Session;
use App\Core\View;
use App\Core\Database;
use App\Repositories\OrganizationRepository;
use App\Repositories\UserRepository;
use PDOException;

final class SystemAdminController extends Controller
{
    public function __construct(
        private UserRepository $users = new UserRepository(),
        private OrganizationRepository $organizations = new OrganizationRepository(),
    ) {
    }

    public function index(): void
    {
        $this->requireSystemAdmin();

        try {
            $userCount = $this->users->countAll();
            $orgCount = $this->organizations->countAll();
            $invoiceCount = (int) Database::pdo()->query('SELECT COUNT(*) FROM invoices')->fetchColumn();
        } catch (PDOException) {
            Session::flash('error', 'Could not load statistics.');
            $userCount = $orgCount = $invoiceCount = 0;
        }

        View::render('system/index', [
            'user_count' => $userCount,
            'organization_count' => $orgCount,
            'invoice_count' => $invoiceCount,
            'user_name' => (string) Session::get('user_name', ''),
            'role' => (string) Session::get('role', 'owner'),
            'show_team_nav' => in_array(Session::get('role', ''), ['owner', 'admin'], true),
            'error' => Session::flash('error') ?? '',
            'success' => Session::flash('success') ?? '',
        ]);
    }

    private function requireSystemAdmin(): void
    {
        $ctx = $this->authContext();
        if ($ctx === null) {
            Session::flash('error', 'Sign in to continue.');
            $this->redirect('/login');
        }
        if (!billo_is_system_admin()) {
            Session::flash('error', 'You do not have system operator access.');
            $this->redirect('/dashboard');
        }
    }
}
