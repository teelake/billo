<?php

declare(strict_types=1);

namespace App\Services;

/**
 * Validates, resizes, and re-encodes landing-page images (GD) — trusted logos, portraits, hero.
 */
final class LandingImageService
{
    public const PROFILE_TRUSTED = 'trusted';

    public const PROFILE_PORTRAIT = 'portrait';

    public const PROFILE_HERO = 'hero';

    private const MAX_SRC_EDGE = 4096;

    /** @var array<string, array{max_upload:int, max_edge:int, jpeg:int, png:int}> */
    private const PROFILES = [
        self::PROFILE_TRUSTED => ['max_upload' => 1048576, 'max_edge' => 480, 'jpeg' => 90, 'png' => 6],
        self::PROFILE_PORTRAIT => ['max_upload' => 1048576, 'max_edge' => 384, 'jpeg' => 90, 'png' => 6],
        self::PROFILE_HERO => ['max_upload' => 2621440, 'max_edge' => 1680, 'jpeg' => 90, 'png' => 6],
    ];

    /**
     * @param array{name?:string,type?:string,tmp_name?:string,error?:int,size?:int} $file
     * @return array{ok:true, ext:'jpg'|'png'}|array{ok:false, error:string}
     */
    public static function processAndSave(array $file, string $destBasePath, string $profile): array
    {
        if (!isset(self::PROFILES[$profile])) {
            return ['ok' => false, 'error' => 'Invalid image profile.'];
        }
        if (!extension_loaded('gd')) {
            return ['ok' => false, 'error' => 'Image processing requires the PHP GD extension.'];
        }

        $cfg = self::PROFILES[$profile];
        $err = (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE);
        if ($err === UPLOAD_ERR_NO_FILE) {
            return ['ok' => false, 'error' => 'No file was uploaded.'];
        }
        if ($err !== UPLOAD_ERR_OK) {
            return ['ok' => false, 'error' => self::uploadErrMessage($err, $cfg['max_upload'])];
        }

        $tmp = (string) ($file['tmp_name'] ?? '');
        if ($tmp === '' || !is_uploaded_file($tmp)) {
            return ['ok' => false, 'error' => 'Invalid upload.'];
        }

        $size = (int) ($file['size'] ?? 0);
        if ($size > $cfg['max_upload']) {
            $mb = round($cfg['max_upload'] / 1048576, 1);

            return ['ok' => false, 'error' => "File must be {$mb} MB or smaller."];
        }

        $info = @getimagesize($tmp);
        if ($info === false || !isset($info[0], $info[1], $info[2])) {
            return ['ok' => false, 'error' => 'Upload a JPG, PNG, GIF, or WebP image.'];
        }

        $w = (int) $info[0];
        $h = (int) $info[1];
        if ($w < 1 || $h < 1 || $w > self::MAX_SRC_EDGE || $h > self::MAX_SRC_EDGE) {
            return ['ok' => false, 'error' => 'Image dimensions are too large.'];
        }

        $type = (int) $info[2];
        $im = self::imageCreateFromUpload($tmp, $type);
        if ($im === false) {
            return ['ok' => false, 'error' => 'Could not read this image.'];
        }

        $usePng = self::shouldUsePng($type);
        $im = self::resizeDownIfNeeded($im, $w, $h, $usePng, $cfg['max_edge']);
        if ($im === false) {
            return ['ok' => false, 'error' => 'Could not process this image.'];
        }

        $ext = $usePng ? 'png' : 'jpg';
        $destDir = dirname($destBasePath);
        if (!is_dir($destDir) && !mkdir($destDir, 0755, true) && !is_dir($destDir)) {
            imagedestroy($im);

            return ['ok' => false, 'error' => 'Could not create storage directory.'];
        }

        $dest = $destBasePath . '.' . $ext;
        $ok = $usePng
            ? imagepng($im, $dest, $cfg['png'])
            : imagejpeg($im, $dest, $cfg['jpeg']);
        imagedestroy($im);

        if ($ok !== true) {
            return ['ok' => false, 'error' => 'Could not save the image.'];
        }

        return ['ok' => true, 'ext' => $ext];
    }

    public static function isSafeStoredPath(string $path, string $segment): bool
    {
        $path = str_replace('\\', '/', trim($path));
        if ($path === '' || str_contains($path, '..')) {
            return false;
        }
        $prefix = 'storage/landing/' . $segment . '/';

        return str_starts_with($path, $prefix) && preg_match('#^storage/landing/' . preg_quote($segment, '#') . '/[a-zA-Z0-9._-]+$#', $path) === 1;
    }

    public static function landingRoot(): string
    {
        return BILLO_ROOT . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'landing';
    }

    /**
     * @return list<array{name:string,type:string,tmp_name:string,error:int,size:int}>
     */
    public static function normalizeFilesList(string $fieldName): array
    {
        if (empty($_FILES[$fieldName]) || !isset($_FILES[$fieldName]['name'])) {
            return [];
        }
        $f = $_FILES[$fieldName];
        if (!is_array($f['name'])) {
            return [[
                'name' => (string) $f['name'],
                'type' => (string) ($f['type'] ?? ''),
                'tmp_name' => (string) ($f['tmp_name'] ?? ''),
                'error' => (int) ($f['error'] ?? UPLOAD_ERR_NO_FILE),
                'size' => (int) ($f['size'] ?? 0),
            ]];
        }
        $out = [];
        foreach ($f['name'] as $idx => $name) {
            $out[] = [
                'name' => is_string($name) ? $name : '',
                'type' => is_string($f['type'][$idx] ?? null) ? $f['type'][$idx] : '',
                'tmp_name' => is_string($f['tmp_name'][$idx] ?? null) ? $f['tmp_name'][$idx] : '',
                'error' => (int) ($f['error'][$idx] ?? UPLOAD_ERR_NO_FILE),
                'size' => (int) ($f['size'][$idx] ?? 0),
            ];
        }

        return $out;
    }

    public static function removeDirContents(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }
        foreach (scandir($dir) ?: [] as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }
            $p = $dir . DIRECTORY_SEPARATOR . $item;
            if (is_file($p)) {
                @unlink($p);
            } elseif (is_dir($p)) {
                self::removeDirContents($p);
                @rmdir($p);
            }
        }
    }

    public static function deleteDir(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }
        self::removeDirContents($dir);
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
    private static function resizeDownIfNeeded($im, int $w, int $h, bool $preserveAlpha, int $maxEdge)
    {
        $long = max($w, $h);
        if ($long <= $maxEdge) {
            return $im;
        }

        $ratio = $maxEdge / $long;
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

    private static function uploadErrMessage(int $err, int $maxBytes): string
    {
        return match ($err) {
            UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => 'File is too large.',
            UPLOAD_ERR_PARTIAL => 'Upload was interrupted.',
            default => 'Upload failed.',
        };
    }
}
