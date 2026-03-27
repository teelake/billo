<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Repositories\InvoiceRepository;
use App\Services\Payments\PaystackGateway;

final class PaystackWebhookController
{
    public function __construct(
        private InvoiceRepository $invoices = new InvoiceRepository(),
        private PaystackGateway $paystack = new PaystackGateway(),
    ) {
    }

    public function handle(): void
    {
        if (!$this->paystack->isConfigured()) {
            http_response_code(503);
            header('Content-Type: text/plain; charset=UTF-8');
            echo 'Paystack not configured';
            return;
        }

        $payload = file_get_contents('php://input');
        if ($payload === false || $payload === '') {
            http_response_code(400);
            echo 'Empty body';
            return;
        }

        $sig = (string) ($_SERVER['HTTP_X_PAYSTACK_SIGNATURE'] ?? '');
        if (!$this->paystack->verifyWebhookSignature($payload, $sig)) {
            error_log('Paystack webhook: bad signature');
            http_response_code(400);
            echo 'Bad signature';
            return;
        }

        $parsed = $this->paystack->parseChargeSuccessWebhook($payload);
        if ($parsed !== null) {
            $this->invoices->markPaidFromGateway(
                $parsed['invoice_id'],
                $parsed['organization_id'],
                $parsed['transaction_ref'],
                'paystack',
            );
        }

        http_response_code(200);
        header('Content-Type: text/plain; charset=UTF-8');
        echo 'ok';
    }
}
