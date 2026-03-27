<?php

declare(strict_types=1);

namespace App\Services\Payments;

final class NullPaymentGateway implements PaymentGatewayInterface
{
    public function getDriverId(): string
    {
        return 'none';
    }

    public function isConfigured(): bool
    {
        return false;
    }

    public function beginHostedCheckout(array $invoice, int $organizationId): array
    {
        throw new \LogicException('No payment provider is configured.');
    }

    public function completeFromReturn(array $queryParams): ?array
    {
        return null;
    }
}
