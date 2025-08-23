<?php
declare(strict_types=1);

namespace App;

use PDO;

final class Settings
{
    public static function get(string $name, ?string $default = null): ?string
    {
        $pdo = Database::pdo();
        $st = $pdo->prepare("SELECT value FROM app_settings WHERE name=? LIMIT 1");
        $st->execute([$name]);
        $v = $st->fetchColumn();
        return $v !== false ? (string)$v : $default;
    }

    /** @param array<string,string|null> $pairs */
    public static function setMany(array $pairs): void
    {
        $pdo = Database::pdo();
        $ins = $pdo->prepare("INSERT INTO app_settings (name,value,updated_at) VALUES (?,?,NOW())
                              ON DUPLICATE KEY UPDATE value=VALUES(value), updated_at=NOW()");
        foreach ($pairs as $k=>$v) {
            $ins->execute([$k, $v]);
        }
    }
}
