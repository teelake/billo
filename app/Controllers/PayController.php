<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\View;
use App\Repositories\InvoiceRepository;
use App\Services\PaymentLinkService;
use App\Services\Payments\PaymentGatewayFactory;

final class PayController extends Controller
{
    public function __construct(
        private InvoiceRepository $invoices = new InvoiceRepository(),
        private PaymentLinkService $paymentLinks = new PaymentLinkService(),
    ) {
    }

    /** Public: start hosted checkout from signed token (provider from config). */
    public function checkout(): void
    {
        $token = isset($_GET['token']) ? (string) $_GET['token'] : '';
        $payload = $this->paymentLinks->verifyToken($token);
        if ($payload === null) {
            http_response_code(400);
            View::render('pay/error', ['message' => 'This payment link is invalid or has expired.']);
            return;
        }

        $gateway = PaymentGatewayFactory::active();
        if (!$gateway->isConfigured()) {
            http_response_code(503);
            View::render('pay/error', ['message' => 'Online payment is not configured for this site.']);
            return;
        }

        $invoice = $this->invoices->findWithLines($payload['i'], $payload['o']);
        if ($invoice === null) {
            http_response_code(404);
            View::render('pay/error', ['message' => 'Invoice not found.']);
            return;
        }
        if (($invoice['status'] ?? '') !== 'sent' || ($invoice['invoice_kind'] ?? 'invoice') !== 'invoice') {
            View::render('pay/error', ['message' => 'This invoice cannot be paid online.']);
            return;
        }
        if ((float) ($invoice['total'] ?? 0) <= 0) {
            View::render('pay/error', ['message' => 'Nothing to pay on this invoice.']);
            return;
        }

        try {
            $session = $gateway->beginHostedCheckout($invoice, $payload['o']);
        } catch (\Throwable $e) {
            error_log('PayController checkout: ' . $e->getMessage());
            View::render('pay/error', ['message' => 'Could not start payment. Please try again later.']);
            return;
        }

        $this->invoices->setGatewayPendingCheckout(
            $payload['i'],
            $payload['o'],
            $gateway->getDriverId(),
            $session['checkout_ref'],
        );

        header('Location: ' . $session['redirect_url'], true, 303);
        exit;
    }

    public function returnPage(): void
    {
        $gateway = PaymentGatewayFactory::active();
        if ($gateway->isConfigured()) {
            $query = [];
            foreach ($_GET as $key => $val) {
                if (is_string($val)) {
                    $query[$key] = $val;
                }
            }
            $done = $gateway->completeFromReturn($query);
            if ($done !== null) {
                $this->invoices->markPaidFromGateway(
                    $done['invoice_id'],
                    $done['organization_id'],
                    $done['transaction_ref'],
                    $gateway->getDriverId(),
                );
            }
        }

        View::render('pay/thanks', []);
    }

    public function cancelPage(): void
    {
        View::render('pay/cancel', []);
    }
}
