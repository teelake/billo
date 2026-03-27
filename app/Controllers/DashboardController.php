<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Session;
use App\Core\View;
use App\Repositories\InvoiceRepository;
use App\Repositories\OrganizationRepository;
use App\Repositories\PlatformAnalyticsRepository;
use App\Repositories\TenantAnalyticsRepository;
use App\Repositories\UserRepository;

final class DashboardController extends Controller
{
    public function __construct(
        private OrganizationRepository $organizations = new OrganizationRepository(),
        private UserRepository $users = new UserRepository(),
        private InvoiceRepository $invoices = new InvoiceRepository(),
        private PlatformAnalyticsRepository $platformAnalytics = new PlatformAnalyticsRepository(),
        private TenantAnalyticsRepository $tenantAnalytics = new TenantAnalyticsRepository(),
    ) {
    }

    public function index(): void
    {
        $ctx = $this->requireAuth();
        $org = $this->organizations->findById($ctx['organization_id']);
        $user = $this->users->findById($ctx['user_id']);
        $emailVerified = $user !== null && !empty($user['email_verified_at']);

        $isPlatformOperator = billo_is_system_admin();
        $platformSummary = $isPlatformOperator ? $this->platformAnalytics->summary() : null;
        $orgId = (int) $ctx['organization_id'];
        $tenantSummary = in_array($ctx['role'], ['owner', 'admin'], true)
            ? $this->tenantAnalytics->summary($orgId)
            : null;
        $invoiceStatusBreakdown = in_array($ctx['role'], ['owner', 'admin'], true)
            ? $this->tenantAnalytics->invoiceStatusBreakdown($orgId)
            : null;
        $recentInvoices = $this->invoices->recentForOrganization($orgId, 8);
        $canManageInvoices = in_array($ctx['role'], ['owner', 'admin', 'member'], true);

        View::render('dashboard/index', [
            'organization' => $org,
            'role' => $ctx['role'],
            'user_name' => (string) Session::get('user_name', ''),
            'user_email' => (string) Session::get('user_email', ''),
            'email_verified' => $emailVerified,
            'show_team_nav' => in_array($ctx['role'], ['owner', 'admin'], true),
            'can_manage_clients' => in_array($ctx['role'], ['owner', 'admin', 'member'], true),
            'is_platform_operator' => $isPlatformOperator,
            'platform_summary' => $platformSummary,
            'tenant_summary' => $tenantSummary,
            'invoice_status_breakdown' => $invoiceStatusBreakdown,
            'recent_invoices' => $recentInvoices,
            'can_manage_invoices' => $canManageInvoices,
            'error' => Session::flash('error') ?? '',
            'success' => Session::flash('success') ?? '',
        ]);
    }
}
