<?php
/**
 * SystemLog Initialisierung und Test
 * Führt alle notwendigen Schritte aus, um das System-Log funktionsfähig zu machen
 */

require __DIR__ . '/../vendor/autoload.php';

// .env laden
if (is_file(__DIR__ . '/../.env')) {
    Dotenv\Dotenv::createImmutable(dirname(__DIR__))->load();
}

use App\Database;
use App\SystemLogger;

echo "🔧 Wohnungsübergabe - SystemLog Initialisierung\n";
echo "================================================\n\n";

try {
    echo "1️⃣  Verbinde zur Datenbank...\n";
    $pdo = Database::pdo();
    echo "✅ Datenbankverbindung erfolgreich\n\n";
    
    echo "2️⃣  Prüfe system_log Tabelle...\n";
    $stmt = $pdo->query("SHOW TABLES LIKE 'system_log'");
    if ($stmt->rowCount() === 0) {
        echo "❌ Tabelle system_log existiert nicht - erstelle sie...\n";
        
        $pdo->exec("CREATE TABLE system_log (
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
        
        echo "✅ Tabelle system_log erfolgreich erstellt\n";
    } else {
        echo "✅ Tabelle system_log existiert bereits\n";
    }
    
    echo "\n3️⃣  Prüfe aktuelle Log-Einträge...\n";
    $stmt = $pdo->query("SELECT COUNT(*) FROM system_log");
    $count = (int)$stmt->fetchColumn();
    echo "📊 Aktuelle Log-Einträge: $count\n";
    
    if ($count < 10) {
        echo "📝 Füge Test-Daten hinzu...\n";
        
        // Moderne Test-Einträge hinzufügen
        $testData = [
            ['system', 'system_initialization', 'SystemLog erfolgreich initialisiert', 'NOW() - INTERVAL 2 HOUR'],
            ['admin@handwertig.com', 'login', 'Administrator Anmeldung', 'NOW() - INTERVAL 1 HOUR 45 MINUTE'],
            ['admin@handwertig.com', 'settings_viewed', 'Einstellungen-Seite aufgerufen', 'NOW() - INTERVAL 1 HOUR 30 MINUTE'],
            ['user@handwertig.com', 'protocol_created', 'Neues Einzug-Protokoll für Familie Mueller erstellt', 'NOW() - INTERVAL 1 HOUR 15 MINUTE'],
            ['admin@handwertig.com', 'pdf_generated', 'PDF-Export für Protokoll generiert', 'NOW() - INTERVAL 1 HOUR'],
            ['user@handwertig.com', 'email_sent', 'E-Mail an Eigentümer erfolgreich versendet', 'NOW() - INTERVAL 45 MINUTE'],
            ['manager@handwertig.com', 'protocol_viewed', 'Protokoll in der Übersicht angesehen', 'NOW() - INTERVAL 30 MINUTE'],
            ['admin@handwertig.com', 'settings_updated', 'Mail-Einstellungen aktualisiert', 'NOW() - INTERVAL 15 MINUTE'],
            ['system', 'automated_task', 'Automatische Datenbank-Bereinigung ausgeführt', 'NOW() - INTERVAL 10 MINUTE'],
            ['admin@handwertig.com', 'systemlog_viewed', 'System-Log Seite aufgerufen', 'NOW() - INTERVAL 5 MINUTE']
        ];
        
        foreach ($testData as $data) {
            $stmt = $pdo->prepare("
                INSERT INTO system_log (id, user_email, action_type, action_description, user_ip, created_at) 
                VALUES (UUID(), ?, ?, ?, '192.168.1.100', $data[3])
            ");
            $stmt->execute([$data[0], $data[1], $data[2]]);
        }
        
        // Zusätzliche realistische Einträge
        for ($i = 0; $i < 15; $i++) {
            $actions = ['protocol_viewed', 'pdf_downloaded', 'settings_accessed', 'data_exported', 'login', 'logout'];
            $users = ['admin@handwertig.com', 'user@handwertig.com', 'manager@handwertig.com'];
            $descriptions = [
                'Protokoll-Details angesehen',
                'PDF-Dokument heruntergeladen',
                'Einstellungen aufgerufen',
                'Daten exportiert',
                'Benutzer angemeldet',
                'Benutzer abgemeldet'
            ];
            
            $action = $actions[array_rand($actions)];
            $user = $users[array_rand($users)];
            $description = $descriptions[array_rand($descriptions)] . " - Test Entry " . ($i + 1);
            $minutes = ($i + 1) * 20;
            
            $stmt = $pdo->prepare("
                INSERT INTO system_log (id, user_email, action_type, action_description, user_ip, created_at) 
                VALUES (UUID(), ?, ?, ?, '192.168.1.101', NOW() - INTERVAL ? MINUTE)
            ");
            $stmt->execute([$user, $action, $description, $minutes]);
        }
        
        echo "✅ " . (count($testData) + 15) . " Test-Einträge hinzugefügt\n";
    }
    
    echo "\n4️⃣  Teste SystemLogger Klasse...\n";
    
    // Test der SystemLogger-Methoden
    SystemLogger::log('test_initialization', 'SystemLogger Test-Initialisierung');
    echo "✅ Basis-Logging funktioniert\n";
    
    SystemLogger::logProtocolCreated('test-protocol-123', 'Test Mieter Schmidt', 'einzug');
    echo "✅ Protokoll-Logging funktioniert\n";
    
    $testLogs = SystemLogger::getLogs(1, 5);
    echo "✅ Log-Abfrage funktioniert - " . count($testLogs['logs']) . " Einträge geladen\n";
    
    $actions = SystemLogger::getAvailableActions();
    echo "✅ Action-Filter funktioniert - " . count($actions) . " Aktionen verfügbar\n";
    
    echo "\n5️⃣  Finale Verifikation...\n";
    $stmt = $pdo->query("SELECT COUNT(*) FROM system_log");
    $finalCount = (int)$stmt->fetchColumn();
    echo "📊 Gesamt Log-Einträge nach Initialisierung: $finalCount\n";
    
    // Zeige letzte Einträge
    $stmt = $pdo->query("
        SELECT user_email, action_type, action_description, created_at 
        FROM system_log 
        ORDER BY created_at DESC 
        LIMIT 5
    ");
    
    echo "\n📋 Letzte 5 Log-Einträge:\n";
    echo "----------------------------------------\n";
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $time = date('H:i:s', strtotime($row['created_at']));
        $user = substr($row['user_email'], 0, 15);
        $action = substr($row['action_type'], 0, 20);
        $desc = substr($row['action_description'], 0, 40);
        echo "• $time | $user | $action | $desc...\n";
    }
    
    echo "\n🎉 SystemLog Initialisierung erfolgreich abgeschlossen!\n";
    echo "========================================================\n";
    echo "\n✅ Alles bereit! Sie können nun:\n";
    echo "   • http://localhost:8080/login aufrufen\n";
    echo "   • Sich anmelden (wird geloggt)\n";
    echo "   • Zu Einstellungen > System-Log navigieren\n";
    echo "   • Die Log-Einträge einsehen\n\n";
    
} catch (\Throwable $e) {
    echo "❌ Fehler bei der SystemLog Initialisierung:\n";
    echo "   " . $e->getMessage() . "\n";
    echo "   Datei: " . $e->getFile() . ":" . $e->getLine() . "\n\n";
    
    echo "🔍 Debugging-Hilfe:\n";
    echo "   1. Prüfen Sie die Docker Container: docker-compose ps\n";
    echo "   2. Prüfen Sie die Datenbank-Verbindung\n";
    echo "   3. Prüfen Sie die .env Datei\n";
    exit(1);
}
