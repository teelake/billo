<?php

declare(strict_types=1);

namespace App\Services\Payments;

use App\Core\Config;
use Stripe\Checkout\Session;
use Stripe\Exception\ApiErrorException;
use Stripe\Stripe;

final class StripeGateway implements PaymentGatewayInterface
{
    public function getDriverId(): string
    {
        return 'stripe';
    }

    public function isConfigured(): bool
    {
        $sk = $this->secretKey();

        return $sk !== '' && str_starts_with($sk, 'sk_');
    }

    public function beginHostedCheckout(array $invoice, int $organizationId): array
    {
        $sk = $this->secretKey();
        if ($sk === '') {
            throw new \RuntimeException('Stripe is not configured.');
        }
        Stripe::setApiKey($sk);

        $invoiceId = (int) ($invoice['id'] ?? 0);
        $currency = strtolower((string) ($invoice['currency'] ?? 'ngn'));
        $total = function_exists('billo_invoice_payable_amount')
            ? \billo_invoice_payable_amount($invoice)
            : (float) ($invoice['total'] ?? 0);
        $num = (string) ($invoice['invoice_number'] ?? 'Invoice');

        $unitAmount = (int) round($total * 100);
        if ($unitAmount < 50) {
            throw new \InvalidArgumentException('Amount too small for card checkout.');
        }

        $success = \billo_url('/pay/return?session_id={CHECKOUT_SESSION_ID}');
        $cancel = \billo_url('/pay/cancel');

        try {
            $session = Session::create([
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
                'success_url' => $success,
                'cancel_url' => $cancel,
            ]);
        } catch (ApiErrorException $e) {
            error_log('Stripe Checkout: ' . $e->getMessage());
            throw $e;
        }

        return [
            'redirect_url' => (string) $session->url,
            'checkout_ref' => (string) $session->id,
        ];
    }

    public function completeFromReturn(array $queryParams): ?array
    {
        if (!$this->isConfigured()) {
            return null;
        }
        $sessionId = trim((string) ($queryParams['session_id'] ?? ''));
        if ($sessionId === '') {
            return null;
        }
        Stripe::setApiKey($this->secretKey());
        try {
            $session = Session::retrieve($sessionId);
        } catch (\Throwable $e) {
            error_log('Stripe session retrieve: ' . $e->getMessage());

            return null;
        }
        if ($session->payment_status !== 'paid') {
            return null;
        }
        $invoiceId = (int) ($session->metadata->invoice_id ?? 0);
        $orgId = (int) ($session->metadata->organization_id ?? 0);
        if ($invoiceId <= 0 || $orgId <= 0) {
            return null;
        }
        $pi = $session->payment_intent;
        $piId = is_string($pi) ? $pi : (is_object($pi) && isset($pi->id) ? (string) $pi->id : '');

        return [
            'invoice_id' => $invoiceId,
            'organization_id' => $orgId,
            'transaction_ref' => $piId !== '' ? $piId : $sessionId,
        ];
    }

    private function secretKey(): string
    {
        $sk = trim((string) Config::get('payments.stripe.secret_key', ''));
        if ($sk !== '') {
            return $sk;
        }

        return trim((string) Config::get('payments.stripe_secret_key', ''));
    }
}
