<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Config;
use App\Repositories\InvoiceRepository;
use Stripe\Exception\SignatureVerificationException;
use Stripe\Webhook;
use UnexpectedValueException;

final class StripeWebhookController
{
    public function __construct(
        private InvoiceRepository $invoices = new InvoiceRepository(),
    ) {
    }

    public function handle(): void
    {
        $secret = trim((string) Config::get('payments.stripe.webhook_secret', ''));
        if ($secret === '') {
            $secret = trim((string) Config::get('payments.stripe_webhook_secret', ''));
        }
        if ($secret === '') {
            http_response_code(503);
            header('Content-Type: text/plain; charset=UTF-8');
            echo 'Webhook not configured';
            return;
        }

        $payload = file_get_contents('php://input');
        if ($payload === false || $payload === '') {
            http_response_code(400);
            echo 'Empty body';
            return;
        }

        $sigHeader = $_SERVER['HTTP_STRIPE_SIGNATURE'] ?? '';
        try {
            $event = Webhook::constructEvent($payload, $sigHeader, $secret);
        } catch (UnexpectedValueException|SignatureVerificationException $e) {
            error_log('Stripe webhook verify: ' . $e->getMessage());
            http_response_code(400);
            echo 'Bad signature';
            return;
        }

        if ($event->type === 'checkout.session.completed') {
            /** @var \Stripe\Checkout\Session $session */
            $session = $event->data->object;
            $invoiceId = (int) ($session->metadata->invoice_id ?? 0);
            $orgId = (int) ($session->metadata->organization_id ?? 0);
            if ($invoiceId <= 0 || $orgId <= 0) {
                $found = $this->invoices->findByGatewayCheckoutRef($session->id);
                if ($found === null) {
                    http_response_code(200);
                    echo 'ok';
                    return;
                }
                $invoiceId = (int) $found['id'];
                $orgId = (int) $found['organization_id'];
            }

            $pi = $session->payment_intent;
            $piId = is_string($pi) ? $pi : (is_object($pi) && isset($pi->id) ? (string) $pi->id : '');

            if ($session->payment_status === 'paid') {
                $this->invoices->markPaidFromGateway($invoiceId, $orgId, $piId !== '' ? $piId : $session->id, 'stripe');
            }
        }

        http_response_code(200);
        header('Content-Type: text/plain; charset=UTF-8');
        echo 'ok';
    }
}
