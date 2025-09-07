#!/bin/bash

# Umfassendes Debug- und Fix-Skript für Protokoll-Speicherung

echo "=========================================="
echo "PROTOKOLL-SPEICHERUNG DEBUG & FIX"
echo "=========================================="
echo ""

# Container finden
APP=$(docker ps --format "table {{.Names}}" | grep app | head -1)
DB=$(docker ps --format "table {{.Names}}" | grep db | head -1)

if [ -z "$APP" ] || [ -z "$DB" ]; then
    echo "❌ Docker-Container nicht gefunden!"
    echo "Starten Sie Docker Desktop und führen Sie aus:"
    echo "  cd /Users/berndgundlach/Documents/Docker/wohnungsuebergabe"
    echo "  docker compose up -d"
    exit 1
fi

echo "Verwende Container:"
echo "  App: $APP"
echo "  DB:  $DB"
echo ""

# 1. Datenbank-Struktur prüfen
echo "1. PRÜFE DATENBANK-STRUKTUR"
echo "============================"

docker exec $DB mysql -u root -proot wohnungsuebergabe << 'EOF'
-- Prüfe protocols Tabelle
SHOW CREATE TABLE protocols;

-- Prüfe protocol_events Tabelle
SHOW TABLES LIKE 'protocol_events';

-- Prüfe system_log Tabelle
SHOW TABLES LIKE 'system_log';

-- Zeige letzte Protokolle
SELECT id, tenant_name, type, updated_at 
FROM protocols 
ORDER BY updated_at DESC 
LIMIT 5;
EOF

echo ""

# 2. Erstelle Test-PHP-Skript
echo "2. ERSTELLE TEST-SKRIPT"
echo "======================="

cat > /tmp/test_protocol_save.php << 'PHPEOF'
<?php
require_once '/var/www/html/vendor/autoload.php';

use App\Database;
use App\SystemLogger;

echo "\n=== PROTOKOLL-SPEICHERUNG TEST ===\n\n";

try {
    $pdo = Database::pdo();
    
    // 1. Finde ein Test-Protokoll
    echo "1. Suche Test-Protokoll...\n";
    $stmt = $pdo->query("SELECT id, tenant_name, type, payload FROM protocols WHERE deleted_at IS NULL ORDER BY created_at DESC LIMIT 1");
    $protocol = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$protocol) {
        echo "   ✗ Kein Protokoll gefunden!\n";
        echo "   Erstelle Test-Protokoll...\n";
        
        // Erstelle Test-Protokoll
        $testId = '82cc7de7-7d1e-11f0-89a6-822b82242c5d';
        $stmt = $pdo->prepare("
            INSERT INTO protocols (id, unit_id, type, tenant_name, payload, created_at) 
            VALUES (?, 
                    (SELECT id FROM units LIMIT 1), 
                    'einzug', 
                    'Test Mieter', 
                    '{}', 
                    NOW())
            ON DUPLICATE KEY UPDATE tenant_name = 'Test Mieter'
        ");
        $stmt->execute([$testId]);
        
        $protocol = ['id' => $testId, 'tenant_name' => 'Test Mieter', 'type' => 'einzug'];
    }
    
    echo "   ✓ Protokoll gefunden: " . $protocol['id'] . "\n";
    echo "   Aktueller Name: " . $protocol['tenant_name'] . "\n\n";
    
    // 2. Teste direkte SQL-Änderung
    echo "2. Teste direkte SQL-Änderung...\n";
    $newName = 'Test Mieter ' . date('H:i:s');
    
    $stmt = $pdo->prepare("UPDATE protocols SET tenant_name = ?, updated_at = NOW() WHERE id = ?");
    $result = $stmt->execute([$newName, $protocol['id']]);
    
    if ($result) {
        echo "   ✓ UPDATE erfolgreich\n";
        
        // Verifizieren
        $stmt = $pdo->prepare("SELECT tenant_name, updated_at FROM protocols WHERE id = ?");
        $stmt->execute([$protocol['id']]);
        $updated = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($updated['tenant_name'] === $newName) {
            echo "   ✓ Name korrekt gespeichert: " . $updated['tenant_name'] . "\n";
            echo "   ✓ Updated_at: " . $updated['updated_at'] . "\n";
        } else {
            echo "   ✗ Name NICHT korrekt: " . $updated['tenant_name'] . "\n";
        }
    } else {
        echo "   ✗ UPDATE fehlgeschlagen!\n";
    }
    echo "\n";
    
    // 3. Teste protocol_events
    echo "3. Teste protocol_events...\n";
    try {
        // Prüfe ob Tabelle existiert
        $stmt = $pdo->query("SHOW TABLES LIKE 'protocol_events'");
        if ($stmt->rowCount() > 0) {
            // Event einfügen
            $stmt = $pdo->prepare("
                INSERT INTO protocol_events (id, protocol_id, type, message, created_at) 
                VALUES (UUID(), ?, 'test', 'Test-Event von Debug-Skript', NOW())
            ");
            $stmt->execute([$protocol['id']]);
            echo "   ✓ Event hinzugefügt\n";
            
            // Events zählen
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM protocol_events WHERE protocol_id = ?");
            $stmt->execute([$protocol['id']]);
            $count = $stmt->fetchColumn();
            echo "   ✓ Anzahl Events für dieses Protokoll: " . $count . "\n";
        } else {
            echo "   ⚠ protocol_events Tabelle existiert nicht\n";
            echo "   → Erstelle Tabelle...\n";
            
            $pdo->exec("
                CREATE TABLE IF NOT EXISTS protocol_events (
                    id CHAR(36) PRIMARY KEY,
                    protocol_id CHAR(36) NOT NULL,
                    type VARCHAR(50) NOT NULL,
                    message TEXT,
                    created_by VARCHAR(255),
                    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                    INDEX idx_protocol (protocol_id),
                    INDEX idx_created (created_at)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
            ");
            echo "   ✓ protocol_events Tabelle erstellt\n";
        }
    } catch (PDOException $e) {
        echo "   ✗ Fehler bei protocol_events: " . $e->getMessage() . "\n";
    }
    echo "\n";
    
    // 4. Teste SystemLogger
    echo "4. Teste SystemLogger...\n";
    try {
        SystemLogger::logProtocolUpdated($protocol['id'], [
            'type' => 'einzug',
            'tenant_name' => $newName,
            'city' => 'Test Stadt',
            'street' => 'Test Straße',
            'unit' => 'Test Einheit'
        ], ['tenant_name']);
        
        echo "   ✓ SystemLogger aufgerufen\n";
        
        // Prüfe system_log
        $stmt = $pdo->query("SELECT COUNT(*) FROM system_log WHERE action_type = 'protocol_updated'");
        $count = $stmt->fetchColumn();
        echo "   ✓ Anzahl protocol_updated Logs: " . $count . "\n";
    } catch (Throwable $e) {
        echo "   ✗ SystemLogger Fehler: " . $e->getMessage() . "\n";
    }
    echo "\n";
    
    // 5. Zusammenfassung
    echo "5. ZUSAMMENFASSUNG\n";
    echo "==================\n";
    
    // Aktuelle Protokoll-Daten
    $stmt = $pdo->prepare("SELECT * FROM protocols WHERE id = ?");
    $stmt->execute([$protocol['id']]);
    $final = $stmt->fetch(PDO::FETCH_ASSOC);
    
    echo "Protokoll-ID: " . $final['id'] . "\n";
    echo "Mieter-Name: " . $final['tenant_name'] . "\n";
    echo "Typ: " . $final['type'] . "\n";
    echo "Updated_at: " . $final['updated_at'] . "\n";
    echo "Owner_id: " . ($final['owner_id'] ?? 'NULL') . "\n";
    echo "Manager_id: " . ($final['manager_id'] ?? 'NULL') . "\n";
    
    echo "\n✅ Test abgeschlossen!\n\n";
    
} catch (Exception $e) {
    echo "\n❌ FEHLER: " . $e->getMessage() . "\n";
    echo "Stack-Trace:\n";
    echo $e->getTraceAsString() . "\n";
}
PHPEOF

# Kopiere Test-Skript in Container
docker cp /tmp/test_protocol_save.php $APP:/tmp/test_protocol_save.php

# Führe Test aus
echo ""
docker exec $APP php /tmp/test_protocol_save.php

echo ""
echo "3. PRÜFE ROUTING"
echo "================"

# Prüfe ob die Route existiert
docker exec $APP php -r "
require_once '/var/www/html/vendor/autoload.php';
\$routes = require '/var/www/html/public/index.php';
echo 'POST /protocols/save Route vorhanden: ';
echo file_exists('/var/www/html/backend/src/Controllers/ProtocolsController.php') ? '✓' : '✗';
echo PHP_EOL;
"

echo ""
echo "4. ERSTELLE FIX-SKRIPT"
echo "====================="

cat > /tmp/fix_protocol_save.sql << 'SQLEOF'
-- Stelle sicher dass alle Tabellen korrekt sind

-- 1. Prüfe protocols Tabelle
ALTER TABLE protocols 
  MODIFY COLUMN payload LONGTEXT,
  MODIFY COLUMN updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP;

-- 2. Erstelle protocol_events falls nicht vorhanden
CREATE TABLE IF NOT EXISTS protocol_events (
    id CHAR(36) PRIMARY KEY DEFAULT (UUID()),
    protocol_id CHAR(36) NOT NULL,
    type VARCHAR(50) NOT NULL,
    message TEXT,
    created_by VARCHAR(255),
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_protocol (protocol_id),
    INDEX idx_created (created_at),
    INDEX idx_type (type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3. Füge Test-Event ein
INSERT INTO protocol_events (protocol_id, type, message, created_by) 
VALUES ('82cc7de7-7d1e-11f0-89a6-822b82242c5d', 'debug', 'Fix-Skript ausgeführt', 'system');

-- 4. Zeige Ergebnis
SELECT 'Protokolle mit Updates heute:' as Info, COUNT(*) as Anzahl 
FROM protocols 
WHERE DATE(updated_at) = CURDATE();

SELECT 'Events heute:' as Info, COUNT(*) as Anzahl 
FROM protocol_events 
WHERE DATE(created_at) = CURDATE();

SELECT 'System-Logs heute:' as Info, COUNT(*) as Anzahl 
FROM system_log 
WHERE DATE(created_at) = CURDATE();
SQLEOF

# Führe SQL-Fix aus
echo ""
docker exec $DB mysql -u root -proot wohnungsuebergabe < /tmp/fix_protocol_save.sql

echo ""
echo "=========================================="
echo "ANALYSE ABGESCHLOSSEN"
echo "=========================================="
echo ""
echo "NÄCHSTE SCHRITTE:"
echo "1. Öffnen Sie: http://localhost:8080/protocols/edit?id=82cc7de7-7d1e-11f0-89a6-822b82242c5d"
echo "2. Ändern Sie den Namen"
echo "3. Klicken Sie auf 'Speichern'"
echo "4. Prüfen Sie ob die Änderung gespeichert wurde"
echo ""
echo "Falls immer noch Probleme:"
echo "  Führen Sie aus: ./debug_protocol_save.sh"
echo ""
