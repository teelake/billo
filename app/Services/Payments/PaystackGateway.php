<?php

declare(strict_types=1);

namespace App\Services\Payments;

use App\Core\Config;

/**
 * Paystack Transaction Initialize + verify (no official SDK).
 *
 * @see https://paystack.com/docs/api/transaction
 */
final class PaystackGateway implements PaymentGatewayInterface
{
    private const API_BASE = 'https://api.paystack.co';

    public function getDriverId(): string
    {
        return 'paystack';
    }

    public function isConfigured(): bool
    {
        $sk = trim((string) Config::get('payments.paystack.secret_key', ''));

        return $sk !== '' && str_starts_with($sk, 'sk_');
    }

    public function beginHostedCheckout(array $invoice, int $organizationId): array
    {
        $sk = trim((string) Config::get('payments.paystack.secret_key', ''));
        if (!$this->isConfigured()) {
            throw new \RuntimeException('Paystack is not configured.');
        }

        $invoiceId = (int) ($invoice['id'] ?? 0);
        $currency = strtoupper((string) ($invoice['currency'] ?? 'NGN'));
        $total = (float) ($invoice['total'] ?? 0);
        if ($total <= 0) {
            throw new \InvalidArgumentException('Invoice total must be positive.');
        }

        $amount = $this->amountInSubunit($total, $currency);
        if ($amount < 100) {
            throw new \InvalidArgumentException('Amount too small for Paystack.');
        }

        $reference = 'b1_o' . $organizationId . '_i' . $invoiceId . '_' . bin2hex(random_bytes(8));
        $email = $this->resolvePayerEmail($invoice);
        $callback = \billo_url('/pay/return');

        $payload = [
            'email' => $email,
            'amount' => $amount,
            'currency' => $currency,
            'reference' => $reference,
            'callback_url' => $callback,
            'metadata' => [
                'invoice_id' => (string) $invoiceId,
                'organization_id' => (string) $organizationId,
            ],
        ];

        $res = $this->request('POST', '/transaction/initialize', $sk, $payload);
        if (!($res['status'] ?? false) || !isset($res['data']['authorization_url'])) {
            $msg = (string) ($res['message'] ?? 'Paystack initialize failed');
            error_log('Paystack initialize: ' . $msg);
            throw new \RuntimeException($msg);
        }

        return [
            'redirect_url' => (string) $res['data']['authorization_url'],
            'checkout_ref' => $reference,
        ];
    }

    public function completeFromReturn(array $queryParams): ?array
    {
        if (!$this->isConfigured()) {
            return null;
        }
        $ref = trim((string) ($queryParams['reference'] ?? $queryParams['trxref'] ?? ''));
        if ($ref === '') {
            return null;
        }

        $sk = trim((string) Config::get('payments.paystack.secret_key', ''));
        $res = $this->request('GET', '/transaction/verify/' . rawurlencode($ref), $sk, null);
        if (!($res['status'] ?? false)) {
            return null;
        }
        $data = $res['data'] ?? [];
        if (($data['status'] ?? '') !== 'success') {
            return null;
        }

        $resolved = $this->resolvePaidInvoiceFromPaystackData($data, $ref, 'verify');
        if ($resolved === null) {
            return null;
        }

        return $resolved;
    }

    /**
     * @return array<string, mixed>
     */
    public function parseChargeSuccessWebhook(string $rawJson): ?array
    {
        try {
            /** @var array<string, mixed> $evt */
            $evt = json_decode($rawJson, true, 512, JSON_THROW_ON_ERROR);
        } catch (\Throwable) {
            return null;
        }
        if (($evt['event'] ?? '') !== 'charge.success') {
            return null;
        }
        $data = $evt['data'] ?? null;
        if (!is_array($data)) {
            return null;
        }
        $ref = trim((string) ($data['reference'] ?? ''));
        if ($ref === '') {
            return null;
        }

        return $this->resolvePaidInvoiceFromPaystackData($data, $ref, 'webhook');
    }

    public function verifyWebhookSignature(string $payload, string $signatureHeader): bool
    {
        $sk = trim((string) Config::get('payments.paystack.secret_key', ''));
        if ($sk === '' || $signatureHeader === '') {
            return false;
        }
        $expected = hash_hmac('sha512', $payload, $sk);

        return hash_equals($expected, $signatureHeader);
    }

    private function resolvePayerEmail(array $invoice): string
    {
        $email = strtolower(trim((string) ($invoice['client_email'] ?? '')));
        if ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return $email;
        }
        $fb = strtolower(trim((string) Config::get('payments.fallback_payer_email', '')));
        if ($fb !== '' && filter_var($fb, FILTER_VALIDATE_EMAIL)) {
            return $fb;
        }
        $from = strtolower(trim((string) Config::get('mail.from_address', 'noreply@example.com')));
        if ($from !== '' && filter_var($from, FILTER_VALIDATE_EMAIL)) {
            return $from;
        }

        return 'noreply@example.com';
    }

    private function amountInSubunit(float $total, string $currency): int
    {
        unset($currency);

        return (int) round($total * 100);
    }

    private function amountMatchesInvoice(int $paidSubunits, string $currency, int $invoiceId, int $organizationId): bool
    {
        $repo = new \App\Repositories\InvoiceRepository();
        $inv = $repo->findWithLines($invoiceId, $organizationId);
        if ($inv === null) {
            return false;
        }
        $cur = strtoupper((string) ($inv['currency'] ?? 'NGN'));
        if ($cur !== $currency) {
            return false;
        }
        $expected = $this->amountInSubunit((float) ($inv['total'] ?? 0), $cur);

        return $paidSubunits === $expected;
    }

    /**
     * @param array<string, mixed>|null $jsonBody
     * @return array<string, mixed>
     */
    private function request(string $method, string $path, string $secretKey, ?array $jsonBody): array
    {
        $url = self::API_BASE . $path;
        $ch = curl_init($url);
        if ($ch === false) {
            return ['status' => false, 'message' => 'curl init failed'];
        }
        $headers = [
            'Authorization: Bearer ' . $secretKey,
            'Content-Type: application/json',
        ];
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_TIMEOUT => 30,
        ]);
        if ($jsonBody !== null && $method !== 'GET') {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($jsonBody, JSON_THROW_ON_ERROR));
        }
        $raw = curl_exec($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        if ($raw === false || $raw === '') {
            return ['status' => false, 'message' => 'empty response', 'http_code' => $code];
        }
        try {
            /** @var array<string, mixed> $out */
            $out = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
        } catch (\Throwable) {
            return ['status' => false, 'message' => 'invalid json'];
        }
        $out['http_code'] = $code;

        return $out;
    }
}
