<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Config;

/**
 * Nigerian banks for invoice “pay to” fields. Uses Paystack’s list API when
 * payments.paystack.secret_key is set (same key as checkout), with a short TTL
 * file cache; otherwise falls back to data/ng_banks.json shipped with the app.
 */
final class NigerianBankListService
{
    private const PAYSTACK_URL = 'https://api.paystack.co/bank?country=nigeria&perPage=100';
    private const CACHE_TTL_SEC = 86400;

    /**
     * @return list<array{code: string, name: string}>
     */
    public static function banks(): array
    {
        $secret = trim((string) Config::get('payments.paystack.secret_key', ''));
        if ($secret !== '') {
            $cached = self::readCache();
            if ($cached !== null) {
                return $cached;
            }
            $live = self::fetchFromPaystack($secret);
            if ($live !== null) {
                self::writeCache($live);

                return $live;
            }
        }

        return self::bundled();
    }

    /**
     * @return list<array{code: string, name: string}>|null
     */
    private static function readCache(): ?array
    {
        $path = BILLO_ROOT . '/storage/cache/ng_banks_paystack.json';
        if (!is_file($path)) {
            return null;
        }
        if (time() - (int) filemtime($path) > self::CACHE_TTL_SEC) {
            return null;
        }
        $raw = file_get_contents($path);
        if ($raw === false || $raw === '') {
            return null;
        }
        try {
            $data = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return null;
        }
        if (!is_array($data)) {
            return null;
        }
        $out = self::normalizeList($data);

        return $out === [] ? null : $out;
    }

    /**
     * @param list<array{code: string, name: string}> $banks
     */
    private static function writeCache(array $banks): void
    {
        $dir = BILLO_ROOT . '/storage/cache';
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }
        $path = $dir . '/ng_banks_paystack.json';
        try {
            file_put_contents(
                $path,
                json_encode($banks, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
                LOCK_EX
            );
        } catch (\JsonException | \Throwable) {
            // ignore cache write failures
        }
    }

    /**
     * @return list<array{code: string, name: string}>|null
     */
    private static function fetchFromPaystack(string $secret): ?array
    {
        $raw = self::httpGetJson(self::PAYSTACK_URL, $secret);
        if ($raw === null || $raw === '') {
            return null;
        }
        try {
            $json = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return null;
        }
        if (!is_array($json) || empty($json['status']) || !isset($json['data']) || !is_array($json['data'])) {
            return null;
        }
        $rows = [];
        foreach ($json['data'] as $row) {
            if (!is_array($row)) {
                continue;
            }
            $name = isset($row['name']) ? trim((string) $row['name']) : '';
            $code = isset($row['code']) ? trim((string) $row['code']) : '';
            if ($name === '' || $code === '') {
                continue;
            }
            $rows[] = ['code' => $code, 'name' => $name];
        }

        return self::dedupeAndSort($rows);
    }

    /**
     * @param list<array<string, mixed>> $data
     * @return list<array{code: string, name: string}>
     */
    private static function normalizeList(array $data): array
    {
        $rows = [];
        foreach ($data as $row) {
            if (!is_array($row) || !isset($row['code'], $row['name'])) {
                continue;
            }
            $name = trim((string) $row['name']);
            $code = trim((string) $row['code']);
            if ($name === '' || $code === '') {
                continue;
            }
            $rows[] = ['code' => $code, 'name' => $name];
        }

        return self::dedupeAndSort($rows);
    }

    /**
     * @param list<array{code: string, name: string}> $rows
     * @return list<array{code: string, name: string}>
     */
    private static function dedupeAndSort(array $rows): array
    {
        $byCode = [];
        foreach ($rows as $r) {
            $byCode[$r['code']] = $r['name'];
        }
        $out = [];
        foreach ($byCode as $code => $name) {
            $out[] = ['code' => $code, 'name' => $name];
        }
        usort($out, static fn (array $a, array $b): int => strcasecmp($a['name'], $b['name']));

        return $out;
    }

    /**
     * @return list<array{code: string, name: string}>
     */
    private static function bundled(): array
    {
        $path = BILLO_ROOT . '/data/ng_banks.json';
        if (!is_file($path)) {
            return [];
        }
        $raw = file_get_contents($path);
        if ($raw === false || $raw === '') {
            return [];
        }
        try {
            $data = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return [];
        }
        if (!is_array($data)) {
            return [];
        }

        return self::normalizeList($data);
    }

    private static function httpGetJson(string $url, string $secret): ?string
    {
        if (function_exists('curl_init')) {
            $ch = curl_init($url);
            if ($ch === false) {
                return null;
            }
            curl_setopt_array($ch, [
                CURLOPT_HTTPHEADER => [
                    'Authorization: Bearer ' . $secret,
                    'Accept: application/json',
                ],
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => 12,
            ]);
            $body = curl_exec($ch);
            $code = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
            curl_close($ch);
            if (!is_string($body) || $code !== 200) {
                return null;
            }

            return $body;
        }

        $ctx = stream_context_create([
            'http' => [
                'method' => 'GET',
                'header' => 'Authorization: Bearer ' . $secret . "\r\nAccept: application/json\r\n",
                'timeout' => 12,
            ],
        ]);
        $body = @file_get_contents($url, false, $ctx);

        return is_string($body) ? $body : null;
    }
}
