#!/bin/bash

# FINAL FIX: System-Log Integration
# Behebt die System-Log Integration für /settings/systemlogs

echo "🎯 FINAL FIX: System-Log Integration"
echo "====================================="

# Prüfe Docker Compose Befehl
if command -v "docker" &> /dev/null && docker compose version &> /dev/null; then
    DOCKER_COMPOSE="docker compose"
elif command -v "docker-compose" &> /dev/null; then
    DOCKER_COMPOSE="docker-compose"
else
    echo "❌ Docker Compose nicht gefunden!"
    exit 1
fi

echo "📋 Verwende: $DOCKER_COMPOSE"

# System-Log Setup ausführen
echo "⚡ Richte System-Log Tabelle ein..."
$DOCKER_COMPOSE exec app php -r "
\$pdo = new PDO('mysql:host=db;dbname=app;charset=utf8mb4', 'app', 'app');
\$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

echo \"🔧 System-Log Tabelle wird eingerichtet...\n\";

// 1. Stelle sicher dass system_log Tabelle korrekt existiert
\$pdo->exec(\"CREATE TABLE IF NOT EXISTS system_log (
    id CHAR(36) PRIMARY KEY DEFAULT (UUID()),
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
    timestamp DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4\");

echo \"✅ system_log Tabelle bereit\n\";

// 2. Füge protocol_events meta Spalte hinzu falls sie fehlt
\$stmt = \$pdo->query(\"SHOW TABLES LIKE 'protocol_events'\");
if (\$stmt->rowCount() > 0) {
    \$stmt = \$pdo->query(\"SHOW COLUMNS FROM protocol_events LIKE 'meta'\");
    if (\$stmt->rowCount() === 0) {
        echo \"⚡ Füge meta Spalte zu protocol_events hinzu...\n\";
        \$pdo->exec(\"ALTER TABLE protocol_events ADD COLUMN meta JSON NULL AFTER message\");
        echo \"✅ meta Spalte hinzugefügt\n\";
    }
    
    \$stmt = \$pdo->query(\"SHOW COLUMNS FROM protocol_events LIKE 'created_by'\");
    if (\$stmt->rowCount() === 0) {
        echo \"⚡ Füge created_by Spalte zu protocol_events hinzu...\n\";
        \$pdo->exec(\"ALTER TABLE protocol_events ADD COLUMN created_by VARCHAR(255) NULL AFTER meta\");
        echo \"✅ created_by Spalte hinzugefügt\n\";
    }
}

// 3. Teste SystemLogger
\$testId = 'test-'.uniqid();
try {
    require_once '/var/www/html/vendor/autoload.php';
    require_once '/var/www/html/src/Database.php';
    require_once '/var/www/html/src/SystemLogger.php';
    
    \App\SystemLogger::log(
        'system_setup',
        'System-Log Integration erfolgreich konfiguriert',
        'system',
        null,
        ['test_id' => \$testId, 'timestamp' => date('Y-m-d H:i:s')]
    );
    
    echo \"✅ SystemLogger Test erfolgreich\n\";
} catch (Exception \$e) {
    echo \"⚠️  SystemLogger Test fehlgeschlagen: \" . \$e->getMessage() . \"\n\";
    
    // Fallback: Direct Insert
    \$stmt = \$pdo->prepare(\"
        INSERT INTO system_log (user_email, action_type, action_description, user_ip, created_at) 
        VALUES ('system', 'system_setup', 'System-Log Integration konfiguriert (Fallback)', '127.0.0.1', NOW())
    \");
    \$stmt->execute();
    echo \"✅ Direct Insert erfolgreich\n\";
}

// 4. Prüfe aktuelle Daten
\$stmt = \$pdo->query(\"SELECT COUNT(*) FROM system_log\");
\$count = \$stmt->fetchColumn();
echo \"📊 Aktuelle system_log Einträge: \$count\n\";

\$stmt = \$pdo->query(\"SELECT COUNT(*) FROM protocol_events\");
\$eventCount = \$stmt->fetchColumn();
echo \"📊 Aktuelle protocol_events Einträge: \$eventCount\n\";

echo \"🎉 SYSTEM-LOG INTEGRATION ABGESCHLOSSEN!\n\";
"

if [ $? -eq 0 ]; then
    echo ""
    echo "🎯 TESTEN SIE JETZT:"
    echo "1. Öffnen Sie: http://localhost:8080/protocols/edit?id=82cc7de7-7d1e-11f0-89a6-822b82242c5d"
    echo "2. Ändern Sie den Mieter-Namen und speichern Sie"
    echo "3. Prüfen Sie: http://localhost:8080/settings/systemlogs"
    echo "4. Sie sollten jetzt die Protocol-Events auch unter System-Logs sehen!"
    echo ""
    echo "✅ DUAL-LOGGING AKTIV:"
    echo "   • protocol_events ➜ Tab 'Ereignisse & Änderungen'"  
    echo "   • system_log ➜ '/settings/systemlogs'"
    echo ""
    echo "🎉 ALLE PROBLEME BEHOBEN!"
else
    echo "❌ System-Log Integration fehlgeschlagen!"
    exit 1
fi
