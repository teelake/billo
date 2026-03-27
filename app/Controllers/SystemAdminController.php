<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Config;
use App\Core\Controller;
use App\Core\Database;
use App\Core\Request;
use App\Core\Session;
use App\Core\View;
use App\Repositories\OrganizationRepository;
use App\Repositories\PlatformAnalyticsRepository;
use App\Repositories\PlatformReportsRepository;
use App\Repositories\UserRepository;
use PDOException;

final class SystemAdminController extends Controller
{
    public function __construct(
        private Request $request,
        private UserRepository $users = new UserRepository(),
        private OrganizationRepository $organizations = new OrganizationRepository(),
        private PlatformAnalyticsRepository $platformAnalytics = new PlatformAnalyticsRepository(),
        private PlatformReportsRepository $platformReports = new PlatformReportsRepository(),
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

    public function reports(): void
    {
        $this->requireSystemAdmin();

        $type = $this->request->input('type', 'organizations') ?? 'organizations';
        if (!in_array($type, ['organizations', 'invoices', 'users'], true)) {
            $type = 'organizations';
        }
        $page = (int) ($this->request->input('page', '1') ?? '1');
        $perPage = (int) ($this->request->input('per_page', (string) PlatformReportsRepository::DEFAULT_PER_PAGE)
            ?? (string) PlatformReportsRepository::DEFAULT_PER_PAGE);
        $page = $this->platformReports->clampPage($page);
        $perPage = $this->platformReports->clampPerPage($perPage);

        $q = (string) ($this->request->input('q', '') ?? '');
        $status = $this->platformReports->normalizeInvoiceStatus($this->request->input('status', '') ?? '');
        $orgIdRaw = $this->request->input('organization_id', '') ?? '';
        $organizationId = max(0, (int) preg_replace('/\D/', '', (string) $orgIdRaw));
        [$dateFrom, $dateTo] = $this->platformReports->normalizeDateRange(
            $this->request->input('from', '') ?? '',
            $this->request->input('to', '') ?? '',
        );
        $fromStr = $dateFrom ?? '';
        $toStr = $dateTo ?? '';

        $rows = [];
        $total = 0;
        if ($type === 'organizations') {
            $total = $this->platformReports->countOrganizations($q);
        } elseif ($type === 'invoices') {
            $total = $this->platformReports->countInvoices($status, $organizationId, $dateFrom, $dateTo);
        } else {
            $total = $this->platformReports->countUsers($q);
        }

        $totalPages = max(1, (int) ceil($total / $perPage));
        $page = min($page, $totalPages);

        if ($type === 'organizations') {
            $rows = $this->platformReports->listOrganizations($page, $perPage, $q);
        } elseif ($type === 'invoices') {
            $rows = $this->platformReports->listInvoices($page, $perPage, $status, $organizationId, $dateFrom, $dateTo);
        } else {
            $rows = $this->platformReports->listUsers($page, $perPage, $q);
        }

        $queryForLinks = array_filter([
            'type' => $type,
            'q' => $q,
            'status' => $status,
            'organization_id' => $organizationId > 0 ? (string) $organizationId : '',
            'from' => $fromStr,
            'to' => $toStr,
            'per_page' => (string) $perPage,
        ], static fn ($v) => $v !== null && $v !== '');

        View::render('system/reports', [
            'report_type' => $type,
            'rows' => $rows,
            'total' => $total,
            'page' => $page,
            'per_page' => $perPage,
            'total_pages' => $totalPages,
            'q' => $q,
            'status' => $status,
            'organization_id' => $organizationId,
            'from' => $fromStr,
            'to' => $toStr,
            'query_for_links' => $queryForLinks,
            'user_name' => (string) Session::get('user_name', ''),
            'role' => (string) Session::get('role', 'owner'),
            'show_team_nav' => in_array(Session::get('role', ''), ['owner', 'admin'], true),
            'error' => Session::flash('error') ?? '',
            'success' => Session::flash('success') ?? '',
        ]);
    }

    public function reportsExport(): void
    {
        $this->requireSystemAdmin();

        $type = $this->request->input('type', 'organizations') ?? 'organizations';
        if (!in_array($type, ['organizations', 'invoices', 'users'], true)) {
            $type = 'organizations';
        }
        $q = (string) ($this->request->input('q', '') ?? '');
        $status = $this->platformReports->normalizeInvoiceStatus($this->request->input('status', '') ?? '');
        $orgIdRaw = $this->request->input('organization_id', '') ?? '';
        $organizationId = max(0, (int) preg_replace('/\D/', '', (string) $orgIdRaw));
        [$dateFrom, $dateTo] = $this->platformReports->normalizeDateRange(
            $this->request->input('from', '') ?? '',
            $this->request->input('to', '') ?? '',
        );

        $filename = 'billo-report-' . $type . '-' . date('Y-m-d') . '.csv';
        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        echo "\xEF\xBB\xBF";

        $out = fopen('php://output', 'w');
        if ($out === false) {
            exit;
        }

        if ($type === 'organizations') {
            fputcsv($out, ['organization_id', 'name', 'slug', 'created_at']);
            foreach ($this->platformReports->exportOrganizationsCsv($q) as $row) {
                fputcsv($out, $row);
            }
        } elseif ($type === 'invoices') {
            fputcsv($out, [
                'invoice_id', 'organization_id', 'organization', 'invoice_number', 'status',
                'total', 'currency', 'issue_date', 'created_at',
            ]);
            foreach ($this->platformReports->exportInvoicesCsv($status, $organizationId, $dateFrom, $dateTo) as $row) {
                fputcsv($out, $row);
            }
        } else {
            fputcsv($out, ['user_id', 'email', 'name', 'is_system_admin', 'created_at']);
            foreach ($this->platformReports->exportUsersCsv($q) as $row) {
                fputcsv($out, $row);
            }
        }
        fclose($out);
        exit;
    }

    public function configuration(): void
    {
        $this->requireSystemAdmin();

        $mask = static function (?string $v): string {
            if ($v === null || $v === '') {
                return '(not set)';
            }

            return strlen($v) <= 6 ? '(set)' : '••••••••…' . substr($v, -4);
        };

        $smtpUser = (string) Config::get('mail.smtp.username', '');
        $smtpPass = (string) Config::get('mail.smtp.password', '');
        $paystackSk = (string) Config::get('payments.paystack.secret_key', '');
        $paystackPk = (string) Config::get('payments.paystack.public_key', '');
        $stripeSk = (string) Config::get('payments.stripe.secret_key', '');
        $stripeWh = (string) Config::get('payments.stripe.webhook_secret', '');
        $linkSecret = (string) Config::get('payments.link_signing_secret', '');
        $adminEmails = Config::get('platform.admin_emails', []);
        $adminList = is_array($adminEmails) ? implode(', ', array_map('strval', $adminEmails)) : '';

        View::render('system/configuration', [
            'config_snapshot' => [
                'application' => [
                    'Name' => (string) Config::get('app.name', ''),
                    'Environment' => (string) Config::get('app.env', ''),
                    'Public URL' => (string) Config::get('app.url', ''),
                    'Base path' => (string) Config::get('app.base_path', ''),
                    'Debug' => Config::get('app.debug', false) ? 'on' : 'off',
                ],
                'session' => [
                    'Cookie name' => (string) Config::get('session.name', ''),
                    'Lifetime (sec)' => (string) Config::get('session.lifetime', ''),
                    'Secure cookie' => Config::get('session.secure', false) ? 'yes' : 'no',
                    'SameSite' => (string) Config::get('session.samesite', ''),
                ],
                'mail' => [
                    'Driver' => (string) Config::get('mail.driver', ''),
                    'From' => trim((string) Config::get('mail.from_name', '') . ' <' . (string) Config::get('mail.from_address', '') . '>'),
                    'SMTP host:port' => (string) Config::get('mail.smtp.host', '') . ':' . (string) Config::get('mail.smtp.port', ''),
                    'SMTP encryption' => (string) Config::get('mail.smtp.encryption', ''),
                    'SMTP username' => $smtpUser !== '' ? $mask($smtpUser) : '(not set)',
                    'SMTP password' => $smtpPass !== '' ? $mask($smtpPass) : '(not set)',
                ],
                'payments' => [
                    'Provider' => (string) Config::get('payments.provider', ''),
                    'Pay link signing secret' => $linkSecret !== '' ? $mask($linkSecret) : '(not set)',
                    'Paystack secret' => $paystackSk !== '' ? $mask($paystackSk) : '(not set)',
                    'Paystack public' => $paystackPk !== '' ? $mask($paystackPk) : '(not set)',
                    'Stripe secret' => $stripeSk !== '' ? $mask($stripeSk) : '(not set)',
                    'Stripe webhook secret' => $stripeWh !== '' ? $mask($stripeWh) : '(not set)',
                ],
                'platform' => [
                    'Landing admin emails' => $adminList !== '' ? $adminList : '(none)',
                ],
                'auth' => [
                    'Password reset TTL (min)' => (string) Config::get('auth.password_reset_ttl_minutes', ''),
                    'Email verification TTL (h)' => (string) Config::get('auth.email_verification_ttl_hours', ''),
                    'Invitation TTL (days)' => (string) Config::get('auth.invitation_ttl_days', ''),
                ],
            ],
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
