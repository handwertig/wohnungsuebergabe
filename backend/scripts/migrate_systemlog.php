<?php
/**
 * Migration: SystemLog Tabelle erstellen und initial befüllen
 */

require __DIR__ . '/../vendor/autoload.php';

// .env laden
if (is_file(__DIR__ . '/../.env')) {
    Dotenv\Dotenv::createImmutable(dirname(__DIR__))->load();
}

use App\Database;
use App\SystemLogger;

try {
    $pdo = Database::pdo();
    
    echo "🗄️  Erstelle system_log Tabelle...\n";
    
    // Tabelle erstellen (mit IF NOT EXISTS)
    $pdo->exec("CREATE TABLE IF NOT EXISTS system_log (
        id CHAR(36) PRIMARY KEY,
        user_email VARCHAR(255) NOT NULL DEFAULT 'system',
        user_ip VARCHAR(45) NULL,
        action_type VARCHAR(100) NOT NULL,
        action_description TEXT NOT NULL,
        resource_type VARCHAR(50) NULL,
        resource_id CHAR(36) NULL,
        additional_data JSON NULL,
        request_method VARCHAR(10) NULL,
        request_url VARCHAR(500) NULL,
        user_agent TEXT NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        timestamp DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_user_email (user_email),
        INDEX idx_action_type (action_type),
        INDEX idx_resource (resource_type, resource_id),
        INDEX idx_created_at (created_at),
        INDEX idx_timestamp (timestamp)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    
    echo "✅ Tabelle system_log erstellt/überprüft\n";
    
    // Prüfe ob bereits Daten vorhanden sind
    $stmt = $pdo->query("SELECT COUNT(*) FROM system_log");
    $count = (int)$stmt->fetchColumn();
    
    if ($count === 0) {
        echo "📝 Füge initiale Test-Daten hinzu...\n";
        
        // Lösche existierende Test-Daten (falls vorhanden)
        $pdo->exec("DELETE FROM system_log WHERE user_email IN ('system', 'test@example.com')");
        
        // Füge umfassende Test-Einträge hinzu
        $now = date('Y-m-d H:i:s');
        
        $testEntries = [
            ['admin@handwertig.com', 'login', 'Administrator hat sich angemeldet', null, null, '192.168.1.100', 'NOW() - INTERVAL 2 HOUR'],
            ['admin@handwertig.com', 'settings_viewed', 'System-Log Seite aufgerufen', null, null, '192.168.1.100', 'NOW() - INTERVAL 1 HOUR'],
            ['user@handwertig.com', 'protocol_created', 'Neues Einzug-Protokoll für Mustermann erstellt', 'protocol', null, '192.168.1.101', 'NOW() - INTERVAL 45 MINUTE'],
            ['admin@handwertig.com', 'pdf_generated', 'PDF für Protokoll generiert (Version 1)', 'protocol', null, '192.168.1.100', 'NOW() - INTERVAL 30 MINUTE'],
            ['user@handwertig.com', 'email_sent', 'E-Mail an Mieter (max@mustermann.de) erfolgreich versendet', 'protocol', null, '192.168.1.101', 'NOW() - INTERVAL 15 MINUTE'],
            ['system', 'system_setup', 'Wohnungsübergabe-Software erfolgreich installiert', null, null, '127.0.0.1', 'NOW() - INTERVAL 10 MINUTE'],
            ['admin@handwertig.com', 'settings_updated', 'Einstellungen aktualisiert: branding', null, null, '192.168.1.100', 'NOW() - INTERVAL 5 MINUTE'],
            ['system', 'migration_executed', 'SystemLogger erfolgreich konfiguriert', null, null, '127.0.0.1', 'NOW()']
        ];
        
        foreach ($testEntries as $entry) {
            $stmt = $pdo->prepare("
                INSERT INTO system_log (id, user_email, action_type, action_description, resource_type, resource_id, user_ip, created_at) VALUES 
                (UUID(), ?, ?, ?, ?, UUID(), ?, $entry[6])
            ");
            $stmt->execute([$entry[0], $entry[1], $entry[2], $entry[3], $entry[5]]);
        }
        
        // Füge noch mehr realistische Test-Einträge hinzu
        for ($i = 0; $i < 25; $i++) {
            $actions = [
                'protocol_viewed', 'pdf_viewed', 'export_generated', 'protocol_updated', 
                'login', 'logout', 'settings_viewed', 'email_sent', 'protocol_draft_saved'
            ];
            $users = ['admin@handwertig.com', 'user@handwertig.com', 'manager@handwertig.com', 'eigentümer@example.com'];
            $entities = ['protocol', 'draft', 'user', 'object'];
            
            $action = $actions[array_rand($actions)];
            $user = $users[array_rand($users)];
            $entity = $entities[array_rand($entities)];
            $description = ucfirst(str_replace('_', ' ', $action)) . ' - Entry #' . ($i + 1);
            $ip = '192.168.1.' . (100 + ($i % 20));
            $minutes = $i * 15 + 30;
            
            $stmt = $pdo->prepare("
                INSERT INTO system_log (id, user_email, action_type, action_description, resource_type, resource_id, user_ip, created_at) 
                VALUES (UUID(), ?, ?, ?, ?, UUID(), ?, NOW() - INTERVAL ? MINUTE)
            ");
            $stmt->execute([$user, $action, $description, $entity, $ip, $minutes]);
        }
        
        echo "✅ " . (count($testEntries) + 25) . " Test-Log-Einträge hinzugefügt\n";
    } else {
        echo "ℹ️  Bereits $count Log-Einträge vorhanden - keine Test-Daten hinzugefügt\n";
    }
    
    // Verifying the table structure
    $stmt = $pdo->query("SHOW TABLES LIKE 'system_log'");
    if ($stmt->rowCount() > 0) {
        echo "✅ Tabelle system_log erfolgreich verifiziert\n";
        
        // Show current count
        $stmt = $pdo->query("SELECT COUNT(*) FROM system_log");
        $finalCount = (int)$stmt->fetchColumn();
        echo "📊 Gesamt Log-Einträge: $finalCount\n";
        
        // Show recent entries
        $stmt = $pdo->query("SELECT user_email, action_type, action_description, created_at FROM system_log ORDER BY created_at DESC LIMIT 5");
        echo "\n📋 Letzte 5 Log-Einträge:\n";
        echo "-------------------------------\n";
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            echo "• " . $row['created_at'] . " | " . $row['user_email'] . " | " . $row['action_type'] . " | " . substr($row['action_description'], 0, 50) . "...\n";
        }
    } else {
        echo "❌ Tabelle system_log konnte nicht erstellt werden!\n";
        exit(1);
    }
    
} catch (\Throwable $e) {
    echo "❌ Fehler bei der Migration: " . $e->getMessage() . "\n";
    exit(1);
}

echo "\n🎉 SystemLog Migration erfolgreich abgeschlossen!\n";
