<?php

declare(strict_types=1);

namespace App\Services;

use App\Support\InvoiceTheme;
use Dompdf\Dompdf;
use Dompdf\Options;
use Throwable;

final class InvoicePdfService
{
    /**
     * @param array<string, mixed> $invoice from InvoiceRepository::findWithLines
     * @param array<string, mixed> $organization from OrganizationRepository::findById
     */
    public function render(array $invoice, array $organization): string
    {
        $html = $this->buildHtml($invoice, $organization);

        $logoUrl = isset($organization['invoice_logo_url']) ? trim((string) $organization['invoice_logo_url']) : '';
        $remoteLogo = str_starts_with($logoUrl, 'https://');

        $options = new Options();
        $options->set('defaultFont', 'DejaVu Sans');
        $options->set('isRemoteEnabled', $remoteLogo);
        $options->setChroot(BILLO_ROOT);

        try {
            $dompdf = new Dompdf($options);
            $dompdf->loadHtml($html, 'UTF-8');
            $dompdf->setPaper('A4', 'portrait');
            $dompdf->render();

            return (string) $dompdf->output();
        } catch (Throwable $e) {
            error_log('Billo InvoicePdfService: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * @param array<string, mixed> $invoice
     * @param array<string, mixed> $organization
     */
    private function buildHtml(array $invoice, array $organization): string
    {
        $h = static function (string $s): string {
            return htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        };

        $T = InvoiceTheme::tokens($organization);
        $hideTax = function_exists('billo_invoice_hide_tax_column') && billo_invoice_hide_tax_column($organization, $invoice);

        $muted = $h($T['muted_color']);
        $cellBr = $h($T['cell_border']);
        $theadBg = $h($T['thead_bg']);
        $theadColor = $h($T['thead_color']);
        $theadBorder = $h($T['thead_border']);
        $docLab = $h($T['doc_label_accent']);
        $primary = $h($T['primary']);
        $accent = $h($T['accent']);
        $titlePx = $h($T['title_size']);

        $displayName = trim((string) ($organization['legal_name'] ?? ''));
        if ($displayName === '') {
            $displayName = (string) ($organization['name'] ?? billo_brand_name());
        }

        $logoHtml = $this->logoHtml($organization, $h);

        $addrLines = array_filter([
            $organization['billing_address_line1'] ?? '',
            $organization['billing_address_line2'] ?? '',
            trim(implode(', ', array_filter([
                $organization['billing_city'] ?? '',
                $organization['billing_state'] ?? '',
                $organization['billing_country'] ?? '',
            ], static fn ($v) => is_string($v) && $v !== ''))),
        ], static fn ($v) => is_string($v) && trim($v) !== '');
        $addrHtml = '';
        foreach ($addrLines as $line) {
            $addrHtml .= '<div style="color:' . $muted . ';font-size:11px">' . $h(trim((string) $line)) . '</div>';
        }

        $taxId = trim((string) ($organization['tax_id'] ?? ''));
        $taxHtml = $taxId !== '' ? '<div style="margin-top:8px;font-size:11px;color:' . $muted . '">Tax ID: ' . $h($taxId) . '</div>' : '';
        $cac = trim((string) ($organization['company_registration_number'] ?? ''));
        $cacHtml = $cac !== '' ? '<div style="margin-top:4px;font-size:11px;color:' . $muted . '">CAC / Reg.: ' . $h($cac) . '</div>' : '';
        $web = trim((string) ($organization['company_website'] ?? ''));
        $webHtml = $web !== '' ? '<div style="margin-top:4px;font-size:11px;color:' . $muted . '">Website: ' . $h($web) . '</div>' : '';

        $footer = trim((string) ($organization['invoice_footer'] ?? ''));
        $footerHtml = $footer !== ''
            ? '<div style="margin-top:28px;padding-top:16px;border-top:1px solid ' . $cellBr . ';font-size:10px;color:' . $muted . ';white-space:pre-wrap">'
            . $h($footer) . '</div>' : '';

        $num = (string) ($invoice['invoice_number'] ?? 'Invoice');
        $invKind = (string) ($invoice['invoice_kind'] ?? 'invoice');
        $creditRef = trim((string) ($invoice['credited_invoice_number'] ?? ''));
        $docLabel = $invKind === 'credit_note' ? 'Credit note' : 'Invoice';
        $currency = (string) ($invoice['currency'] ?? 'NGN');
        $issue = (string) ($invoice['issue_date'] ?? '');
        $due = isset($invoice['due_date']) && $invoice['due_date'] !== null && $invoice['due_date'] !== ''
            ? (string) $invoice['due_date'] : '';
        $status = (string) ($invoice['status'] ?? '');

        $clientBits = array_filter([
            $invoice['client_name'] ?? '',
            $invoice['client_company'] ?? '',
        ], static fn ($v) => is_string($v) && $v !== '');
        $clientLine = $clientBits !== [] ? implode(' · ', $clientBits) : '—';

        $leftBoxStyle = 'flex:1;min-width:0';
        if ($T['doc_block_border'] !== 'none') {
            $leftBoxStyle .= ';padding-left:12px;border-left:' . $h($T['doc_block_border']);
        }

        /** @var list<array<string, mixed>> $lines */
        $lines = isset($invoice['lines']) && is_array($invoice['lines']) ? $invoice['lines'] : [];
        $rows = '';
        foreach ($lines as $ln) {
            $desc = (string) ($ln['description'] ?? '');
            $qty = (float) ($ln['quantity'] ?? 0);
            $unit = (float) ($ln['unit_amount'] ?? 0);
            $tax = (float) ($ln['tax_rate'] ?? 0);
            $lt = (float) ($ln['line_total'] ?? 0);
            $qLabel = rtrim(rtrim(sprintf('%.4f', $qty), '0'), '.') ?: '0';
            $tdBr = 'padding:8px 6px;border-bottom:1px solid ' . $cellBr;
            $rows .= '<tr>'
                . '<td style="' . $tdBr . '">' . $h($desc) . '</td>'
                . '<td style="' . $tdBr . ';text-align:right">' . $h($qLabel) . '</td>'
                . '<td style="' . $tdBr . ';text-align:right">' . $h($currency . ' ' . number_format($unit, 2)) . '</td>';
            if (!$hideTax) {
                $rows .= '<td style="' . $tdBr . ';text-align:right">' . $h(number_format($tax, 2)) . '%</td>';
            }
            $rows .= '<td style="' . $tdBr . ';text-align:right;font-weight:600">' . $h($currency . ' ' . number_format($lt, 2)) . '</td>'
                . '</tr>';
        }

        $thBase = 'padding:8px 6px;border-bottom:2px solid ' . $theadBorder . ';background:' . $theadBg . ';color:' . $theadColor;
        $theadCells = '<th style="text-align:left;' . $thBase . '">Item</th>'
            . '<th style="text-align:right;' . $thBase . '">Qty</th>'
            . '<th style="text-align:right;' . $thBase . '">Unit</th>';
        if (!$hideTax) {
            $theadCells .= '<th style="text-align:right;' . $thBase . '">Tax</th>';
        }
        $theadCells .= '<th style="text-align:right;' . $thBase . '">Total</th>';

        $notesHtml = '';
        if (!empty($invoice['notes'])) {
            $notesHtml = '<div style="margin-top:20px"><div style="font-size:10px;color:' . $muted . ';text-transform:uppercase;letter-spacing:.06em;margin-bottom:6px">Notes</div>'
                . '<div style="font-size:11px;color:#334155;white-space:pre-wrap">' . $h((string) $invoice['notes']) . '</div></div>';
        }

        $bankLines = billo_organization_bank_detail_lines($organization);
        $bankHtml = '';
        if ($bankLines !== []) {
            $bankBits = '';
            foreach ($bankLines as $bl) {
                $bankBits .= '<div style="font-size:11px;color:' . $muted . '">' . $h($bl) . '</div>';
            }
            $bankHtml = '<div style="margin-top:20px"><div style="font-size:10px;color:' . $muted . ';text-transform:uppercase;letter-spacing:.06em;margin-bottom:6px">Bank details</div>'
                . $bankBits . '</div>';
        }

        $creditRefHtml = '';
        if ($invKind === 'credit_note' && $creditRef !== '') {
            $creditRefHtml = '<div style="font-size:10px;color:' . $muted . ';margin-top:6px">Applies to invoice: <strong>' . $h($creditRef) . '</strong></div>';
        }

        $docTaxPdf = function_exists('billo_invoice_use_document_tax') && billo_invoice_use_document_tax($invoice);

        $totalsRows = '<tr><td style="color:' . $muted . ';padding:4px 0">Subtotal</td><td style="text-align:right;padding:4px 0">'
            . $h($currency . ' ' . number_format((float) ($invoice['subtotal'] ?? 0), 2)) . '</td></tr>';

        if ($docTaxPdf) {
            if (!empty($invoice['apply_vat'])) {
                $vr = (float) ($invoice['vat_rate'] ?? 0);
                $vLabel = 'VAT';
                if ($vr > 0.00001) {
                    $vLabel .= ' (' . $h(rtrim(rtrim(sprintf('%.2f', $vr), '0'), '.') ?: '0') . '%)';
                }
                $totalsRows .= '<tr><td style="color:' . $muted . ';padding:4px 0">' . $vLabel . '</td><td style="text-align:right;padding:4px 0">'
                    . $h($currency . ' ' . number_format((float) ($invoice['vat_amount'] ?? $invoice['tax_total'] ?? 0), 2)) . '</td></tr>';
            }
            $totalLabel = 'Total';
            if (!empty($invoice['apply_wht'])) {
                $totalLabel .= ' (before WHT)';
            }
            $totalsRows .= '<tr><td style="font-weight:700;color:' . $primary . ';padding:8px 0 4px;border-top:1px solid ' . $accent . '">' . $h($totalLabel) . '</td><td style="text-align:right;font-weight:700;color:' . $primary . ';padding:8px 0 4px;border-top:1px solid ' . $accent . '">'
                . $h($currency . ' ' . number_format((float) ($invoice['total'] ?? 0), 2)) . '</td></tr>';
            if (!empty($invoice['apply_wht'])) {
                $whtName = trim((string) ($invoice['wht_type_name'] ?? ''));
                $whtLab = 'Less: WHT' . ($whtName !== '' ? ' (' . $h($whtName) . ')' : '');
                $totalsRows .= '<tr><td style="color:' . $muted . ';padding:4px 0">' . $whtLab . '</td><td style="text-align:right;padding:4px 0">'
                    . $h('−' . $currency . ' ' . number_format((float) ($invoice['wht_amount'] ?? 0), 2)) . '</td></tr>';
                $totalsRows .= '<tr><td style="font-weight:700;color:' . $primary . ';padding:10px 0 4px;border-top:2px solid ' . $accent . '">Net payable</td><td style="text-align:right;font-weight:700;color:' . $primary . ';padding:10px 0 4px;border-top:2px solid ' . $accent . '">'
                    . $h($currency . ' ' . number_format((float) ($invoice['net_payable'] ?? $invoice['total'] ?? 0), 2)) . '</td></tr>';
            }
        } else {
            if (!$hideTax) {
                $totalsRows .= '<tr><td style="color:' . $muted . ';padding:4px 0">Tax</td><td style="text-align:right;padding:4px 0">'
                    . $h($currency . ' ' . number_format((float) ($invoice['tax_total'] ?? 0), 2)) . '</td></tr>';
            }
            $totalsRows .= '<tr><td style="font-weight:700;color:' . $primary . ';padding:8px 0 4px;border-top:1px solid ' . $accent . '">Total</td><td style="text-align:right;font-weight:700;color:' . $primary . ';padding:8px 0 4px;border-top:1px solid ' . $accent . '">'
                . $h($currency . ' ' . number_format((float) ($invoice['total'] ?? 0), 2)) . '</td></tr>';
        }

        return '<!DOCTYPE html><html><head><meta charset="UTF-8">'
            . '<style>body{font-family:DejaVu Sans,sans-serif;font-size:12px;color:#0f172a;margin:24px;background:' . $h($T['sheet_bg']) . '}</style></head><body>'
            . $logoHtml
            . '<div style="display:flex;justify-content:space-between;align-items:flex-start;gap:16px">'
            . '<div style="' . $h($leftBoxStyle) . '"><div style="font-size:10px;text-transform:uppercase;letter-spacing:.1em;color:' . $docLab . ';margin-bottom:4px">' . $h($docLabel) . ' from</div>'
            . '<div style="font-size:16px;font-weight:700;color:' . $primary . '">' . $h($displayName) . '</div>'
            . $addrHtml . $taxHtml . $cacHtml . $webHtml . '</div>'
            . '<div style="text-align:right">'
            . '<div style="font-size:10px;text-transform:uppercase;letter-spacing:.08em;color:' . $muted . ';margin-bottom:2px">' . $h($docLabel) . '</div>'
            . '<div style="font-size:' . $titlePx . ';font-weight:700;color:' . $primary . '">' . $h($num) . '</div>'
            . $creditRefHtml
            . '<div style="font-size:11px;color:#475569;margin-top:6px">Issue: ' . $h($issue) . '</div>'
            . ($due !== '' ? '<div style="font-size:11px;color:#475569">Due: ' . $h($due) . '</div>' : '')
            . '<div style="font-size:11px;color:#475569">Status: ' . $h($status) . '</div></div></div>'

            . '<div style="margin-top:28px"><div style="font-size:10px;color:' . $muted . ';text-transform:uppercase;letter-spacing:.06em;margin-bottom:6px">Bill to</div>'
            . '<div style="font-weight:600">' . $h($clientLine) . '</div></div>'

            . '<table style="width:100%;border-collapse:collapse;margin-top:20px;font-size:11px"><thead><tr>'
            . $theadCells
            . '</tr></thead><tbody>' . $rows . '</tbody></table>'

            . '<table style="width:240px;margin-left:auto;margin-top:16px;font-size:11px">' . $totalsRows . '</table>'

            . $bankHtml . $notesHtml . $footerHtml
            . '</body></html>';
    }

    /**
     * @param array<string, mixed> $organization
     * @param callable(string): string $h
     */
    private function logoHtml(array $organization, callable $h): string
    {
        $ref = isset($organization['invoice_logo_url']) ? trim((string) $organization['invoice_logo_url']) : '';
        if ($ref === '') {
            return '';
        }
        if (str_starts_with($ref, 'https://')) {
            return '<div><img src="' . $h($ref) . '" style="max-height:56px;margin-bottom:8px" alt=""></div>';
        }

        $root = realpath(BILLO_ROOT);
        if ($root === false) {
            return '';
        }
        $path = realpath(BILLO_ROOT . DIRECTORY_SEPARATOR . str_replace(['/', '\\'], DIRECTORY_SEPARATOR, ltrim($ref, '/\\')));
        if ($path === false || !is_file($path) || !str_starts_with($path, $root)) {
            return '';
        }
        $mime = @mime_content_type($path);
        if (!is_string($mime) || !str_starts_with($mime, 'image/')) {
            return '';
        }
        $raw = @file_get_contents($path);
        if ($raw === false || $raw === '') {
            return '';
        }

        return '<div><img src="data:' . $h($mime) . ';base64,' . base64_encode($raw) . '" style="max-height:56px;margin-bottom:8px" alt=""></div>';
    }
}
