<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;

/**
 * JSON API foundation for integrations (NRS, mobile clients, etc.).
 */
final class ApiV1Controller extends Controller
{
    public function health(): void
    {
        header('Content-Type: application/json; charset=UTF-8');
        echo json_encode([
            'ok' => true,
            'service' => 'billo',
            'version' => '1',
        ], JSON_THROW_ON_ERROR);
        exit;
    }

    /** Reserved for NRS callbacks — return 501 until contract is finalized. */
    public function nrsWebhookPlaceholder(): void
    {
        header('Content-Type: application/json; charset=UTF-8');
        http_response_code(501);
        echo json_encode([
            'ok' => false,
            'message' => 'NRS webhook URL not implemented; configure push from Billo to NRS or agree on callback contract.',
        ], JSON_THROW_ON_ERROR);
        exit;
    }
}
