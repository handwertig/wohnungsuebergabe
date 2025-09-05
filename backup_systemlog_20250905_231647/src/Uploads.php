<?php
declare(strict_types=1);

namespace App;

final class Uploads
{
    private const MAX_SIZE = 10_485_760; // 10 MB
    private const ALLOWED = ['image/jpeg','image/png'];

    private static function ensureStorage(): string
    {
        $base = __DIR__ . '/../storage/uploads';
        if (!is_dir($base)) { @mkdir($base, 0775, true); }
        return realpath($base) ?: $base;
    }

    public static function saveDraftFile(string $draftId, array $file, string $section, ?string $roomKey = null): array
    {
        $base = self::ensureStorage();
        $dir  = $base . '/' . $draftId;
        if (!is_dir($dir)) { @mkdir($dir, 0775, true); }
        return self::saveFile($dir, $file, $section, $roomKey);
    }

    public static function saveProtocolFile(string $protocolId, array $file, string $section, ?string $roomKey = null): array
    {
        $base = self::ensureStorage();
        $dir  = $base . '/' . $protocolId;
        if (!is_dir($dir)) { @mkdir($dir, 0775, true); }
        return self::saveFile($dir, $file, $section, $roomKey);
    }

    private static function saveFile(string $dir, array $file, string $section, ?string $roomKey): array
    {
        $name = preg_replace('~[^a-zA-Z0-9._-]+~', '_', $file['name'] ?? 'upload');
        $ts   = date('Ymd_His');
        $dest = $dir . '/' . $ts . '-' . $name;

        if (!isset($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) {
            throw new \RuntimeException('Ungültiger Upload.');
        }
        if (($file['size'] ?? 0) > self::MAX_SIZE) {
            throw new \RuntimeException('Datei zu groß (max. 10 MB).');
        }
        $mime = mime_content_type($file['tmp_name']) ?: ($file['type'] ?? 'application/octet-stream');
        if (!in_array($mime, self::ALLOWED, true)) {
            throw new \RuntimeException('Nur JPG oder PNG erlaubt.');
        }

        if (!move_uploaded_file($file['tmp_name'], $dest)) {
            throw new \RuntimeException('Upload konnte nicht gespeichert werden.');
        }

        $thumb = self::makeThumb($dest, $mime);

        return [
            'path'    => $dest,
            'thumb'   => $thumb, // kann null sein, wenn Thumb fehlgeschlagen
            'name'    => $name,
            'mime'    => $mime,
            'size'    => (int)($file['size'] ?? filesize($dest) ?: 0),
            'section' => $section,
            'room_key'=> $roomKey,
        ];
    }

    /** Erzeugt ein ~400px breites Thumbnail (JPEG/PNG) */
    private static function makeThumb(string $src, string $mime): ?string
    {
        try {
            if ($mime === 'image/jpeg') {
                $im = imagecreatefromjpeg($src);
                $ext = '.jpg';
            } elseif ($mime === 'image/png') {
                $im = imagecreatefrompng($src);
                $ext = '.png';
            } else {
                return null;
            }
            if (!$im) return null;

            $w = imagesx($im); $h = imagesy($im);
            $newW = 400; $newH = (int) round($h * ($newW / max(1,$w)));
            $thumb = imagecreatetruecolor($newW, $newH);
            imagealphablending($thumb, false); imagesavealpha($thumb, true);
            imagecopyresampled($thumb, $im, 0,0,0,0, $newW,$newH, $w,$h);

            $dest = preg_replace('~(\.[a-zA-Z0-9]+)$~', '.thumb$1', $src);
            if ($mime === 'image/jpeg') {
                imagejpeg($thumb, $dest, 82);
            } else {
                imagepng($thumb, $dest, 6);
            }
            imagedestroy($thumb); imagedestroy($im);
            return $dest;
        } catch (\Throwable $e) {
            return null;
        }
    }
}
