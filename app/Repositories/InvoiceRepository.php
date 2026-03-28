<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Config;
use App\Core\Database;
use App\Support\InvoiceTaxCalculator;
use PDO;
use PDOException;
use RuntimeException;

final class InvoiceRepository
{
    private static ?bool $invoicesHasInvoiceKind = null;

    private static ?bool $invoicesHasDocumentTax = null;

    private static ?bool $invoicesHasCreditedInvoiceId = null;

    public static function supportsDocumentTax(): bool
    {
        return self::invoicesTableHasDocumentTaxColumns();
    }

    private static function invoicesTableHasInvoiceKindColumn(): bool
    {
        if (self::$invoicesHasInvoiceKind !== null) {
            return self::$invoicesHasInvoiceKind;
        }
        $dbName = Config::get('db.database');
        if (!is_string($dbName) || $dbName === '') {
            self::$invoicesHasInvoiceKind = false;

            return false;
        }
        try {
            $stmt = Database::pdo()->prepare(
                'SELECT 1 FROM information_schema.COLUMNS
                 WHERE TABLE_SCHEMA = :schema AND TABLE_NAME = :tbl AND COLUMN_NAME = :col
                 LIMIT 1'
            );
            $stmt->execute([
                'schema' => $dbName,
                'tbl' => 'invoices',
                'col' => 'invoice_kind',
            ]);
            self::$invoicesHasInvoiceKind = (bool) $stmt->fetchColumn();
        } catch (\Throwable) {
            self::$invoicesHasInvoiceKind = false;
        }

        return self::$invoicesHasInvoiceKind;
    }

    private static function invoicesTableHasDocumentTaxColumns(): bool
    {
        if (self::$invoicesHasDocumentTax !== null) {
            return self::$invoicesHasDocumentTax;
        }
        $dbName = Config::get('db.database');
        if (!is_string($dbName) || $dbName === '') {
            self::$invoicesHasDocumentTax = false;

            return false;
        }
        try {
            $stmt = Database::pdo()->prepare(
                'SELECT 1 FROM information_schema.COLUMNS
                 WHERE TABLE_SCHEMA = :schema AND TABLE_NAME = :tbl AND COLUMN_NAME = :col
                 LIMIT 1'
            );
            $stmt->execute([
                'schema' => $dbName,
                'tbl' => 'invoices',
                'col' => 'tax_computation',
            ]);
            self::$invoicesHasDocumentTax = (bool) $stmt->fetchColumn();
        } catch (\Throwable) {
            self::$invoicesHasDocumentTax = false;
        }

        return self::$invoicesHasDocumentTax;
    }

    private static function invoicesTableHasNetPayableColumn(): bool
    {
        return self::invoicesTableHasDocumentTaxColumns();
    }

    private static function invoicesTableHasCreditedInvoiceIdColumn(): bool
    {
        if (self::$invoicesHasCreditedInvoiceId !== null) {
            return self::$invoicesHasCreditedInvoiceId;
        }
        $dbName = Config::get('db.database');
        if (!is_string($dbName) || $dbName === '') {
            self::$invoicesHasCreditedInvoiceId = false;

            return false;
        }
        try {
            $stmt = Database::pdo()->prepare(
                'SELECT 1 FROM information_schema.COLUMNS
                 WHERE TABLE_SCHEMA = :schema AND TABLE_NAME = :tbl AND COLUMN_NAME = :col
                 LIMIT 1'
            );
            $stmt->execute([
                'schema' => $dbName,
                'tbl' => 'invoices',
                'col' => 'credited_invoice_id',
            ]);
            self::$invoicesHasCreditedInvoiceId = (bool) $stmt->fetchColumn();
        } catch (\Throwable) {
            self::$invoicesHasCreditedInvoiceId = false;
        }

        return self::$invoicesHasCreditedInvoiceId;
    }

    /**
     * SQL fragment: restrict to standard invoices when invoice_kind exists; otherwise all rows match.
     */
    private static function sqlStandardInvoiceOnly(): string
    {
        return self::invoicesTableHasInvoiceKindColumn()
            ? "invoice_kind = 'invoice'"
            : '1=1';
    }

    /**
     * Advance a row still in draft to sent, paid, or void (used after create/update from the form).
     *
     * @throws \InvalidArgumentException when sent/paid requires a client
     */
    private function applyInvoiceFormStatus(
        \PDO $pdo,
        int $invoiceId,
        int $organizationId,
        string $targetStatus,
        ?int $clientId,
    ): void {
        $allowed = ['draft', 'sent', 'paid', 'void'];
        if (!in_array($targetStatus, $allowed, true)) {
            $targetStatus = 'draft';
        }
        if ($targetStatus === 'draft') {
            return;
        }
        if (in_array($targetStatus, ['sent', 'paid'], true) && ($clientId === null || $clientId <= 0)) {
            throw new \InvalidArgumentException('Select a client before marking this invoice as sent or paid.');
        }

        if ($targetStatus === 'sent') {
            $stmt = $pdo->prepare(
                'UPDATE invoices SET
                    status = \'sent\',
                    sent_at = COALESCE(sent_at, CURRENT_TIMESTAMP),
                    paid_at = NULL,
                    updated_at = CURRENT_TIMESTAMP
                 WHERE id = :id AND organization_id = :organization_id AND status = \'draft\''
            );
        } elseif ($targetStatus === 'paid') {
            $stmt = $pdo->prepare(
                'UPDATE invoices SET
                    status = \'paid\',
                    sent_at = COALESCE(sent_at, CURRENT_TIMESTAMP),
                    paid_at = CURRENT_TIMESTAMP,
                    updated_at = CURRENT_TIMESTAMP
                 WHERE id = :id AND organization_id = :organization_id AND status = \'draft\''
            );
        } else {
            $stmt = $pdo->prepare(
                'UPDATE invoices SET
                    status = \'void\',
                    updated_at = CURRENT_TIMESTAMP
                 WHERE id = :id AND organization_id = :organization_id AND status = \'draft\''
            );
        }

        $stmt->execute(['id' => $invoiceId, 'organization_id' => $organizationId]);
        if ($stmt->rowCount() === 0) {
            throw new \RuntimeException('Could not update invoice status.');
        }
    }

    /** @return list<array<string, mixed>> */
    public function listForOrganization(int $organizationId): array
    {
        $kindSelect = self::invoicesTableHasInvoiceKindColumn()
            ? 'i.invoice_kind'
            : "'invoice' AS invoice_kind";
        $np = self::invoicesTableHasNetPayableColumn() ? 'i.net_payable' : 'i.total AS net_payable';
        $stmt = Database::pdo()->prepare(
            "SELECT i.id, i.invoice_number, i.status, {$kindSelect}, i.issue_date, i.due_date, i.currency,
                    i.subtotal, i.tax_total, i.total, {$np}, i.client_id, i.sent_at, i.paid_at, i.created_at,
                    c.name AS client_name, c.company_name AS client_company
             FROM invoices i
             LEFT JOIN clients c ON c.id = i.client_id
             WHERE i.organization_id = :organization_id
             ORDER BY i.created_at DESC, i.id DESC"
        );
        $stmt->execute(['organization_id' => $organizationId]);
        /** @var list<array<string, mixed>> */
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /** @return list<array<string, mixed>> */
    public function recentForOrganization(int $organizationId, int $limit = 8): array
    {
        $limit = max(1, min(25, $limit));
        $kindSelect = self::invoicesTableHasInvoiceKindColumn()
            ? 'i.invoice_kind'
            : "'invoice' AS invoice_kind";
        $sql = "SELECT i.id, i.invoice_number, i.status, {$kindSelect}, i.issue_date, i.due_date, i.currency,
                       i.total, i.client_id, i.created_at,
                       c.name AS client_name, c.company_name AS client_company
                FROM invoices i
                LEFT JOIN clients c ON c.id = i.client_id
                WHERE i.organization_id = :organization_id
                ORDER BY i.created_at DESC, i.id DESC
                LIMIT {$limit}";
        $stmt = Database::pdo()->prepare($sql);
        $stmt->execute(['organization_id' => $organizationId]);
        /** @var list<array<string, mixed>> */
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * @return array<string, mixed>|null invoice row with key lines (list)
     */
    public function findWithLines(int $invoiceId, int $organizationId): ?array
    {
        $pdo = Database::pdo();
        $joinWht = '';
        if (self::invoicesTableHasDocumentTaxColumns()) {
            $joinWht = ' LEFT JOIN tax_configs tc_wht ON tc_wht.id = i.wht_id';
        }
        $whtSelect = self::invoicesTableHasDocumentTaxColumns()
            ? ', tc_wht.name AS wht_type_name'
            : '';
        $creditSelect = self::invoicesTableHasCreditedInvoiceIdColumn()
            ? 'cred_ref.invoice_number AS credited_invoice_number'
            : 'NULL AS credited_invoice_number';
        $creditJoin = self::invoicesTableHasCreditedInvoiceIdColumn()
            ? 'LEFT JOIN invoices cred_ref ON cred_ref.id = i.credited_invoice_id'
            : '';
        $stmt = $pdo->prepare(
            "SELECT i.*, c.name AS client_name, c.company_name AS client_company, c.email AS client_email,
                    {$creditSelect}
                    {$whtSelect}
             FROM invoices i
             LEFT JOIN clients c ON c.id = i.client_id
             {$creditJoin}
             {$joinWht}
             WHERE i.id = :id AND i.organization_id = :organization_id
             LIMIT 1"
        );
        $stmt->execute(['id' => $invoiceId, 'organization_id' => $organizationId]);
        $invoice = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($invoice === false) {
            return null;
        }

        $lineStmt = $pdo->prepare(
            'SELECT id, line_order, description, quantity, unit_amount, tax_rate,
                    line_subtotal, line_tax, line_total
             FROM invoice_line_items
             WHERE invoice_id = :invoice_id
             ORDER BY line_order ASC, id ASC'
        );
        $lineStmt->execute(['invoice_id' => $invoiceId]);
        /** @var list<array<string, mixed>> */
        $lines = $lineStmt->fetchAll(PDO::FETCH_ASSOC);
        $invoice['lines'] = $lines;

        return $invoice;
    }

    /**
     * @param list<array{description:string,quantity:float,unit_amount:float,tax_rate:float}> $lines
     * @param array{
     *   apply_vat: bool,
     *   vat_rate: float,
     *   apply_wht: bool,
     *   wht_id: ?int,
     *   wht_rate: float
     * }|null $documentTax document-level VAT/WHT (standard invoices only)
     * @param 'draft'|'sent'|'paid'|'void' $targetStatus status after insert (draft = leave as draft)
     */
    public function create(
        int $organizationId,
        ?int $clientId,
        string $issueDate,
        ?string $dueDate,
        string $currency,
        ?string $notes,
        array $lines,
        string $invoiceKind = 'invoice',
        ?int $creditedInvoiceId = null,
        ?array $documentTax = null,
        string $targetStatus = 'draft',
    ): int {
        $pdo = Database::pdo();
        $kind = $invoiceKind === 'credit_note' ? 'credit_note' : 'invoice';
        $hasDocCols = self::invoicesTableHasDocumentTaxColumns();
        $useDocument = $hasDocCols && $kind === 'invoice' && is_array($documentTax);

        if ($useDocument) {
            $enriched = self::enrichLinesWithAmounts($lines, true);
            $totals = self::sumDocumentMoneyTotals($enriched, $documentTax);
        } else {
            $enriched = self::enrichLinesWithAmounts($lines, false);
            $totals = self::sumMoneyTotals($enriched);
        }

        try {
            $pdo->beginTransaction();

            $invoiceNumber = $this->allocateInvoiceNumber($pdo, $organizationId);
            $hasKind = self::invoicesTableHasInvoiceKindColumn();
            $hasCreditId = self::invoicesTableHasCreditedInvoiceIdColumn();

            if ($hasDocCols) {
                if ($useDocument) {
                    if ($hasKind && $hasCreditId) {
                        $stmt = $pdo->prepare(
                            'INSERT INTO invoices (
                                organization_id, client_id, invoice_number, status,
                                invoice_kind, credited_invoice_id,
                                issue_date, due_date, currency, notes,
                                subtotal, tax_total, total,
                                tax_computation, apply_vat, vat_rate, apply_wht, wht_id,
                                vat_amount, wht_amount, net_payable
                            ) VALUES (
                                :organization_id, :client_id, :invoice_number, \'draft\',
                                :invoice_kind, :credited_invoice_id,
                                :issue_date, :due_date, :currency, :notes,
                                :subtotal, :tax_total, :total,
                                \'document\', :apply_vat, :vat_rate, :apply_wht, :wht_id,
                                :vat_amount, :wht_amount, :net_payable
                            )'
                        );
                        $stmt->execute([
                            'organization_id' => $organizationId,
                            'client_id' => $clientId,
                            'invoice_number' => $invoiceNumber,
                            'invoice_kind' => $kind,
                            'credited_invoice_id' => $creditedInvoiceId,
                            'issue_date' => $issueDate,
                            'due_date' => $dueDate,
                            'currency' => $currency,
                            'notes' => $notes,
                            'subtotal' => $totals['subtotal'],
                            'tax_total' => $totals['tax_total'],
                            'total' => $totals['total'],
                            'apply_vat' => !empty($documentTax['apply_vat']) ? 1 : 0,
                            'vat_rate' => number_format((float) ($documentTax['vat_rate'] ?? 0), 4, '.', ''),
                            'apply_wht' => !empty($documentTax['apply_wht']) ? 1 : 0,
                            'wht_id' => !empty($documentTax['apply_wht']) && !empty($documentTax['wht_id'])
                                ? (int) $documentTax['wht_id'] : null,
                            'vat_amount' => $totals['vat_amount'],
                            'wht_amount' => $totals['wht_amount'],
                            'net_payable' => $totals['net_payable'],
                        ]);
                    } elseif ($hasKind) {
                        $stmt = $pdo->prepare(
                            'INSERT INTO invoices (
                                organization_id, client_id, invoice_number, status,
                                invoice_kind,
                                issue_date, due_date, currency, notes,
                                subtotal, tax_total, total,
                                tax_computation, apply_vat, vat_rate, apply_wht, wht_id,
                                vat_amount, wht_amount, net_payable
                            ) VALUES (
                                :organization_id, :client_id, :invoice_number, \'draft\',
                                :invoice_kind,
                                :issue_date, :due_date, :currency, :notes,
                                :subtotal, :tax_total, :total,
                                \'document\', :apply_vat, :vat_rate, :apply_wht, :wht_id,
                                :vat_amount, :wht_amount, :net_payable
                            )'
                        );
                        $stmt->execute([
                            'organization_id' => $organizationId,
                            'client_id' => $clientId,
                            'invoice_number' => $invoiceNumber,
                            'invoice_kind' => $kind,
                            'issue_date' => $issueDate,
                            'due_date' => $dueDate,
                            'currency' => $currency,
                            'notes' => $notes,
                            'subtotal' => $totals['subtotal'],
                            'tax_total' => $totals['tax_total'],
                            'total' => $totals['total'],
                            'apply_vat' => !empty($documentTax['apply_vat']) ? 1 : 0,
                            'vat_rate' => number_format((float) ($documentTax['vat_rate'] ?? 0), 4, '.', ''),
                            'apply_wht' => !empty($documentTax['apply_wht']) ? 1 : 0,
                            'wht_id' => !empty($documentTax['apply_wht']) && !empty($documentTax['wht_id'])
                                ? (int) $documentTax['wht_id'] : null,
                            'vat_amount' => $totals['vat_amount'],
                            'wht_amount' => $totals['wht_amount'],
                            'net_payable' => $totals['net_payable'],
                        ]);
                    } else {
                        $stmt = $pdo->prepare(
                            'INSERT INTO invoices (
                                organization_id, client_id, invoice_number, status,
                                issue_date, due_date, currency, notes,
                                subtotal, tax_total, total,
                                tax_computation, apply_vat, vat_rate, apply_wht, wht_id,
                                vat_amount, wht_amount, net_payable
                            ) VALUES (
                                :organization_id, :client_id, :invoice_number, \'draft\',
                                :issue_date, :due_date, :currency, :notes,
                                :subtotal, :tax_total, :total,
                                \'document\', :apply_vat, :vat_rate, :apply_wht, :wht_id,
                                :vat_amount, :wht_amount, :net_payable
                            )'
                        );
                        $stmt->execute([
                            'organization_id' => $organizationId,
                            'client_id' => $clientId,
                            'invoice_number' => $invoiceNumber,
                            'issue_date' => $issueDate,
                            'due_date' => $dueDate,
                            'currency' => $currency,
                            'notes' => $notes,
                            'subtotal' => $totals['subtotal'],
                            'tax_total' => $totals['tax_total'],
                            'total' => $totals['total'],
                            'apply_vat' => !empty($documentTax['apply_vat']) ? 1 : 0,
                            'vat_rate' => number_format((float) ($documentTax['vat_rate'] ?? 0), 4, '.', ''),
                            'apply_wht' => !empty($documentTax['apply_wht']) ? 1 : 0,
                            'wht_id' => !empty($documentTax['apply_wht']) && !empty($documentTax['wht_id'])
                                ? (int) $documentTax['wht_id'] : null,
                            'vat_amount' => $totals['vat_amount'],
                            'wht_amount' => $totals['wht_amount'],
                            'net_payable' => $totals['net_payable'],
                        ]);
                    }
                } elseif ($hasKind && $hasCreditId) {
                    $stmt = $pdo->prepare(
                        'INSERT INTO invoices (
                            organization_id, client_id, invoice_number, status,
                            invoice_kind, credited_invoice_id,
                            issue_date, due_date, currency, notes,
                            subtotal, tax_total, total,
                            tax_computation, apply_vat, vat_rate, apply_wht, wht_id,
                            vat_amount, wht_amount, net_payable
                        ) VALUES (
                            :organization_id, :client_id, :invoice_number, \'draft\',
                            :invoice_kind, :credited_invoice_id,
                            :issue_date, :due_date, :currency, :notes,
                            :subtotal, :tax_total, :total,
                            \'line\', 0, 0, 0, NULL,
                            0, 0, :net_payable
                        )'
                    );
                    $stmt->execute([
                        'organization_id' => $organizationId,
                        'client_id' => $clientId,
                        'invoice_number' => $invoiceNumber,
                        'invoice_kind' => $kind,
                        'credited_invoice_id' => $creditedInvoiceId,
                        'issue_date' => $issueDate,
                        'due_date' => $dueDate,
                        'currency' => $currency,
                        'notes' => $notes,
                        'subtotal' => $totals['subtotal'],
                        'tax_total' => $totals['tax_total'],
                        'total' => $totals['total'],
                        'net_payable' => $totals['total'],
                    ]);
                } elseif ($hasKind) {
                    $stmt = $pdo->prepare(
                        'INSERT INTO invoices (
                            organization_id, client_id, invoice_number, status,
                            invoice_kind,
                            issue_date, due_date, currency, notes,
                            subtotal, tax_total, total,
                            tax_computation, apply_vat, vat_rate, apply_wht, wht_id,
                            vat_amount, wht_amount, net_payable
                        ) VALUES (
                            :organization_id, :client_id, :invoice_number, \'draft\',
                            :invoice_kind,
                            :issue_date, :due_date, :currency, :notes,
                            :subtotal, :tax_total, :total,
                            \'line\', 0, 0, 0, NULL,
                            0, 0, :net_payable
                        )'
                    );
                    $stmt->execute([
                        'organization_id' => $organizationId,
                        'client_id' => $clientId,
                        'invoice_number' => $invoiceNumber,
                        'invoice_kind' => $kind,
                        'issue_date' => $issueDate,
                        'due_date' => $dueDate,
                        'currency' => $currency,
                        'notes' => $notes,
                        'subtotal' => $totals['subtotal'],
                        'tax_total' => $totals['tax_total'],
                        'total' => $totals['total'],
                        'net_payable' => $totals['total'],
                    ]);
                } else {
                    $stmt = $pdo->prepare(
                        'INSERT INTO invoices (
                            organization_id, client_id, invoice_number, status,
                            issue_date, due_date, currency, notes,
                            subtotal, tax_total, total,
                            tax_computation, apply_vat, vat_rate, apply_wht, wht_id,
                            vat_amount, wht_amount, net_payable
                        ) VALUES (
                            :organization_id, :client_id, :invoice_number, \'draft\',
                            :issue_date, :due_date, :currency, :notes,
                            :subtotal, :tax_total, :total,
                            \'line\', 0, 0, 0, NULL,
                            0, 0, :net_payable
                        )'
                    );
                    $stmt->execute([
                        'organization_id' => $organizationId,
                        'client_id' => $clientId,
                        'invoice_number' => $invoiceNumber,
                        'issue_date' => $issueDate,
                        'due_date' => $dueDate,
                        'currency' => $currency,
                        'notes' => $notes,
                        'subtotal' => $totals['subtotal'],
                        'tax_total' => $totals['tax_total'],
                        'total' => $totals['total'],
                        'net_payable' => $totals['total'],
                    ]);
                }
            } elseif ($hasKind && $hasCreditId) {
                $stmt = $pdo->prepare(
                    'INSERT INTO invoices (
                        organization_id, client_id, invoice_number, status,
                        invoice_kind, credited_invoice_id,
                        issue_date, due_date, currency, notes,
                        subtotal, tax_total, total
                    ) VALUES (
                        :organization_id, :client_id, :invoice_number, \'draft\',
                        :invoice_kind, :credited_invoice_id,
                        :issue_date, :due_date, :currency, :notes,
                        :subtotal, :tax_total, :total
                    )'
                );
                $stmt->execute([
                    'organization_id' => $organizationId,
                    'client_id' => $clientId,
                    'invoice_number' => $invoiceNumber,
                    'invoice_kind' => $kind,
                    'credited_invoice_id' => $creditedInvoiceId,
                    'issue_date' => $issueDate,
                    'due_date' => $dueDate,
                    'currency' => $currency,
                    'notes' => $notes,
                    'subtotal' => $totals['subtotal'],
                    'tax_total' => $totals['tax_total'],
                    'total' => $totals['total'],
                ]);
            } elseif ($hasKind) {
                $stmt = $pdo->prepare(
                    'INSERT INTO invoices (
                        organization_id, client_id, invoice_number, status,
                        invoice_kind,
                        issue_date, due_date, currency, notes,
                        subtotal, tax_total, total
                    ) VALUES (
                        :organization_id, :client_id, :invoice_number, \'draft\',
                        :invoice_kind,
                        :issue_date, :due_date, :currency, :notes,
                        :subtotal, :tax_total, :total
                    )'
                );
                $stmt->execute([
                    'organization_id' => $organizationId,
                    'client_id' => $clientId,
                    'invoice_number' => $invoiceNumber,
                    'invoice_kind' => $kind,
                    'issue_date' => $issueDate,
                    'due_date' => $dueDate,
                    'currency' => $currency,
                    'notes' => $notes,
                    'subtotal' => $totals['subtotal'],
                    'tax_total' => $totals['tax_total'],
                    'total' => $totals['total'],
                ]);
            } else {
                $stmt = $pdo->prepare(
                    'INSERT INTO invoices (
                        organization_id, client_id, invoice_number, status,
                        issue_date, due_date, currency, notes,
                        subtotal, tax_total, total
                    ) VALUES (
                        :organization_id, :client_id, :invoice_number, \'draft\',
                        :issue_date, :due_date, :currency, :notes,
                        :subtotal, :tax_total, :total
                    )'
                );
                $stmt->execute([
                    'organization_id' => $organizationId,
                    'client_id' => $clientId,
                    'invoice_number' => $invoiceNumber,
                    'issue_date' => $issueDate,
                    'due_date' => $dueDate,
                    'currency' => $currency,
                    'notes' => $notes,
                    'subtotal' => $totals['subtotal'],
                    'tax_total' => $totals['tax_total'],
                    'total' => $totals['total'],
                ]);
            }
            $invoiceId = (int) $pdo->lastInsertId();
            $this->insertLineItems($pdo, $invoiceId, $enriched);
            $this->applyInvoiceFormStatus($pdo, $invoiceId, $organizationId, $targetStatus, $clientId);

            $pdo->commit();
        } catch (\InvalidArgumentException $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        } catch (PDOException $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }

        return $invoiceId;
    }

    /**
     * Draft credit note mirroring line items (negated unit prices). Source must be sent/paid standard invoice.
     *
     * @throws RuntimeException
     */
    public function createCreditNoteFromSource(int $organizationId, int $sourceInvoiceId): int
    {
        $src = $this->findWithLines($sourceInvoiceId, $organizationId);
        if ($src === null) {
            throw new RuntimeException('Invoice not found.');
        }
        $kind = (string) ($src['invoice_kind'] ?? 'invoice');
        $status = (string) ($src['status'] ?? '');
        if ($kind !== 'invoice' || !in_array($status, ['sent', 'paid'], true)) {
            throw new RuntimeException('Credit notes can only be started from sent or paid standard invoices.');
        }

        $linesIn = [];
        /** @var list<array<string, mixed>> $srcLines */
        $srcLines = isset($src['lines']) && is_array($src['lines']) ? $src['lines'] : [];
        foreach ($srcLines as $ln) {
            $u = (float) ($ln['unit_amount'] ?? 0);
            $linesIn[] = [
                'description' => (string) ($ln['description'] ?? 'Credit'),
                'quantity' => abs((float) ($ln['quantity'] ?? 1)) ?: 1.0,
                'unit_amount' => $u > 0 ? -1 * $u : $u,
                'tax_rate' => (float) ($ln['tax_rate'] ?? 0),
            ];
        }
        if ($linesIn === []) {
            throw new RuntimeException('Invoice has no lines to credit.');
        }

        $today = (new \DateTimeImmutable('today'))->format('Y-m-d');
        $note = 'Credit note for ' . (string) ($src['invoice_number'] ?? '') . '.';
        $clientId = isset($src['client_id']) ? (int) $src['client_id'] : null;
        if ($clientId <= 0) {
            $clientId = null;
        }

        return $this->create(
            $organizationId,
            $clientId,
            $today,
            null,
            (string) ($src['currency'] ?? 'NGN'),
            $note,
            $linesIn,
            'credit_note',
            $sourceInvoiceId,
            null,
        );
    }

    /**
     * @param list<array{description:string,quantity:float,unit_amount:float,tax_rate:float}> $lines
     * @param array{
     *   apply_vat: bool,
     *   vat_rate: float,
     *   apply_wht: bool,
     *   wht_id: ?int,
     *   wht_rate: float
     * }|null $documentTax used when invoice uses document tax computation
     * @param 'draft'|'sent'|'paid'|'void' $targetStatus status after save (draft = leave as draft)
     */
    public function updateDraft(
        int $invoiceId,
        int $organizationId,
        ?int $clientId,
        string $issueDate,
        ?string $dueDate,
        string $currency,
        ?string $notes,
        array $lines,
        ?array $documentTax = null,
        string $targetStatus = 'draft',
    ): bool {
        $pdo = Database::pdo();
        $hasDocCols = self::invoicesTableHasDocumentTaxColumns();
        $hasKind = self::invoicesTableHasInvoiceKindColumn();
        $kindSql = $hasKind ? 'invoice_kind' : '\'invoice\' AS invoice_kind';
        $taxSql = $hasDocCols
            ? 'COALESCE(tax_computation, \'line\') AS tax_computation'
            : '\'line\' AS tax_computation';

        try {
            $pdo->beginTransaction();

            $checkSql = "SELECT id, {$kindSql}, {$taxSql} FROM invoices WHERE id = :id AND organization_id = :organization_id AND status = 'draft' LIMIT 1";
            $check = $pdo->prepare($checkSql);
            $check->execute(['id' => $invoiceId, 'organization_id' => $organizationId]);
            /** @var array<string, mixed>|false $row */
            $row = $check->fetch(PDO::FETCH_ASSOC);
            if ($row === false) {
                $pdo->rollBack();

                return false;
            }

            $invKind = (string) ($row['invoice_kind'] ?? 'invoice');
            $taxComp = (string) ($row['tax_computation'] ?? 'line');
            $useDocument = $hasDocCols && $invKind === 'invoice' && $taxComp === 'document' && is_array($documentTax);

            if ($useDocument) {
                $enriched = self::enrichLinesWithAmounts($lines, true);
                $totals = self::sumDocumentMoneyTotals($enriched, $documentTax);
            } else {
                $enriched = self::enrichLinesWithAmounts($lines, false);
                $totals = self::sumMoneyTotals($enriched);
            }

            if ($hasDocCols && $useDocument) {
                $upd = $pdo->prepare(
                    'UPDATE invoices SET
                        client_id = :client_id,
                        issue_date = :issue_date,
                        due_date = :due_date,
                        currency = :currency,
                        notes = :notes,
                        subtotal = :subtotal,
                        tax_total = :tax_total,
                        total = :total,
                        apply_vat = :apply_vat,
                        vat_rate = :vat_rate,
                        apply_wht = :apply_wht,
                        wht_id = :wht_id,
                        vat_amount = :vat_amount,
                        wht_amount = :wht_amount,
                        net_payable = :net_payable,
                        updated_at = CURRENT_TIMESTAMP
                     WHERE id = :id AND organization_id = :organization_id AND status = \'draft\''
                );
                $upd->execute([
                    'id' => $invoiceId,
                    'organization_id' => $organizationId,
                    'client_id' => $clientId,
                    'issue_date' => $issueDate,
                    'due_date' => $dueDate,
                    'currency' => $currency,
                    'notes' => $notes,
                    'subtotal' => $totals['subtotal'],
                    'tax_total' => $totals['tax_total'],
                    'total' => $totals['total'],
                    'apply_vat' => !empty($documentTax['apply_vat']) ? 1 : 0,
                    'vat_rate' => number_format((float) ($documentTax['vat_rate'] ?? 0), 4, '.', ''),
                    'apply_wht' => !empty($documentTax['apply_wht']) ? 1 : 0,
                    'wht_id' => !empty($documentTax['apply_wht']) && !empty($documentTax['wht_id'])
                        ? (int) $documentTax['wht_id'] : null,
                    'vat_amount' => $totals['vat_amount'],
                    'wht_amount' => $totals['wht_amount'],
                    'net_payable' => $totals['net_payable'],
                ]);
            } elseif ($hasDocCols) {
                $upd = $pdo->prepare(
                    'UPDATE invoices SET
                        client_id = :client_id,
                        issue_date = :issue_date,
                        due_date = :due_date,
                        currency = :currency,
                        notes = :notes,
                        subtotal = :subtotal,
                        tax_total = :tax_total,
                        total = :total,
                        net_payable = :net_payable,
                        updated_at = CURRENT_TIMESTAMP
                     WHERE id = :id AND organization_id = :organization_id AND status = \'draft\''
                );
                $upd->execute([
                    'id' => $invoiceId,
                    'organization_id' => $organizationId,
                    'client_id' => $clientId,
                    'issue_date' => $issueDate,
                    'due_date' => $dueDate,
                    'currency' => $currency,
                    'notes' => $notes,
                    'subtotal' => $totals['subtotal'],
                    'tax_total' => $totals['tax_total'],
                    'total' => $totals['total'],
                    'net_payable' => $totals['total'],
                ]);
            } else {
                $upd = $pdo->prepare(
                    'UPDATE invoices SET
                        client_id = :client_id,
                        issue_date = :issue_date,
                        due_date = :due_date,
                        currency = :currency,
                        notes = :notes,
                        subtotal = :subtotal,
                        tax_total = :tax_total,
                        total = :total,
                        updated_at = CURRENT_TIMESTAMP
                     WHERE id = :id AND organization_id = :organization_id AND status = \'draft\''
                );
                $upd->execute([
                    'id' => $invoiceId,
                    'organization_id' => $organizationId,
                    'client_id' => $clientId,
                    'issue_date' => $issueDate,
                    'due_date' => $dueDate,
                    'currency' => $currency,
                    'notes' => $notes,
                    'subtotal' => $totals['subtotal'],
                    'tax_total' => $totals['tax_total'],
                    'total' => $totals['total'],
                ]);
            }
            if ($upd->rowCount() === 0) {
                $pdo->rollBack();

                return false;
            }

            $pdo->prepare('DELETE FROM invoice_line_items WHERE invoice_id = :invoice_id')
                ->execute(['invoice_id' => $invoiceId]);
            $this->insertLineItems($pdo, $invoiceId, $enriched);
            $this->applyInvoiceFormStatus($pdo, $invoiceId, $organizationId, $targetStatus, $clientId);

            $pdo->commit();
        } catch (\InvalidArgumentException $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        } catch (PDOException $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }

        return true;
    }

    public function deleteDraft(int $invoiceId, int $organizationId): bool
    {
        $stmt = Database::pdo()->prepare(
            'DELETE FROM invoices WHERE id = :id AND organization_id = :organization_id AND status = \'draft\''
        );
        $stmt->execute(['id' => $invoiceId, 'organization_id' => $organizationId]);

        return $stmt->rowCount() > 0;
    }

    public function setGatewayPendingCheckout(int $invoiceId, int $organizationId, string $provider, string $checkoutRef): bool
    {
        $kindPred = self::sqlStandardInvoiceOnly();
        $stmt = Database::pdo()->prepare(
            "UPDATE invoices SET
                payment_provider = :provider,
                gateway_checkout_ref = :cref,
                updated_at = CURRENT_TIMESTAMP
             WHERE id = :id AND organization_id = :organization_id AND status = 'sent' AND {$kindPred}"
        );
        $stmt->execute([
            'provider' => $provider,
            'cref' => $checkoutRef,
            'id' => $invoiceId,
            'organization_id' => $organizationId,
        ]);

        return $stmt->rowCount() > 0;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findByGatewayCheckoutRef(string $checkoutRef): ?array
    {
        $np = self::invoicesTableHasNetPayableColumn() ? ', net_payable, tax_computation' : '';
        $kindSel = self::invoicesTableHasInvoiceKindColumn() ? 'invoice_kind' : "'invoice' AS invoice_kind";
        $stmt = Database::pdo()->prepare(
            "SELECT id, organization_id, status, {$kindSel}, total{$np} FROM invoices
             WHERE gateway_checkout_ref = :cref LIMIT 1"
        );
        $stmt->execute(['cref' => $checkoutRef]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    public function markPaidFromGateway(int $invoiceId, int $organizationId, string $transactionRef, string $provider): bool
    {
        $amountPred = 'total > 0';
        if (self::invoicesTableHasNetPayableColumn()) {
            $amountPred = 'net_payable > 0';
        }
        $kindPred = self::sqlStandardInvoiceOnly();
        $stmt = Database::pdo()->prepare(
            "UPDATE invoices SET
                status = 'paid',
                paid_at = CURRENT_TIMESTAMP,
                gateway_transaction_ref = :tx,
                payment_provider = COALESCE(payment_provider, :provider),
                updated_at = CURRENT_TIMESTAMP
             WHERE id = :id AND organization_id = :organization_id
               AND status = 'sent'
               AND {$kindPred}
               AND {$amountPred}"
        );
        $stmt->execute([
            'id' => $invoiceId,
            'organization_id' => $organizationId,
            'tx' => $transactionRef,
            'provider' => $provider,
        ]);

        return $stmt->rowCount() > 0;
    }

    public function markSent(int $invoiceId, int $organizationId): bool
    {
        $stmt = Database::pdo()->prepare(
            'UPDATE invoices SET
                status = \'sent\',
                sent_at = CURRENT_TIMESTAMP,
                updated_at = CURRENT_TIMESTAMP
             WHERE id = :id AND organization_id = :organization_id
               AND status = \'draft\'
               AND client_id IS NOT NULL'
        );
        $stmt->execute(['id' => $invoiceId, 'organization_id' => $organizationId]);

        return $stmt->rowCount() > 0;
    }

    public function markPaid(int $invoiceId, int $organizationId): bool
    {
        $kindPred = self::sqlStandardInvoiceOnly();
        $stmt = Database::pdo()->prepare(
            "UPDATE invoices SET
                status = 'paid',
                paid_at = CURRENT_TIMESTAMP,
                updated_at = CURRENT_TIMESTAMP
             WHERE id = :id AND organization_id = :organization_id
               AND status = 'sent'
               AND {$kindPred}"
        );
        $stmt->execute(['id' => $invoiceId, 'organization_id' => $organizationId]);

        return $stmt->rowCount() > 0;
    }

    public function markVoid(int $invoiceId, int $organizationId): bool
    {
        $stmt = Database::pdo()->prepare(
            'UPDATE invoices SET
                status = \'void\',
                updated_at = CURRENT_TIMESTAMP
             WHERE id = :id AND organization_id = :organization_id
               AND status IN (\'draft\', \'sent\')'
        );
        $stmt->execute(['id' => $invoiceId, 'organization_id' => $organizationId]);

        return $stmt->rowCount() > 0;
    }

    /**
     * @param list<array{line_subtotal:float,line_tax:float,line_total:float,...}> $enriched
     * @return array{subtotal:string,tax_total:string,total:string}
     */
    public static function sumMoneyTotals(array $enriched): array
    {
        $subtotal = 0.0;
        $taxTotal = 0.0;
        foreach ($enriched as $line) {
            $subtotal += (float) $line['line_subtotal'];
            $taxTotal += (float) $line['line_tax'];
        }
        $total = $subtotal + $taxTotal;

        return [
            'subtotal' => number_format($subtotal, 2, '.', ''),
            'tax_total' => number_format($taxTotal, 2, '.', ''),
            'total' => number_format($total, 2, '.', ''),
        ];
    }

    /**
     * @param list<array{line_subtotal:float,...}> $enriched
     * @param array{
     *   apply_vat: bool,
     *   vat_rate: float,
     *   apply_wht: bool,
     *   wht_id: ?int,
     *   wht_rate: float
     * } $documentTax
     * @return array{
     *   subtotal:string,
     *   tax_total:string,
     *   total:string,
     *   vat_amount:string,
     *   wht_amount:string,
     *   net_payable:string
     * }
     */
    public static function sumDocumentMoneyTotals(array $enriched, array $documentTax): array
    {
        $subtotal = 0.0;
        foreach ($enriched as $line) {
            $subtotal += (float) $line['line_subtotal'];
        }
        $applyVat = !empty($documentTax['apply_vat']);
        $vatRate = $applyVat ? (float) ($documentTax['vat_rate'] ?? 0) : 0.0;
        $applyWht = !empty($documentTax['apply_wht']);
        $whtRate = $applyWht ? (float) ($documentTax['wht_rate'] ?? 0) : 0.0;

        $calc = InvoiceTaxCalculator::compute($subtotal, $applyVat, $vatRate, $applyWht, $whtRate);

        return [
            'subtotal' => number_format($subtotal, 2, '.', ''),
            'tax_total' => $calc['vat_amount'],
            'total' => $calc['gross_total'],
            'vat_amount' => $calc['vat_amount'],
            'wht_amount' => $calc['wht_amount'],
            'net_payable' => $calc['net_payable'],
        ];
    }

    /**
     * @param list<array{description:string,quantity:float,unit_amount:float,tax_rate:float}> $lines
     * @return list<array{description:string,quantity:float,unit_amount:float,tax_rate:float,line_subtotal:float,line_tax:float,line_total:float}>
     */
    public static function enrichLinesWithAmounts(array $lines, bool $zeroLineTax = false): array
    {
        $out = [];
        foreach ($lines as $line) {
            $qty = (float) $line['quantity'];
            $unit = (float) $line['unit_amount'];
            $rate = $zeroLineTax ? 0.0 : (float) $line['tax_rate'];
            $lineSub = round($qty * $unit, 2);
            $lineTax = round($lineSub * ($rate / 100.0), 2);
            $lineTot = round($lineSub + $lineTax, 2);
            $out[] = [
                'description' => $line['description'],
                'quantity' => $qty,
                'unit_amount' => $unit,
                'tax_rate' => $rate,
                'line_subtotal' => $lineSub,
                'line_tax' => $lineTax,
                'line_total' => $lineTot,
            ];
        }

        return $out;
    }

    private function allocateInvoiceNumber(PDO $pdo, int $organizationId): string
    {
        $stmt = $pdo->prepare(
            'SELECT invoice_number_prefix, invoice_next_number FROM organizations WHERE id = :id FOR UPDATE'
        );
        $stmt->execute(['id' => $organizationId]);
        /** @var array<string, mixed>|false $row */
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row === false) {
            throw new RuntimeException('Organization not found.');
        }
        $prefix = trim((string) $row['invoice_number_prefix']);
        if ($prefix === '') {
            $prefix = 'INV';
        }
        $next = (int) $row['invoice_next_number'];
        $number = $prefix . '-' . str_pad((string) $next, 6, '0', STR_PAD_LEFT);

        $upd = $pdo->prepare(
            'UPDATE organizations SET invoice_next_number = invoice_next_number + 1 WHERE id = :id'
        );
        $upd->execute(['id' => $organizationId]);
        if ($upd->rowCount() === 0) {
            throw new RuntimeException('Could not advance invoice number.');
        }

        return $number;
    }

    /**
     * @param PDO $pdo
     * @param list<array{description:string,quantity:float,unit_amount:float,tax_rate:float,line_subtotal:float,line_tax:float,line_total:float}> $enrichedLines
     */
    private function insertLineItems(PDO $pdo, int $invoiceId, array $enrichedLines): void
    {
        $ins = $pdo->prepare(
            'INSERT INTO invoice_line_items (
                invoice_id, line_order, description, quantity, unit_amount, tax_rate,
                line_subtotal, line_tax, line_total
            ) VALUES (
                :invoice_id, :line_order, :description, :quantity, :unit_amount, :tax_rate,
                :line_subtotal, :line_tax, :line_total
            )'
        );
        $order = 0;
        foreach ($enrichedLines as $line) {
            $ins->execute([
                'invoice_id' => $invoiceId,
                'line_order' => $order,
                'description' => $line['description'],
                'quantity' => number_format($line['quantity'], 4, '.', ''),
                'unit_amount' => number_format($line['unit_amount'], 2, '.', ''),
                'tax_rate' => number_format($line['tax_rate'], 3, '.', ''),
                'line_subtotal' => number_format($line['line_subtotal'], 2, '.', ''),
                'line_tax' => number_format($line['line_tax'], 2, '.', ''),
                'line_total' => number_format($line['line_total'], 2, '.', ''),
            ]);
            ++$order;
        }
    }
}
