<?php

declare(strict_types=1);

namespace App\Services\Payments;

use App\Core\Config;

final class PaymentGatewayFactory
{
    public static function active(): PaymentGatewayInterface
    {
        $id = strtolower(trim((string) Config::get('payments.provider', 'none')));
        if ($id === '' || $id === 'none' || $id === 'off' || $id === 'disabled') {
            return new NullPaymentGateway();
        }

        return match ($id) {
            'paystack' => new PaystackGateway(),
            'stripe' => self::makeStripeGateway(),
            default => new NullPaymentGateway(),
        };
    }

    private static function makeStripeGateway(): PaymentGatewayInterface
    {
        if (!class_exists(\Stripe\Checkout\Session::class)) {
            error_log('Billo: payments.provider is stripe but stripe/stripe-php is not installed. Run: composer require stripe/stripe-php');

            return new NullPaymentGateway();
        }

        return new StripeGateway();
    }

    /** Signed pay links + configured active gateway (any supported provider). */
    public static function invoicePayLinksAvailable(): bool
    {
        $secret = trim((string) Config::get('payments.link_signing_secret', ''));

        return $secret !== '' && self::active()->isConfigured();
    }
}
