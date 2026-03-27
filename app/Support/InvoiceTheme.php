<?php

declare(strict_types=1);

namespace App\Support;

/** Invoice print/PDF layout presets + brand hex colors. */
final class InvoiceTheme
{
    public const STYLES = ['modern', 'professional', 'premium', 'minimal'];

    public static function normalizeStyle(?string $raw): string
    {
        $s = strtolower(trim((string) $raw));
        if ($s === '') {
            return 'modern';
        }
        if (!in_array($s, self::STYLES, true)) {
            return 'modern';
        }

        return $s;
    }

    public static function sanitizeHex(?string $hex, string $fallback = '#1E3A8A'): string
    {
        $h = strtoupper(trim((string) $hex));
        if (preg_match('/^#[0-9A-F]{6}$/', $h) === 1) {
            return $h;
        }

        return strtoupper(strlen($fallback) === 7 && $fallback[0] === '#' ? $fallback : '#1E3A8A');
    }

    /**
     * @return array{r:int,g:int,b:int}
     */
    public static function hexToRgb(string $hex): array
    {
        $h = ltrim($hex, '#');
        if (strlen($h) !== 6) {
            return ['r' => 30, 'g' => 58, 'b' => 138];
        }

        return [
            'r' => hexdec(substr($h, 0, 2)),
            'g' => hexdec(substr($h, 2, 2)),
            'b' => hexdec(substr($h, 4, 2)),
        ];
    }

    /**
     * @param array<string, mixed> $organization
     * @return array{
     *   style:string,
     *   primary:string,
     *   accent:string,
     *   thead_bg:string,
     *   thead_color:string,
     *   thead_border:string,
     *   cell_border:string,
     *   label_color:string,
     *   muted_color:string,
     *   title_size:string,
     *   doc_block_border:string,
     *   sheet_bg:string
     * }
     */
    public static function tokens(array $organization): array
    {
        $style = self::normalizeStyle($organization['invoice_style'] ?? null);
        $primary = self::sanitizeHex($organization['invoice_brand_primary'] ?? null, '#1E3A8A');
        $accent = self::sanitizeHex($organization['invoice_brand_accent'] ?? null, '#16A34A');
        $p = self::hexToRgb($primary);

        $theadBg = '#f1f5f9';
        $theadColor = '#334155';
        $theadBorder = '#e2e8f0';
        $cellBorder = '#e2e8f0';
        $labelColor = '#64748b';
        $mutedColor = '#64748b';
        $titleSize = '20px';
        $docBlockBorder = 'none';
        $sheetBg = '#ffffff';
        $docLabelAccent = $primary;

        if ($style === 'professional') {
            $theadBg = $primary;
            $theadColor = '#ffffff';
            $theadBorder = $primary;
            $cellBorder = '#cbd5e1';
            $titleSize = '19px';
            $docLabelAccent = $primary;
        } elseif ($style === 'premium') {
            $theadBg = "rgba({$p['r']},{$p['g']},{$p['b']},0.09)";
            $theadColor = '#0f172a';
            $theadBorder = $accent;
            $cellBorder = '#e2e8f0';
            $titleSize = '24px';
            $docBlockBorder = '4px solid ' . $accent;
            $sheetBg = '#fafafa';
            $docLabelAccent = $accent;
        } elseif ($style === 'minimal') {
            $theadBg = 'transparent';
            $theadColor = '#64748b';
            $theadBorder = '#e2e8f0';
            $cellBorder = '#f1f5f9';
            $titleSize = '18px';
            $docLabelAccent = $mutedColor;
        }

        return [
            'style' => $style,
            'primary' => $primary,
            'accent' => $accent,
            'thead_bg' => $theadBg,
            'thead_color' => $theadColor,
            'thead_border' => $theadBorder,
            'cell_border' => $cellBorder,
            'label_color' => $labelColor,
            'muted_color' => $mutedColor,
            'title_size' => $titleSize,
            'doc_block_border' => $docBlockBorder,
            'sheet_bg' => $sheetBg,
            'doc_label_accent' => $docLabelAccent,
        ];
    }
}
