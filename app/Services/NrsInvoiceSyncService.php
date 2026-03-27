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

        $orgStmt = Database::pdo()->prepare(
            'SELECT nrs_enabled, nrs_api_base_url, nrs_bearer_token, nrs_tenant_external_id FROM organizations WHERE id = :id LIMIT 1'
        );
        $orgStmt->execute(['id' => $organizationId]);
        $org = $orgStmt->fetch(PDO::FETCH_ASSOC);
        if ($org === false || empty($org['nrs_enabled'])) {
            return;
        }

        $base = trim((string) ($org['nrs_api_base_url'] ?? ''));
        if ($base === '') {
            $this->recordSync($invoiceId, $organizationId, 'failed', 'NRS API base URL is not set.');

            return;
        }

        try {
            $payload = [
                'invoice' => $inv,
                'nrs_tenant_external_id' => $org['nrs_tenant_external_id'],
            ];
            $ok = $this->postJson($base, (string) ($org['nrs_bearer_token'] ?? ''), $payload);
        } catch (\Throwable $e) {
            error_log('NRS sync payload: ' . $e->getMessage());
            $ok = false;
        }
        $this->recordSync($invoiceId, $organizationId, $ok ? 'sent' : 'failed', $ok ? null : 'HTTP request failed or non-2xx (see server log).');
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function postJson(string $baseUrl, string $bearerToken, array $payload): bool
    {
        $url = rtrim($baseUrl, '/') . '/invoices';
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
