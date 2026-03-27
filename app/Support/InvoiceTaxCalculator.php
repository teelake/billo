<?php

declare(strict_types=1);

namespace App\Support;

/** Document-level VAT (on subtotal) + WHT (on subtotal, deducted from gross). */
final class InvoiceTaxCalculator
{
    /**
     * @return array{
     *   vat_amount: string,
     *   wht_amount: string,
     *   gross_total: string,
     *   net_payable: string
     * }
     */
    public static function compute(float $subtotal, bool $applyVat, float $vatRatePercent, bool $applyWht, float $whtRatePercent): array
    {
        if ($vatRatePercent < 0 || $vatRatePercent > 100) {
            throw new \InvalidArgumentException('VAT rate must be between 0 and 100.');
        }
        if ($whtRatePercent < 0 || $whtRatePercent > 100) {
            throw new \InvalidArgumentException('WHT rate must be between 0 and 100.');
        }

        $vatAmount = $applyVat ? round($subtotal * ($vatRatePercent / 100.0), 2) : 0.0;
        $grossTotal = round($subtotal + $vatAmount, 2);
        $whtAmount = $applyWht ? round($subtotal * ($whtRatePercent / 100.0), 2) : 0.0;
        $netPayable = round($grossTotal - $whtAmount, 2);
        if ($netPayable < -0.0001) {
            throw new \InvalidArgumentException('Net payable cannot be negative.');
        }
        if ($netPayable < 0) {
            $netPayable = 0.0;
        }

        return [
            'vat_amount' => number_format($vatAmount, 2, '.', ''),
            'wht_amount' => number_format($whtAmount, 2, '.', ''),
            'gross_total' => number_format($grossTotal, 2, '.', ''),
            'net_payable' => number_format($netPayable, 2, '.', ''),
        ];
    }
}
