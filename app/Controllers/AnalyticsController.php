<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Session;
use App\Core\View;
use App\Repositories\TenantAnalyticsRepository;

final class AnalyticsController extends Controller
{
    public function __construct(
        private TenantAnalyticsRepository $tenantAnalytics = new TenantAnalyticsRepository(),
    ) {
    }

    public function index(): void
    {
        $ctx = $this->requireAuthRole(['owner', 'admin']);
        $orgId = $ctx['organization_id'];

        $summary = $this->tenantAnalytics->summary($orgId);
        $status = $this->tenantAnalytics->invoiceStatusBreakdown($orgId);
        $trends = $this->tenantAnalytics->monthlyTrends($orgId, 12);

        $monthShort = static function (string $ym): string {
            $t = date_create($ym . '-01');

            return $t ? $t->format('M Y') : $ym;
        };

        $labels = [];
        foreach ($trends['months'] as $ym) {
            $labels[] = $monthShort($ym);
        }

        View::render('analytics/index', [
            'summary' => $summary,
            'status' => $status,
            'trends' => $trends,
            'trend_labels' => $labels,
            'user_name' => (string) Session::get('user_name', ''),
            'role' => (string) Session::get('role', 'owner'),
            'show_team_nav' => in_array(Session::get('role', ''), ['owner', 'admin'], true),
            'error' => Session::flash('error') ?? '',
            'success' => Session::flash('success') ?? '',
        ]);
    }
}
