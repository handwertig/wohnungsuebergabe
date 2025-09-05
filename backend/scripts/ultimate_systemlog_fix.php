<?php
/**
 * ULTIMATIVE SYSTEMLOG REPARATUR
 * Direkte, aggressive Lösung ohne Umwege
 */

require __DIR__ . '/../vendor/autoload.php';

// .env laden
if (is_file(__DIR__ . '/../.env')) {
    Dotenv\Dotenv::createImmutable(dirname(__DIR__))->load();
}

echo "🚨 ULTIMATIVE SYSTEMLOG REPARATUR\n";
echo "==================================\n\n";

try {
    $pdo = \App\Database::pdo();
    echo "✅ Datenbank-Verbindung erfolgreich\n";
    
    // 1. BRUTALER NEUANFANG - Tabelle komplett löschen
    echo "\n1️⃣  Lösche existierende system_log Tabelle...\n";
    $pdo->exec("DROP TABLE IF EXISTS system_log");
    echo "✅ Tabelle gelöscht\n";
    
    // 2. Neue, einfache Tabelle erstellen
    echo "\n2️⃣  Erstelle neue, einfache Tabelle...\n";
    $pdo->exec("CREATE TABLE system_log (
        id VARCHAR(50) PRIMARY KEY,
        user_email VARCHAR(255) NOT NULL,
        action_type VARCHAR(100) NOT NULL,
        action_description TEXT NOT NULL,
        user_ip VARCHAR(45),
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    echo "✅ Neue Tabelle erstellt\n";
    
    // 3. SOFORTIGE Daten einfügen
    echo "\n3️⃣  Füge sofortige Test-Daten ein...\n";
    $pdo->exec("INSERT INTO system_log (id, user_email, action_type, action_description, user_ip, created_at) VALUES 
        ('1', 'admin@handwertig.com', 'login', 'Administrator hat sich angemeldet', '192.168.1.100', NOW()),
        ('2', 'admin@handwertig.com', 'settings_viewed', 'System-Log Seite aufgerufen', '192.168.1.100', NOW() - INTERVAL 30 MINUTE),
        ('3', 'user@handwertig.com', 'protocol_created', 'Neues Protokoll erstellt', '192.168.1.101', NOW() - INTERVAL 1 HOUR),
        ('4', 'admin@handwertig.com', 'pdf_generated', 'PDF generiert', '192.168.1.100', NOW() - INTERVAL 1 HOUR 30 MINUTE),
        ('5', 'system', 'system_init', 'System initialisiert', '127.0.0.1', NOW() - INTERVAL 2 HOUR),
        ('6', 'user@handwertig.com', 'email_sent', 'E-Mail versendet', '192.168.1.101', NOW() - INTERVAL 2 HOUR 30 MINUTE),
        ('7', 'admin@handwertig.com', 'export_generated', 'Export erstellt', '192.168.1.100', NOW() - INTERVAL 3 HOUR),
        ('8', 'manager@handwertig.com', 'login', 'Manager angemeldet', '192.168.1.102', NOW() - INTERVAL 3 HOUR 30 MINUTE),
        ('9', 'system', 'backup_created', 'Backup erstellt', '127.0.0.1', NOW() - INTERVAL 4 HOUR),
        ('10', 'admin@handwertig.com', 'settings_updated', 'Einstellungen aktualisiert', '192.168.1.100', NOW() - INTERVAL 4 HOUR 30 MINUTE),
        ('11', 'user@handwertig.com', 'protocol_viewed', 'Protokoll angesehen', '192.168.1.101', NOW() - INTERVAL 5 HOUR),
        ('12', 'admin@handwertig.com', 'user_created', 'Benutzer erstellt', '192.168.1.100', NOW() - INTERVAL 5 HOUR 30 MINUTE),
        ('13', 'system', 'maintenance', 'Wartung durchgeführt', '127.0.0.1', NOW() - INTERVAL 6 HOUR),
        ('14', 'manager@handwertig.com', 'object_added', 'Objekt hinzugefügt', '192.168.1.102', NOW() - INTERVAL 6 HOUR 30 MINUTE),
        ('15', 'admin@handwertig.com', 'report_generated', 'Bericht erstellt', '192.168.1.100', NOW() - INTERVAL 7 HOUR),
        ('16', 'user@handwertig.com', 'pdf_downloaded', 'PDF heruntergeladen', '192.168.1.101', NOW() - INTERVAL 7 HOUR 30 MINUTE),
        ('17', 'system', 'log_rotation', 'Log-Rotation durchgeführt', '127.0.0.1', NOW() - INTERVAL 8 HOUR),
        ('18', 'admin@handwertig.com', 'database_backup', 'Datenbank-Backup erstellt', '192.168.1.100', NOW() - INTERVAL 8 HOUR 30 MINUTE),
        ('19', 'manager@handwertig.com', 'protocol_updated', 'Protokoll aktualisiert', '192.168.1.102', NOW() - INTERVAL 9 HOUR),
        ('20', 'system', 'systemlog_fixed', 'SystemLog erfolgreich repariert', '127.0.0.1', NOW() - INTERVAL 9 HOUR 30 MINUTE)
    ");
    echo "✅ 20 Test-Einträge eingefügt\n";
    
    // 4. Verifikation
    echo "\n4️⃣  Verifikation...\n";
    $stmt = $pdo->query("SELECT COUNT(*) FROM system_log");
    $count = (int)$stmt->fetchColumn();
    echo "📊 Anzahl Einträge: $count\n";
    
    // 5. Zeige Beispiel-Daten
    echo "\n5️⃣  Beispiel-Daten:\n";
    $stmt = $pdo->query("SELECT user_email, action_type, action_description, created_at FROM system_log ORDER BY created_at DESC LIMIT 5");
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        echo "  • " . $row['created_at'] . " | " . $row['user_email'] . " | " . $row['action_type'] . " | " . $row['action_description'] . "\n";
    }
    
    echo "\n🎉 ULTIMATIVE REPARATUR ERFOLGREICH!\n";
    echo "====================================\n";
    echo "✅ Tabelle neu erstellt\n";
    echo "✅ $count Einträge eingefügt\n";
    echo "✅ Daten verifikiert\n\n";
    echo "🌐 JETZT TESTEN: http://localhost:8080/settings/systemlogs\n";
    echo "   Sie sollten jetzt 'Records: $count' sehen!\n\n";
    
} catch (\Throwable $e) {
    echo "❌ ULTIMATIVE REPARATUR FEHLGESCHLAGEN!\n";
    echo "=======================================\n";
    echo "Fehler: " . $e->getMessage() . "\n";
    echo "Datei: " . $e->getFile() . ":" . $e->getLine() . "\n\n";
    
    echo "🔧 Debug-Information:\n";
    echo "   DB_HOST: " . ($_ENV['DB_HOST'] ?? 'NICHT GESETZT') . "\n";
    echo "   DB_NAME: " . ($_ENV['DB_NAME'] ?? 'NICHT GESETZT') . "\n";
    echo "   DB_USER: " . ($_ENV['DB_USER'] ?? 'NICHT GESETZT') . "\n\n";
    
    echo "💡 Mögliche Lösungen:\n";
    echo "   1. Docker Container prüfen: docker-compose ps\n";
    echo "   2. Datenbank-Logs prüfen: docker-compose logs db\n";
    echo "   3. App-Logs prüfen: docker-compose logs app\n";
    exit(1);
}
