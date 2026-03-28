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
        $user = $this->users->findById($ctx['user_id']);
        $emailVerified = $user !== null && !empty($user['email_verified_at']);

        $isPlatformOperator = billo_is_system_admin();
        $operatorWithoutTenant = function_exists('billo_operator_without_tenant') && billo_operator_without_tenant();
        $platformSummary = $isPlatformOperator ? $this->platformAnalytics->summary() : null;
        $orgId = (int) $ctx['organization_id'];

        if ($operatorWithoutTenant) {
            $org = null;
            $tenantSummary = null;
            $invoiceStatusBreakdown = null;
            $recentInvoices = [];
            $canManageInvoices = false;
            $showTeamNav = false;
            $canManageClients = false;
        } else {
            $org = $this->organizations->findById($orgId);
            $tenantSummary = in_array($ctx['role'], ['owner', 'admin'], true)
                ? $this->tenantAnalytics->summary($orgId)
                : null;
            $invoiceStatusBreakdown = in_array($ctx['role'], ['owner', 'admin'], true)
                ? $this->tenantAnalytics->invoiceStatusBreakdown($orgId)
                : null;
            $recentInvoices = $this->invoices->recentForOrganization($orgId, 8);
            $canManageInvoices = in_array($ctx['role'], ['owner', 'admin', 'member'], true);
            $showTeamNav = in_array($ctx['role'], ['owner', 'admin'], true);
            $canManageClients = in_array($ctx['role'], ['owner', 'admin', 'member'], true);
        }

        View::render('dashboard/index', [
            'organization' => $org,
            'role' => $ctx['role'],
            'user_name' => (string) Session::get('user_name', ''),
            'user_email' => (string) Session::get('user_email', ''),
            'email_verified' => $emailVerified,
            'show_team_nav' => $showTeamNav,
            'can_manage_clients' => $canManageClients,
            'is_platform_operator' => $isPlatformOperator,
            'operator_without_tenant' => $operatorWithoutTenant,
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
