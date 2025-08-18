<?php
declare(strict_types=1);

namespace App;

use PDO;

final class AuditLogger
{
    /**
     * @param array<string, mixed>|null $changes
     */
    public static function log(string $entity, string $entityId, string $action, ?array $changes = null): void
    {
        $pdo = Database::pdo();
        $user = Auth::user();
        $stmt = $pdo->prepare('INSERT INTO audit_log (id,entity,entity_id,action,changes,user_id,created_at) VALUES (UUID(),?,?,?,?,?,NOW())');
        $json = $changes ? json_encode($changes, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : null;
        $stmt->execute([$entity, $entityId, $action, $json, $user['id'] ?? null]);
    }
}
