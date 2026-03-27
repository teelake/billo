<?php

declare(strict_types=1);

namespace App\Services;

use App\Repositories\OrganizationSubscriptionRepository;
use App\Repositories\SubscriptionOrderRepository;
use App\Services\Payments\PaystackGateway;

/**
 * Completes Paystack return for subscription_orders (metadata.subscription_order_id).
 */
final class SubscriptionPaymentCompletion
{
    public function tryCompleteFromPaystackReturn(PaystackGateway $gateway, array $queryParams): bool
    {
        $ref = trim((string) ($queryParams['reference'] ?? $queryParams['trxref'] ?? ''));
        if ($ref === '') {
            return false;
        }

        $data = $gateway->verifySuccessfulCharge($ref);
        if ($data === null) {
            return false;
        }

        $meta = isset($data['metadata']) && is_array($data['metadata']) ? $data['metadata'] : [];
        $orderId = (int) ($meta['subscription_order_id'] ?? 0);
        $orgId = (int) ($meta['organization_id'] ?? 0);
        $planId = (int) ($meta['plan_id'] ?? 0);
        if ($orderId <= 0 || $orgId <= 0 || $planId <= 0) {
            return false;
        }

        $orders = new SubscriptionOrderRepository();
        $order = $orders->findPendingByCheckoutRef($ref);
        if ($order === null || (int) ($order['id'] ?? 0) !== $orderId || (int) ($order['organization_id'] ?? 0) !== $orgId) {
            return false;
        }

        $paidKobo = isset($data['amount']) ? (int) $data['amount'] : 0;
        $currency = strtoupper((string) ($data['currency'] ?? 'NGN'));
        $expectedKobo = (int) round((float) ($order['amount'] ?? 0) * 100);
        if ($paidKobo !== $expectedKobo) {
            error_log('Paystack subscription: amount mismatch for order ' . $orderId);

            return false;
        }
        $orderCur = strtoupper((string) ($order['currency'] ?? 'NGN'));
        if ($orderCur !== $currency) {
            return false;
        }

        $txRef = trim((string) ($data['reference'] ?? $ref));
        if (!$orders->markPaid($orderId, $orgId, $txRef !== '' ? $txRef : $ref)) {
            return false;
        }

        (new OrganizationSubscriptionRepository())->setPlan($orgId, $planId, 'active');

        return true;
    }
}
