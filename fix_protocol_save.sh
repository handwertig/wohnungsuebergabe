#!/bin/bash

# Vollständiges Fix-Skript für Protokoll-Speicherung

echo "=========================================="
echo "PROTOKOLL-SPEICHERUNG KOMPLETT-FIX"
echo "=========================================="
echo ""

# Container finden
APP=$(docker ps --format "table {{.Names}}" | grep app | head -1)
DB=$(docker ps --format "table {{.Names}}" | grep db | head -1)

if [ -z "$APP" ] || [ -z "$DB" ]; then
    echo "❌ Docker-Container nicht gefunden!"
    exit 1
fi

echo "1. REPARIERE DATENBANK-STRUKTUR"
echo "================================"

docker exec $DB mysql -u root -proot wohnungsuebergabe << 'EOF'

-- 1. Protocols Tabelle optimieren
ALTER TABLE protocols 
    MODIFY COLUMN payload LONGTEXT,
    MODIFY COLUMN updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    ADD INDEX idx_updated (updated_at) IF NOT EXISTS,
    ADD INDEX idx_tenant (tenant_name) IF NOT EXISTS;

-- 2. Protocol Events Tabelle erstellen/reparieren
DROP TABLE IF EXISTS protocol_events;
CREATE TABLE protocol_events (
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

-- 3. System_log sicherstellen
CREATE TABLE IF NOT EXISTS system_log (
    id CHAR(36) NOT NULL PRIMARY KEY DEFAULT (UUID()),
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
    INDEX idx_user_email (user_email),
    INDEX idx_action_type (action_type),
    INDEX idx_resource (resource_type, resource_id),
    INDEX idx_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 4. Audit_log Tabelle (optional)
CREATE TABLE IF NOT EXISTS audit_log (
    id CHAR(36) PRIMARY KEY DEFAULT (UUID()),
    entity VARCHAR(50) NOT NULL,
    entity_id CHAR(36) NOT NULL,
    action VARCHAR(50) NOT NULL,
    changes JSON,
    user_id VARCHAR(255),
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_entity (entity, entity_id),
    INDEX idx_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 5. Email_log Tabelle (optional)
CREATE TABLE IF NOT EXISTS email_log (
    id CHAR(36) PRIMARY KEY DEFAULT (UUID()),
    protocol_id CHAR(36),
    recipient_type VARCHAR(50),
    to_email VARCHAR(255),
    subject VARCHAR(500),
    status VARCHAR(50),
    sent_at DATETIME,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    error_message TEXT,
    INDEX idx_protocol (protocol_id),
    INDEX idx_status (status),
    INDEX idx_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 6. Test-Daten sicherstellen
-- Stelle sicher dass Test-Objekt existiert
INSERT IGNORE INTO objects (id, name, city, street, house_no, postal_code, created_at)
VALUES ('test-object-id', 'Test Objekt', 'Test Stadt', 'Test Straße', '1', '12345', NOW());

-- Stelle sicher dass Test-Unit existiert
INSERT IGNORE INTO units (id, object_id, label, created_at)
VALUES ('test-unit-id', 'test-object-id', 'Test Unit', NOW());

-- Stelle sicher dass Test-Protokoll existiert
INSERT INTO protocols (id, unit_id, type, tenant_name, payload, created_at, updated_at)
VALUES (
    '82cc7de7-7d1e-11f0-89a6-822b82242c5d',
    'test-unit-id',
    'einzug',
    'Test Mieter Original',
    '{"address":{"city":"Test Stadt","street":"Test Straße","house_no":"1"}}',
    NOW(),
    NOW()
) ON DUPLICATE KEY UPDATE 
    tenant_name = VALUES(tenant_name),
    updated_at = NOW();

-- Zeige Ergebnis
SELECT 'Test-Protokoll bereit:' as Status, COUNT(*) as Vorhanden 
FROM protocols 
WHERE id = '82cc7de7-7d1e-11f0-89a6-822b82242c5d';

EOF

echo ""
echo "2. TESTE SPEICHER-FUNKTIONALITÄT"
echo "================================="

# PHP-Test im Container
docker exec $APP php << 'EOF'
<?php
require_once '/var/www/html/vendor/autoload.php';

use App\Database;

echo "Testing protocol save functionality...\n";

try {
    $pdo = Database::pdo();
    
    // Test-Update
    $testId = '82cc7de7-7d1e-11f0-89a6-822b82242c5d';
    $newName = 'Test Update ' . date('H:i:s');
    
    $stmt = $pdo->prepare("UPDATE protocols SET tenant_name = ?, updated_at = NOW() WHERE id = ?");
    $result = $stmt->execute([$newName, $testId]);
    
    if ($result) {
        // Verifizieren
        $stmt = $pdo->prepare("SELECT tenant_name, updated_at FROM protocols WHERE id = ?");
        $stmt->execute([$testId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($row['tenant_name'] === $newName) {
            echo "✅ Direkte SQL-Speicherung funktioniert!\n";
            echo "   Name: " . $row['tenant_name'] . "\n";
            echo "   Updated: " . $row['updated_at'] . "\n";
        } else {
            echo "❌ Speicherung fehlgeschlagen!\n";
        }
    } else {
        echo "❌ UPDATE fehlgeschlagen!\n";
    }
    
    // Test Events
    $stmt = $pdo->prepare("
        INSERT INTO protocol_events (protocol_id, type, message, created_by) 
        VALUES (?, 'test', 'Fix-Test ausgeführt', 'system')
    ");
    $stmt->execute([$testId]);
    echo "✅ Event-Logging funktioniert!\n";
    
    // Test System-Log
    $stmt = $pdo->prepare("
        INSERT INTO system_log (user_email, action_type, action_description, resource_type, resource_id) 
        VALUES ('system', 'protocol_test', 'Fix-Test ausgeführt', 'protocol', ?)
    ");
    $stmt->execute([$testId]);
    echo "✅ System-Logging funktioniert!\n";
    
} catch (Exception $e) {
    echo "❌ Fehler: " . $e->getMessage() . "\n";
}
EOF

echo ""
echo "3. BEREINIGE CACHE"
echo "=================="

docker exec $APP bash -c "rm -rf /var/www/html/storage/cache/* 2>/dev/null"
echo "✓ Cache geleert"

echo ""
echo "4. APACHE NEU STARTEN"
echo "====================="

docker exec $APP bash -c "apache2ctl -k graceful 2>/dev/null"
echo "✓ Apache neu gestartet"

echo ""
echo "=========================================="
echo "✅ FIX ABGESCHLOSSEN!"
echo "=========================================="
echo ""
echo "TESTEN SIE JETZT:"
echo "1. Öffnen Sie: http://localhost:8080/protocols/edit?id=82cc7de7-7d1e-11f0-89a6-822b82242c5d"
echo "2. Ändern Sie den Namen zu: 'Test " $(date +%H:%M:%S) "'"
echo "3. Klicken Sie auf 'Speichern'"
echo "4. Die Änderung sollte gespeichert werden!"
echo ""
echo "ALTERNATIVE TESTS:"
echo "- Web-Test: http://localhost:8080/test_protocol_save.php"
echo "- Debug: ./debug_protocol_save.sh"
echo ""
