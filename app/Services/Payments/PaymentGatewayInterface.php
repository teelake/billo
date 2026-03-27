<?php

declare(strict_types=1);

namespace App\Services\Payments;

/**
 * Hosted checkout for invoice pay links. Implementations: Paystack, Stripe, etc.
 */
interface PaymentGatewayInterface
{
    /** Machine id stored in invoices.payment_provider (e.g. paystack, stripe). */
    public function getDriverId(): string;

    public function isConfigured(): bool;

    /**
     * Start hosted payment; persist checkout_ref on the invoice before redirecting.
     *
     * @param array<string, mixed> $invoice row from InvoiceRepository::findWithLines
     * @return array{redirect_url: string, checkout_ref: string}
     */
    public function beginHostedCheckout(array $invoice, int $organizationId): array;

    /**
     * Confirm payment after browser return (query string from redirect).
     *
     * @param array<string, string> $queryParams
     * @return array{invoice_id: int, organization_id: int, transaction_ref: string}|null
     */
    public function completeFromReturn(array $queryParams): ?array;
}
