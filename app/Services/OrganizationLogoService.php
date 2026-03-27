<?php

declare(strict_types=1);

namespace App\Services;

/**
 * Validates, optionally resizes, and re-encodes organization logos (GD).
 * Target: ≤1 MiB upload; output optimized JPEG (no alpha) or PNG (alpha) at high quality.
 */
final class OrganizationLogoService
{
    public const MAX_UPLOAD_BYTES = 1048576; // 1 MiB

    private const MAX_SRC_EDGE = 4096;

    private const OUTPUT_MAX_EDGE = 1200;

    private const JPEG_QUALITY = 92;

    private const PNG_LEVEL = 6;

    /**
     * @param array{error?:int, name?:string, size?:int, tmp_name?:string, type?:string} $file One $_FILES entry
     * @return array{ok:true, path:string, ext:'jpg'|'png'}|array{ok:false, error:string}
     */
    public static function processAndStore(array $file, int $organizationId): array
    {
        if (!extension_loaded('gd')) {
            return ['ok' => false, 'error' => 'Logo upload requires the PHP GD extension. Ask your host to enable it.'];
        }

        $err = (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE);
        if ($err === UPLOAD_ERR_NO_FILE) {
            return ['ok' => false, 'error' => 'No file was uploaded.'];
        }
        if ($err !== UPLOAD_ERR_OK) {
            return ['ok' => false, 'error' => self::uploadErrMessage($err)];
        }

        $tmp = (string) ($file['tmp_name'] ?? '');
        if ($tmp === '' || !is_uploaded_file($tmp)) {
            return ['ok' => false, 'error' => 'Invalid upload.'];
        }

        $size = (int) ($file['size'] ?? 0);
        if ($size > self::MAX_UPLOAD_BYTES) {
            return ['ok' => false, 'error' => 'Logo file must be 1 MB or smaller.'];
        }

        $info = @getimagesize($tmp);
        if ($info === false || !isset($info[0], $info[1], $info[2])) {
            return ['ok' => false, 'error' => 'Upload a JPG, PNG, GIF, or WebP image.'];
        }

        $w = (int) $info[0];
        $h = (int) $info[1];
        if ($w < 1 || $h < 1 || $w > self::MAX_SRC_EDGE || $h > self::MAX_SRC_EDGE) {
            return ['ok' => false, 'error' => 'Image is too large (max ' . self::MAX_SRC_EDGE . ' px per side).'];
        }

        $type = (int) $info[2];
        $im = self::imageCreateFromUpload($tmp, $type);
        if ($im === false) {
            return ['ok' => false, 'error' => 'Could not read this image. Try another file.'];
        }

        $usePng = self::shouldUsePng($type);
        $im = self::resizeDownIfNeeded($im, $w, $h, $usePng);
        if ($im === false) {
            return ['ok' => false, 'error' => 'Could not process this image.'];
        }

        $ext = $usePng ? 'png' : 'jpg';

        $dir = self::brandDir($organizationId);
        if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) {
            imagedestroy($im);

            return ['ok' => false, 'error' => 'Could not create storage folder for logos.'];
        }

        self::removeExistingLogos($dir);

        $dest = $dir . DIRECTORY_SEPARATOR . 'logo.' . $ext;
        $ok = $usePng
            ? imagepng($im, $dest, self::PNG_LEVEL)
            : imagejpeg($im, $dest, self::JPEG_QUALITY);
        imagedestroy($im);

        if ($ok !== true) {
            return ['ok' => false, 'error' => 'Could not save the logo file.'];
        }

        $rel = 'storage/branding/' . $organizationId . '/logo.' . $ext;

        return ['ok' => true, 'path' => $rel, 'ext' => $ext];
    }

    public static function brandDir(int $organizationId): string
    {
        return BILLO_ROOT . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'branding' . DIRECTORY_SEPARATOR . $organizationId;
    }

    public static function removeBrandingFiles(int $organizationId): void
    {
        $dir = self::brandDir($organizationId);
        if (!is_dir($dir)) {
            return;
        }
        self::removeExistingLogos($dir);
        @rmdir($dir);
    }

    private static function shouldUsePng(int $originalType): bool
    {
        return in_array($originalType, [IMAGETYPE_PNG, IMAGETYPE_GIF, IMAGETYPE_WEBP], true);
    }

    /**
     * @return GdImage|false|resource
     */
    private static function imageCreateFromUpload(string $tmp, int $type)
    {
        return match ($type) {
            IMAGETYPE_JPEG => imagecreatefromjpeg($tmp),
            IMAGETYPE_PNG => imagecreatefrompng($tmp),
            IMAGETYPE_GIF => imagecreatefromgif($tmp),
            IMAGETYPE_WEBP => function_exists('imagecreatefromwebp') ? imagecreatefromwebp($tmp) : false,
            default => false,
        };
    }

    /**
     * @param GdImage|resource $im
     * @return GdImage|false|resource
     */
    private static function resizeDownIfNeeded($im, int $w, int $h, bool $preserveAlpha)
    {
        $maxEdge = max($w, $h);
        if ($maxEdge <= self::OUTPUT_MAX_EDGE) {
            return $im;
        }

        $ratio = self::OUTPUT_MAX_EDGE / $maxEdge;
        $nw = max(1, (int) round($w * $ratio));
        $nh = max(1, (int) round($h * $ratio));

        $out = imagecreatetruecolor($nw, $nh);
        if ($out === false) {
            imagedestroy($im);

            return false;
        }
        if ($preserveAlpha) {
            imagealphablending($out, false);
            imagesavealpha($out, true);
            $transparent = imagecolorallocatealpha($out, 0, 0, 0, 127);
            imagefilledrectangle($out, 0, 0, $nw, $nh, $transparent);
        } else {
            $white = imagecolorallocate($out, 255, 255, 255);
            imagefilledrectangle($out, 0, 0, $nw, $nh, $white);
        }

        imagealphablending($im, true);
        imagecopyresampled($out, $im, 0, 0, 0, 0, $nw, $nh, $w, $h);
        imagedestroy($im);

        return $out;
    }

    private static function removeExistingLogos(string $dir): void
    {
        foreach (['logo.jpg', 'logo.jpeg', 'logo.png', 'logo.webp'] as $name) {
            $p = $dir . DIRECTORY_SEPARATOR . $name;
            if (is_file($p)) {
                @unlink($p);
            }
        }
    }

    private static function uploadErrMessage(int $err): string
    {
        return match ($err) {
            UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => 'File is too large.',
            UPLOAD_ERR_PARTIAL => 'Upload was interrupted.',
            default => 'Upload failed.',
        };
    }
}
