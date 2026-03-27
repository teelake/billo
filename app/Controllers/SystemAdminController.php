<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Database;
use App\Core\Session;
use App\Core\View;
use App\Repositories\OrganizationRepository;
use App\Repositories\PlatformAnalyticsRepository;
use App\Repositories\UserRepository;
use PDOException;

final class SystemAdminController extends Controller
{
    public function __construct(
        private UserRepository $users = new UserRepository(),
        private OrganizationRepository $organizations = new OrganizationRepository(),
        private PlatformAnalyticsRepository $platformAnalytics = new PlatformAnalyticsRepository(),
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

    public function analytics(): void
    {
        $this->requireSystemAdmin();

        $summary = $this->platformAnalytics->summary();
        $status = $this->platformAnalytics->invoiceStatusBreakdown();
        $trends = $this->platformAnalytics->monthlyTrends(12);
        $topOrgs = $this->platformAnalytics->topOrganizationsByVolume(10);

        $monthShort = static function (string $ym): string {
            $t = date_create($ym . '-01');

            return $t ? $t->format('M Y') : $ym;
        };

        $labels = [];
        foreach ($trends['months'] as $ym) {
            $labels[] = $monthShort($ym);
        }

        View::render('system/analytics', [
            'summary' => $summary,
            'status' => $status,
            'trends' => $trends,
            'trend_labels' => $labels,
            'top_orgs' => $topOrgs,
            'user_name' => (string) Session::get('user_name', ''),
            'role' => (string) Session::get('role', 'owner'),
            'show_team_nav' => in_array(Session::get('role', ''), ['owner', 'admin'], true),
            'error' => Session::flash('error') ?? '',
            'success' => Session::flash('success') ?? '',
        ]);
    }

    public function analyticsExport(): void
    {
        $this->requireSystemAdmin();

        $data = $this->platformAnalytics->organizationsReportCsvRows();
        $filename = 'billo-platform-orgs-' . date('Y-m-d') . '.csv';

        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        echo "\xEF\xBB\xBF";

        $out = fopen('php://output', 'w');
        if ($out !== false) {
            fputcsv($out, $data['headers']);
            foreach ($data['rows'] as $row) {
                fputcsv($out, $row);
            }
            fclose($out);
        }
        exit;
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
