<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Config;

/**
 * Signed, time-limited links for public invoice payment (no login).
 */
final class PaymentLinkService
{
    private const TTL_SECONDS = 60 * 60 * 24 * 90;

    public function buildUrl(int $invoiceId, int $organizationId): string
    {
        $secret = (string) Config::get('payments.link_signing_secret', '');
        if ($secret === '') {
            return '';
        }
        $exp = time() + self::TTL_SECONDS;
        $payload = json_encode(['i' => $invoiceId, 'o' => $organizationId, 'exp' => $exp], JSON_THROW_ON_ERROR);
        $sig = hash_hmac('sha256', $payload, $secret, true);
        $token = $this->base64UrlEncode($payload) . '.' . $this->base64UrlEncode($sig);

        return billo_url('/pay?token=' . rawurlencode($token));
    }

    /**
     * @return array{i:int,o:int,exp:int}|null
     */
    public function verifyToken(?string $token): ?array
    {
        $secret = (string) Config::get('payments.link_signing_secret', '');
        if ($secret === '' || $token === null || !str_contains($token, '.')) {
            return null;
        }
        [$payloadEnc, $sigEnc] = explode('.', $token, 2);
        $payload = $this->base64UrlDecode($payloadEnc);
        $sig = $this->base64UrlDecode($sigEnc);
        if ($payload === null || $sig === null) {
            return null;
        }
        $expected = hash_hmac('sha256', $payload, $secret, true);
        if (!hash_equals($expected, $sig)) {
            return null;
        }
        try {
            /** @var array<string, mixed> $data */
            $data = json_decode($payload, true, 512, JSON_THROW_ON_ERROR);
        } catch (\Throwable) {
            return null;
        }
        $i = isset($data['i']) ? (int) $data['i'] : 0;
        $o = isset($data['o']) ? (int) $data['o'] : 0;
        $exp = isset($data['exp']) ? (int) $data['exp'] : 0;
        if ($i <= 0 || $o <= 0 || $exp < time()) {
            return null;
        }

        return ['i' => $i, 'o' => $o, 'exp' => $exp];
    }

    private function base64UrlEncode(string $raw): string
    {
        return rtrim(strtr(base64_encode($raw), '+/', '-_'), '=');
    }

    private function base64UrlDecode(string $enc): ?string
    {
        $b64 = strtr($enc, '-_', '+/');
        $pad = strlen($b64) % 4;
        if ($pad > 0) {
            $b64 .= str_repeat('=', 4 - $pad);
        }
        $out = base64_decode($b64, true);

        return $out === false ? null : $out;
    }
}
