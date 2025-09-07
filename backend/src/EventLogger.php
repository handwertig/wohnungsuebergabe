<?php
declare(strict_types=1);

namespace App;

use PDO;

class EventLogger
{
    public static function logProtocolEvent(
        string $protocolId, 
        string $type, 
        string $message, 
        ?string $createdBy = null
    ): bool {
        try {
            $pdo = Database::pdo();
            
            // Generiere UUID
            $uuid = sprintf('%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
                mt_rand(0, 0xffff), mt_rand(0, 0xffff),
                mt_rand(0, 0xffff),
                mt_rand(0, 0x0fff) | 0x4000,
                mt_rand(0, 0x3fff) | 0x8000,
                mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
            );
            
            $stmt = $pdo->prepare("
                INSERT INTO protocol_events (id, protocol_id, type, message, created_by, created_at) 
                VALUES (?, ?, ?, ?, ?, NOW())
            ");
            
            return $stmt->execute([
                $uuid,
                $protocolId,
                $type,
                $message,
                $createdBy ?? 'system'
            ]);
            
        } catch (\Exception $e) {
            error_log('EventLogger Error: ' . $e->getMessage());
            return false;
        }
    }
}
