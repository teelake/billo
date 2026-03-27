<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Config;

/**
 * Google OAuth 2.0 authorization code flow (openid email profile).
 */
final class GoogleOAuthService
{
    private const AUTH_URL = 'https://accounts.google.com/o/oauth2/v2/auth';

    private const TOKEN_URL = 'https://oauth2.googleapis.com/token';

    private const USERINFO_URL = 'https://www.googleapis.com/oauth2/v3/userinfo';

    public function isEnabled(): bool
    {
        $on = Config::get('oauth.google.enabled', false);
        if ($on !== true && $on !== 1 && $on !== '1') {
            return false;
        }
        $id = trim((string) Config::get('oauth.google.client_id', ''));
        $secret = trim((string) Config::get('oauth.google.client_secret', ''));

        return $id !== '' && $secret !== '';
    }

    public function redirectUri(): string
    {
        return rtrim(billo_url('/auth/google/callback'), '/');
    }

    public function buildAuthorizeUrl(string $state): string
    {
        $clientId = trim((string) Config::get('oauth.google.client_id', ''));
        $params = [
            'client_id' => $clientId,
            'redirect_uri' => $this->redirectUri(),
            'response_type' => 'code',
            'scope' => 'openid email profile',
            'state' => $state,
            'access_type' => 'online',
            'prompt' => 'select_account',
        ];

        return self::AUTH_URL . '?' . http_build_query($params, '', '&', PHP_QUERY_RFC3986);
    }

    /**
     * @return array{sub:string,email:string,name:string,email_verified:bool}|string error message
     */
    public function exchangeCodeForProfile(string $code): array|string
    {
        $code = trim($code);
        if ($code === '') {
            return 'Missing authorization code.';
        }

        $clientId = trim((string) Config::get('oauth.google.client_id', ''));
        $secret = trim((string) Config::get('oauth.google.client_secret', ''));
        if ($clientId === '' || $secret === '') {
            return 'Google sign-in is not configured.';
        }

        $tokenBody = $this->httpPostForm(self::TOKEN_URL, [
            'code' => $code,
            'client_id' => $clientId,
            'client_secret' => $secret,
            'redirect_uri' => $this->redirectUri(),
            'grant_type' => 'authorization_code',
        ]);
        if (is_string($tokenBody)) {
            return $tokenBody;
        }

        $accessToken = $tokenBody['access_token'] ?? null;
        if (!is_string($accessToken) || $accessToken === '') {
            return 'Could not obtain access token from Google.';
        }

        $raw = $this->httpGetBearer(self::USERINFO_URL, $accessToken);
        if (is_string($raw)) {
            return $raw;
        }

        $sub = isset($raw['sub']) ? trim((string) $raw['sub']) : '';
        $email = isset($raw['email']) ? strtolower(trim((string) $raw['email'])) : '';
        $name = isset($raw['name']) ? trim((string) $raw['name']) : '';
        $verified = !empty($raw['email_verified']) || (isset($raw['email_verified']) && $raw['email_verified'] === true);

        if ($sub === '' || $email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return 'Google did not return a valid email address.';
        }
        if (!$verified) {
            return 'Your Google account email is not verified. Verify it with Google, then try again.';
        }
        if ($name === '') {
            $name = preg_replace('/@.+$/', '', $email) ?? 'User';
            $name = trim($name) !== '' ? trim($name) : 'User';
        }
        if (function_exists('mb_strlen') && mb_strlen($name, 'UTF-8') > 120) {
            $name = mb_substr($name, 0, 120, 'UTF-8');
        } elseif (strlen($name) > 120) {
            $name = substr($name, 0, 120);
        }

        return [
            'sub' => $sub,
            'email' => $email,
            'name' => $name,
            'email_verified' => true,
        ];
    }

    /**
     * @return array<string, mixed>|string
     */
    private function httpPostForm(string $url, array $fields): array|string
    {
        $ch = curl_init($url);
        if ($ch === false) {
            return 'Could not start HTTP request.';
        }
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => http_build_query($fields),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => ['Content-Type: application/x-www-form-urlencoded'],
            CURLOPT_TIMEOUT => 25,
        ]);
        $raw = curl_exec($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        if ($raw === false || !is_string($raw)) {
            return 'Network error talking to Google.';
        }
        if ($code < 200 || $code >= 300) {
            error_log('Google OAuth token HTTP ' . $code . ': ' . $raw);

            return 'Google rejected the sign-in request. Try again.';
        }
        try {
            /** @var mixed $json */
            $json = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
        } catch (\Throwable) {
            return 'Invalid response from Google.';
        }

        return is_array($json) ? $json : 'Invalid response from Google.';
    }

    /**
     * @return array<string, mixed>|string
     */
    private function httpGetBearer(string $url, string $token): array|string
    {
        $ch = curl_init($url);
        if ($ch === false) {
            return 'Could not start HTTP request.';
        }
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . $token],
            CURLOPT_TIMEOUT => 25,
        ]);
        $raw = curl_exec($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        if ($raw === false || !is_string($raw)) {
            return 'Network error talking to Google.';
        }
        if ($code < 200 || $code >= 300) {
            error_log('Google userinfo HTTP ' . $code . ': ' . $raw);

            return 'Could not read your Google profile.';
        }
        try {
            /** @var mixed $json */
            $json = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
        } catch (\Throwable) {
            return 'Invalid profile response from Google.';
        }

        return is_array($json) ? $json : 'Invalid profile response from Google.';
    }
}
