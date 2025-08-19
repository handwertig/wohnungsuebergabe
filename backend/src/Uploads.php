<?php
declare(strict_types=1);

namespace App;

final class Uploads
{
    public static function ensureStorage(): string
    {
        $base = __DIR__ . '/../storage/uploads';
        if (!is_dir($base)) {
            @mkdir($base, 0775, true);
        }
        return realpath($base) ?: $base;
    }

    public static function saveDraftFile(string $draftId, array $file, string $section, ?string $roomKey = null): array
    {
        $base = self::ensureStorage();
        $dir  = $base . '/' . $draftId;
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }
        $name = preg_replace('~[^a-zA-Z0-9._-]+~', '_', $file['name'] ?? 'upload');
        $ts   = date('Ymd_His');
        $dest = $dir . '/' . $ts . '-' . $name;

        if (!is_uploaded_file($file['tmp_name'] ?? '')) {
            throw new \RuntimeException('Ungültiger Upload.');
        }
        if (($file['size'] ?? 0) > 10 * 1024 * 1024) { // 10MB
            throw new \RuntimeException('Datei zu groß (max. 10MB).');
        }

        if (!move_uploaded_file($file['tmp_name'], $dest)) {
            throw new \RuntimeException('Upload konnte nicht gespeichert werden.');
        }

        return [
            'path' => $dest,
            'name' => $name,
            'mime' => mime_content_type($dest) ?: ($file['type'] ?? 'application/octet-stream'),
            'size' => (int)($file['size'] ?? filesize($dest) ?: 0),
            'section' => $section,
            'room_key' => $roomKey,
        ];
    }
}
