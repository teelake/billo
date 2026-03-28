<?php

declare(strict_types=1);

namespace App\Core;

final class Request
{
    public function __construct(
        public readonly string $method,
        public readonly string $path,
    ) {
    }

    public static function fromGlobals(string $basePath): self
    {
        $method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
        $uri = (string) parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
        $base = rtrim($basePath, '/');
        if ($base !== '' && str_starts_with($uri, $base)) {
            $uri = substr($uri, strlen($base)) ?: '/';
        }
        if ($uri === '' || $uri[0] !== '/') {
            $uri = '/' . $uri;
        }

        return new self($method, $uri);
    }

    public function input(string $key, ?string $default = null): ?string
    {
        $v = $_POST[$key] ?? $_GET[$key] ?? null;
        if ($v === null || $v === '') {
            return $default;
        }

        return is_string($v) ? trim($v) : $default;
    }

    /** @return list<string> */
    public function postStringList(string $key): array
    {
        $v = $_POST[$key] ?? null;
        if (!is_array($v)) {
            return [];
        }
        $out = [];
        foreach ($v as $item) {
            if (is_string($item)) {
                $out[] = $item;
            }
        }

        return $out;
    }
}
