<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Config;
use App\Core\Database;
use App\Repositories\InvoiceRepository;
use PDO;

/**
 * Pushes invoice payloads to an external NRS-compatible API when enabled for the organization.
 * Replace HTTP details when NRS publishes final endpoints and auth.
 */
final class NrsInvoiceSyncService
{
    public function syncSentInvoice(int $invoiceId, int $organizationId): void
    {
        $repo = new InvoiceRepository();
        $inv = $repo->findWithLines($invoiceId, $organizationId);
        if ($inv === null || ($inv['invoice_kind'] ?? '') !== 'invoice' || ($inv['status'] ?? '') !== 'sent') {
            return;
        }

        try {
            $orgStmt = Database::pdo()->prepare(
                'SELECT o.nrs_enabled, o.nrs_tenant_external_id, o.tax_id,
                        p.nrs_integration_allowed, p.nrs_requires_organization_tax_id
                 FROM organizations o
                 LEFT JOIN organization_subscriptions s ON s.organization_id = o.id
                 LEFT JOIN subscription_plans p ON p.id = s.plan_id
                 WHERE o.id = :id LIMIT 1'
            );
            $orgStmt->execute(['id' => $organizationId]);
            $row = $orgStmt->fetch(PDO::FETCH_ASSOC);
        } catch (\Throwable) {
            return;
        }

        if ($row === false || empty($row['nrs_enabled'])) {
            return;
        }
        if ((int) ($row['nrs_integration_allowed'] ?? 0) !== 1) {
            $this->recordSync($invoiceId, $organizationId, 'failed', 'NRS is not enabled for this subscription plan.');

            return;
        }
        if ((int) ($row['nrs_requires_organization_tax_id'] ?? 0) === 1
            && trim((string) ($row['tax_id'] ?? '')) === '') {
            $this->recordSync($invoiceId, $organizationId, 'failed', 'Organization tax / TIN is required for NRS on this plan.');

            return;
        }

        $base = rtrim(trim((string) Config::get('nrs.api_base_url', '')), '/');
        if ($base === '') {
            $this->recordSync($invoiceId, $organizationId, 'failed', 'NRS API is not configured on the platform.');

            return;
        }

        $path = trim((string) Config::get('nrs.invoices_path', '/invoices'));
        if ($path === '' || $path[0] !== '/') {
            $path = '/invoices';
        }
        $token = trim((string) Config::get('nrs.bearer_token', ''));

        try {
            $payload = [
                'invoice' => $inv,
                'nrs_tenant_external_id' => $row['nrs_tenant_external_id'],
            ];
            $ok = $this->postJson($base, $path, $token, $payload);
        } catch (\Throwable $e) {
            error_log('NRS sync payload: ' . $e->getMessage());
            $ok = false;
        }
        $this->recordSync($invoiceId, $organizationId, $ok ? 'sent' : 'failed', $ok ? null : 'HTTP request failed or non-2xx (see server log).');
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function postJson(string $baseUrl, string $path, string $bearerToken, array $payload): bool
    {
        $url = rtrim($baseUrl, '/') . $path;
        if (!filter_var($url, FILTER_VALIDATE_URL)) {
            return false;
        }

        try {
            $body = json_encode($payload, JSON_THROW_ON_ERROR);
        } catch (\Throwable) {
            return false;
        }
        $headers = ['Content-Type: application/json'];
        if (trim($bearerToken) !== '') {
            $headers[] = 'Authorization: Bearer ' . $bearerToken;
        }

        $timeout = (int) Config::get('http.timeout_seconds', 15);
        if ($timeout < 5) {
            $timeout = 15;
        }

        $ch = curl_init($url);
        if ($ch === false) {
            return false;
        }
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $body,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_TIMEOUT => $timeout,
        ]);
        $raw = curl_exec($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        if ($raw === false) {
            error_log('NRS sync: curl failed for ' . $url);

            return false;
        }
        if ($code < 200 || $code >= 300) {
            error_log('NRS sync: HTTP ' . $code . ' for ' . $url);

            return false;
        }

        return true;
    }

    private function recordSync(int $invoiceId, int $organizationId, string $status, ?string $error): void
    {
        try {
            $st = Database::pdo()->prepare(
                'INSERT INTO invoice_nrs_sync (invoice_id, organization_id, status, last_error)
                 VALUES (:iid, :oid, :st, :err)
                 ON DUPLICATE KEY UPDATE status = VALUES(status), last_error = VALUES(last_error), updated_at = CURRENT_TIMESTAMP'
            );
            $st->execute([
                'iid' => $invoiceId,
                'oid' => $organizationId,
                'st' => $status === 'sent' ? 'sent' : 'failed',
                'err' => $error,
            ]);
        } catch (\Throwable $e) {
            error_log('NRS sync log: ' . $e->getMessage());
        }
    }
}
