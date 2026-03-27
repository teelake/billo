<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Config;
use Stripe\Checkout\Session;
use Stripe\Exception\ApiErrorException;
use Stripe\Stripe;

final class StripeCheckoutService
{
    public function isConfigured(): bool
    {
        $sk = trim((string) Config::get('payments.stripe_secret_key', ''));

        return $sk !== '' && str_starts_with($sk, 'sk_');
    }

    /**
     * @param array<string, mixed> $invoice row with lines loaded (for display only; amount uses total)
     */
    public function createCheckoutSession(
        array $invoice,
        int $organizationId,
        string $successUrl,
        string $cancelUrl,
    ): Session {
        $sk = trim((string) Config::get('payments.stripe_secret_key', ''));
        Stripe::setApiKey($sk);

        $invoiceId = (int) ($invoice['id'] ?? 0);
        $currency = strtolower((string) ($invoice['currency'] ?? 'ngn'));
        $total = (float) ($invoice['total'] ?? 0);
        $num = (string) ($invoice['invoice_number'] ?? 'Invoice');

        $unitAmount = (int) round($total * 100);
        if ($unitAmount < 50) {
            throw new \InvalidArgumentException('Amount too small for card checkout.');
        }

        try {
            return Session::create([
                'mode' => 'payment',
                'client_reference_id' => $organizationId . ':' . $invoiceId,
                'line_items' => [[
                    'quantity' => 1,
                    'price_data' => [
                        'currency' => $currency,
                        'unit_amount' => $unitAmount,
                        'product_data' => [
                            'name' => $num,
                            'description' => 'Invoice payment',
                        ],
                    ],
                ]],
                'metadata' => [
                    'invoice_id' => (string) $invoiceId,
                    'organization_id' => (string) $organizationId,
                ],
                'success_url' => $successUrl,
                'cancel_url' => $cancelUrl,
            ]);
        } catch (ApiErrorException $e) {
            error_log('Stripe Checkout: ' . $e->getMessage());
            throw $e;
        }
    }
}
