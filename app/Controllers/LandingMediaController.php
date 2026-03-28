<?php

declare(strict_types=1);

namespace App\Controllers;

/**
 * Serves public landing images from storage/landing/{trusted|portraits|hero}/.
 */
final class LandingMediaController
{
    /** Try to handle GET /media/landing/... ; returns true if a response was sent. */
    public function tryServe(string $path): bool
    {
        if (preg_match('#^/media/landing/(trusted|portraits)/([0-9]+)\\.(jpe?g|png)$#i', $path, $m)) {
            $segment = $m[1];
            $ext = strtolower($m[3]) === 'jpeg' ? 'jpg' : strtolower($m[3]);
            $basename = $m[2] . '.' . $ext;
        } elseif (preg_match('#^/media/landing/hero/hero\\.(jpe?g|png)$#i', $path, $m)) {
            $segment = 'hero';
            $ext = strtolower($m[1]) === 'jpeg' ? 'jpg' : strtolower($m[1]);
            $basename = 'hero.' . $ext;
        } else {
            return false;
        }

        $root = realpath(BILLO_ROOT . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'landing');
        if ($root === false) {
            http_response_code(404);

            return true;
        }

        $full = realpath($root . DIRECTORY_SEPARATOR . $segment . DIRECTORY_SEPARATOR . $basename);
        if ($full === false || !str_starts_with($full, $root . DIRECTORY_SEPARATOR)) {
            http_response_code(404);

            return true;
        }

        if (!is_file($full)) {
            http_response_code(404);

            return true;
        }

        $mime = @mime_content_type($full);
        if (!is_string($mime) || !str_starts_with($mime, 'image/')) {
            http_response_code(404);

            return true;
        }

        header('Content-Type: ' . $mime);
        header('Cache-Control: public, max-age=86400');
        readfile($full);

        return true;
    }
}
