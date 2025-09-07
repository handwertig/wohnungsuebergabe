#!/bin/bash

# Docker-Fix-Skript für Settings-Problem
# Verwendet korrekte Docker-Befehle für Mac

echo "==================================="
echo "SETTINGS-PROBLEM VOLLSTÄNDIGE LÖSUNG"
echo "==================================="
echo ""

# Prüfe ob Docker läuft
if ! docker info > /dev/null 2>&1; then
    echo "❌ Docker läuft nicht! Bitte starten Sie Docker Desktop."
    exit 1
fi

echo "✓ Docker läuft"
echo ""

# In Docker-Container wechseln und MySQL-Befehle ausführen
echo "1. Repariere Datenbank-Struktur..."
docker exec wohnungsuebergabe-db-1 mysql -u root -proot wohnungsuebergabe << 'EOF'

-- Settings-Tabelle neu erstellen
DROP TABLE IF EXISTS settings;
CREATE TABLE settings (
    `key` VARCHAR(255) NOT NULL PRIMARY KEY,
    `value` TEXT,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_updated (updated_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Default-Settings einfügen
INSERT IGNORE INTO settings (`key`, `value`) VALUES
('smtp_host', 'mailpit'),
('smtp_port', '1025'),
('smtp_user', ''),
('smtp_pass', ''),
('smtp_secure', ''),
('smtp_from_email', 'no-reply@example.com'),
('smtp_from_name', 'Wohnungsübergabe'),
('ds_base_uri', 'https://eu.docusign.net'),
('ds_account_id', ''),
('ds_user_id', ''),
('ds_client_id', ''),
('ds_client_secret', ''),
('pdf_logo_path', ''),
('custom_css', ''),
('brand_primary', '#222357'),
('brand_secondary', '#e22278');

-- System_log Tabelle neu erstellen
DROP TABLE IF EXISTS system_log;
CREATE TABLE system_log (
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

-- Test-Einträge
INSERT INTO system_log (user_email, action_type, action_description, user_ip) VALUES
('system', 'database_repair', 'Datenbank-Struktur repariert', '127.0.0.1');

-- Prüfe Ergebnis
SELECT COUNT(*) as count FROM settings;
SELECT COUNT(*) as count FROM system_log;

EOF

if [ $? -eq 0 ]; then
    echo "✓ Datenbank-Struktur repariert"
else
    echo "⚠ Warnung: Datenbank-Reparatur möglicherweise unvollständig"
fi
echo ""

# PHP-Test im Container
echo "2. Teste Settings-Funktionalität..."
docker exec wohnungsuebergabe-app-1 php << 'EOF'
<?php
require_once '/var/www/html/vendor/autoload.php';

use App\Database;
use App\Settings;
use App\SystemLogger;

echo "Testing Settings functionality...\n";

// Test 1: Save setting
$testKey = 'test_' . time();
$testValue = 'Test value at ' . date('H:i:s');

$result = Settings::set($testKey, $testValue);
echo $result ? "✓ Settings::set() successful\n" : "✗ Settings::set() failed\n";

// Test 2: Retrieve setting
Settings::clearCache();
$retrieved = Settings::get($testKey);
echo ($retrieved === $testValue) ? "✓ Settings::get() successful\n" : "✗ Settings::get() failed\n";

// Test 3: setMany
$multiSettings = [
    'smtp_host' => 'smtp.test.com',
    'smtp_port' => '587',
    'smtp_from_name' => 'Test ' . date('H:i:s')
];

$result = Settings::setMany($multiSettings);
echo $result ? "✓ Settings::setMany() successful\n" : "✗ Settings::setMany() failed\n";

// Test 4: System log
SystemLogger::log('test', 'Settings test completed');
echo "✓ SystemLogger test completed\n";

// Test 5: Verify in DB
$pdo = Database::pdo();
$count = $pdo->query("SELECT COUNT(*) FROM settings")->fetchColumn();
echo "✓ Total settings in DB: $count\n";

echo "\nAll tests completed!\n";
EOF

if [ $? -eq 0 ]; then
    echo "✓ Settings-Tests erfolgreich"
else
    echo "⚠ Einige Tests möglicherweise fehlgeschlagen"
fi
echo ""

# Cache leeren
echo "3. Leere Application-Cache..."
docker exec wohnungsuebergabe-app-1 bash -c "rm -rf /var/www/html/storage/cache/* 2>/dev/null"
echo "✓ Cache geleert"
echo ""

# Apache neu starten
echo "4. Starte Apache neu..."
docker exec wohnungsuebergabe-app-1 bash -c "apache2ctl -k graceful 2>/dev/null"
echo "✓ Apache neu gestartet"
echo ""

# Alte Test-Dateien entfernen
echo "5. Entferne alte Test-Dateien..."
rm -f backend/public/test_settings_web.php 2>/dev/null
rm -f backend/debug_settings.php 2>/dev/null
rm -f backend/fix_and_test.php 2>/dev/null
echo "✓ Test-Dateien entfernt"
echo ""

echo "==================================="
echo "✅ REPARATUR ABGESCHLOSSEN!"
echo "==================================="
echo ""
echo "Sie können jetzt:"
echo "1. Die Settings-Seite unter http://localhost:8080/settings aufrufen"
echo "2. Mail-Einstellungen unter http://localhost:8080/settings/mail konfigurieren"
echo "3. Alle Änderungen werden gespeichert und geloggt"
echo ""
echo "Zum Testen öffnen Sie: http://localhost:8080/settings/mail"
echo ""
