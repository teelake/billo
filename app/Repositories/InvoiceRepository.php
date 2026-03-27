<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;
use PDO;
use PDOException;
use RuntimeException;

final class InvoiceRepository
{
    /** @return list<array<string, mixed>> */
    public function listForOrganization(int $organizationId): array
    {
        $stmt = Database::pdo()->prepare(
            'SELECT i.id, i.invoice_number, i.status, i.issue_date, i.due_date, i.currency,
                    i.subtotal, i.tax_total, i.total, i.client_id, i.sent_at, i.paid_at, i.created_at,
                    c.name AS client_name, c.company_name AS client_company
             FROM invoices i
             LEFT JOIN clients c ON c.id = i.client_id
             WHERE i.organization_id = :organization_id
             ORDER BY i.created_at DESC, i.id DESC'
        );
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
        $stmt = $pdo->prepare(
            'SELECT i.*, c.name AS client_name, c.company_name AS client_company, c.email AS client_email
             FROM invoices i
             LEFT JOIN clients c ON c.id = i.client_id
             WHERE i.id = :id AND i.organization_id = :organization_id
             LIMIT 1'
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
     */
    public function create(
        int $organizationId,
        ?int $clientId,
        string $issueDate,
        ?string $dueDate,
        string $currency,
        ?string $notes,
        array $lines,
    ): int {
        $enriched = self::enrichLinesWithAmounts($lines);
        $totals = self::sumMoneyTotals($enriched);
        $pdo = Database::pdo();

        try {
            $pdo->beginTransaction();

            $invoiceNumber = $this->allocateInvoiceNumber($pdo, $organizationId);

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
            $invoiceId = (int) $pdo->lastInsertId();
            $this->insertLineItems($pdo, $invoiceId, $enriched);

            $pdo->commit();
        } catch (PDOException $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }

        return $invoiceId;
    }

    /**
     * @param list<array{description:string,quantity:float,unit_amount:float,tax_rate:float}> $lines
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
    ): bool {
        $enriched = self::enrichLinesWithAmounts($lines);
        $totals = self::sumMoneyTotals($enriched);
        $pdo = Database::pdo();

        try {
            $pdo->beginTransaction();

            $check = $pdo->prepare(
                'SELECT id FROM invoices WHERE id = :id AND organization_id = :organization_id AND status = \'draft\' LIMIT 1'
            );
            $check->execute(['id' => $invoiceId, 'organization_id' => $organizationId]);
            if (!$check->fetchColumn()) {
                $pdo->rollBack();

                return false;
            }

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
            if ($upd->rowCount() === 0) {
                $pdo->rollBack();

                return false;
            }

            $pdo->prepare('DELETE FROM invoice_line_items WHERE invoice_id = :invoice_id')
                ->execute(['invoice_id' => $invoiceId]);
            $this->insertLineItems($pdo, $invoiceId, $enriched);

            $pdo->commit();
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
        $stmt = Database::pdo()->prepare(
            'UPDATE invoices SET
                status = \'paid\',
                paid_at = CURRENT_TIMESTAMP,
                updated_at = CURRENT_TIMESTAMP
             WHERE id = :id AND organization_id = :organization_id
               AND status = \'sent\''
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
     * @param list<array{description:string,quantity:float,unit_amount:float,tax_rate:float}> $lines
     * @return list<array{description:string,quantity:float,unit_amount:float,tax_rate:float,line_subtotal:float,line_tax:float,line_total:float}>
     */
    public static function enrichLinesWithAmounts(array $lines): array
    {
        $out = [];
        foreach ($lines as $line) {
            $qty = (float) $line['quantity'];
            $unit = (float) $line['unit_amount'];
            $rate = (float) $line['tax_rate'];
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

