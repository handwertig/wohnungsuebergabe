<?php
/**
 * Debug und Fix für Event-Logging
 */

try {
    $pdo = new PDO('mysql:host=db;port=3306;dbname=app;charset=utf8mb4', 'root', 'root');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "🔍 DEBUG EVENT-LOGGING\n";
    echo "======================\n\n";
    
    // 1. Prüfe protocol_events Tabelle
    echo "1. Prüfe protocol_events Tabelle:\n";
    try {
        $stmt = $pdo->query("SHOW TABLES LIKE 'protocol_events'");
        if ($stmt->rowCount() > 0) {
            echo "   ✅ Tabelle existiert\n";
            
            // Zeige Struktur
            $stmt = $pdo->query("DESCRIBE protocol_events");
            echo "   Spalten:\n";
            while ($row = $stmt->fetch()) {
                echo "   - " . $row['Field'] . " (" . $row['Type'] . ")\n";
            }
        } else {
            echo "   ❌ Tabelle existiert nicht - erstelle sie...\n";
            
            $pdo->exec("
                CREATE TABLE protocol_events (
                    id VARCHAR(36) PRIMARY KEY,
                    protocol_id VARCHAR(36) NOT NULL,
                    type VARCHAR(50) NOT NULL,
                    message TEXT,
                    created_by VARCHAR(255),
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    INDEX idx_protocol_id (protocol_id),
                    INDEX idx_type (type),
                    INDEX idx_created_at (created_at)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            ");
            
            echo "   ✅ Tabelle erstellt!\n";
        }
    } catch (Exception $e) {
        echo "   ❌ Fehler: " . $e->getMessage() . "\n";
    }
    
    // 2. Zeige vorhandene Events
    echo "\n2. Vorhandene Events:\n";
    $stmt = $pdo->query("SELECT * FROM protocol_events ORDER BY created_at DESC LIMIT 10");
    $events = $stmt->fetchAll();
    
    if (empty($events)) {
        echo "   Keine Events gefunden\n";
    } else {
        foreach ($events as $event) {
            echo "   - [{$event['id']}] Protocol: {$event['protocol_id']}\n";
            echo "     Type: {$event['type']}, Message: {$event['message']}\n";
            echo "     Created: {$event['created_at']} by {$event['created_by']}\n\n";
        }
    }
    
    // 3. Prüfe Protocol IDs
    echo "\n3. Protokolle in DB:\n";
    $stmt = $pdo->query("SELECT id, type, tenant_name FROM protocols LIMIT 5");
    while ($row = $stmt->fetch()) {
        echo "   - ID: {$row['id']}\n";
        echo "     Type: {$row['type']}, Mieter: {$row['tenant_name']}\n";
        
        // Prüfe Events für dieses Protokoll
        $stmt2 = $pdo->prepare("SELECT COUNT(*) FROM protocol_events WHERE protocol_id = ?");
        $stmt2->execute([$row['id']]);
        $count = $stmt2->fetchColumn();
        echo "     Events: $count\n\n";
    }
    
    // 4. Füge Test-Event hinzu
    echo "\n4. Füge Test-Event hinzu:\n";
    $protocols = $pdo->query("SELECT id FROM protocols LIMIT 1")->fetch();
    if ($protocols) {
        $protocolId = $protocols['id'];
        $uuid = sprintf('%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
            mt_rand(0, 0xffff), mt_rand(0, 0xffff),
            mt_rand(0, 0xffff),
            mt_rand(0, 0x0fff) | 0x4000,
            mt_rand(0, 0x3fff) | 0x8000,
            mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
        );
        
        $stmt = $pdo->prepare("
            INSERT INTO protocol_events (id, protocol_id, type, message, created_by, created_at) 
            VALUES (?, ?, 'test', 'Test-Event vom Debug-Script', 'debug_script', NOW())
        ");
        $stmt->execute([$uuid, $protocolId]);
        
        echo "   ✅ Test-Event hinzugefügt für Protocol: $protocolId\n";
    }
    
    // 5. Lösche "other" Events
    echo "\n5. Bereinige 'other' Events:\n";
    $stmt = $pdo->query("SELECT COUNT(*) FROM protocol_events WHERE type = 'other'");
    $count = $stmt->fetchColumn();
    if ($count > 0) {
        echo "   Gefunden: $count 'other' Events\n";
        $pdo->exec("DELETE FROM protocol_events WHERE type = 'other'");
        echo "   ✅ Gelöscht\n";
    } else {
        echo "   Keine 'other' Events gefunden\n";
    }
    
    echo "\n======================\n";
    echo "✅ Debug abgeschlossen\n\n";
    
} catch (Exception $e) {
    echo "❌ FEHLER: " . $e->getMessage() . "\n";
    echo "Stack: " . $e->getTraceAsString() . "\n";
}
