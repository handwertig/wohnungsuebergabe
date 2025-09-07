<?php
// Test-Skript für Settings-Speicherung und Logging

require_once __DIR__ . '/vendor/autoload.php';

use App\Database;
use App\Settings;
use App\SystemLogger;

// Farbige Ausgabe für Terminal
function success($msg) { echo "\033[32m✓ $msg\033[0m\n"; }
function error($msg) { echo "\033[31m✗ $msg\033[0m\n"; }
function info($msg) { echo "\033[34m→ $msg\033[0m\n"; }
function warning($msg) { echo "\033[33m⚠ $msg\033[0m\n"; }

try {
    info("Starte Settings-Test...\n");
    
    // Datenbank-Verbindung prüfen
    info("Prüfe Datenbank-Verbindung...");
    $pdo = Database::pdo();
    success("Datenbank-Verbindung erfolgreich");
    
    // Settings-Tabelle prüfen
    info("\nPrüfe Settings-Tabelle...");
    $result = $pdo->query("SHOW TABLES LIKE 'settings'")->fetch();
    if ($result) {
        success("Settings-Tabelle existiert");
        
        // Struktur prüfen
        $columns = $pdo->query("SHOW COLUMNS FROM settings")->fetchAll(PDO::FETCH_COLUMN);
        info("Spalten: " . implode(", ", $columns));
    } else {
        error("Settings-Tabelle existiert nicht!");
        info("Erstelle Settings-Tabelle...");
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS settings (
                `key` VARCHAR(255) PRIMARY KEY,
                `value` TEXT,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");
        success("Settings-Tabelle erstellt");
    }
    
    // System-Log Tabelle prüfen
    info("\nPrüfe System-Log Tabelle...");
    $result = $pdo->query("SHOW TABLES LIKE 'system_log'")->fetch();
    if ($result) {
        success("System-Log Tabelle existiert");
        
        // Struktur prüfen
        $columns = $pdo->query("SHOW COLUMNS FROM system_log")->fetchAll(PDO::FETCH_COLUMN);
        info("Spalten: " . implode(", ", $columns));
    } else {
        error("System-Log Tabelle existiert nicht!");
        info("Erstelle System-Log Tabelle...");
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS system_log (
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
                
                INDEX idx_user_email (user_email),
                INDEX idx_action_type (action_type),
                INDEX idx_resource (resource_type, resource_id),
                INDEX idx_created_at (created_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
        success("System-Log Tabelle erstellt");
    }
    
    // Test 1: Einzelne Einstellung speichern
    info("\n=== TEST 1: Einzelne Einstellung speichern ===");
    $testKey = 'test_setting_' . time();
    $testValue = 'Test-Wert um ' . date('H:i:s');
    
    info("Speichere: $testKey = $testValue");
    Settings::set($testKey, $testValue);
    
    // Direkt aus DB prüfen
    $stmt = $pdo->prepare("SELECT `value` FROM settings WHERE `key` = ?");
    $stmt->execute([$testKey]);
    $dbValue = $stmt->fetchColumn();
    
    if ($dbValue === $testValue) {
        success("Wert korrekt in Datenbank gespeichert");
    } else {
        error("Wert nicht korrekt gespeichert! DB: '$dbValue', Erwartet: '$testValue'");
    }
    
    // Über Settings-Klasse abrufen
    Settings::clearCache();
    $retrievedValue = Settings::get($testKey);
    if ($retrievedValue === $testValue) {
        success("Wert korrekt über Settings::get() abgerufen");
    } else {
        error("Wert nicht korrekt abgerufen! Erhalten: '$retrievedValue', Erwartet: '$testValue'");
    }
    
    // Test 2: Mehrere Einstellungen speichern
    info("\n=== TEST 2: Mehrere Einstellungen speichern (setMany) ===");
    $multiSettings = [
        'smtp_host' => 'smtp.test.com',
        'smtp_port' => '587',
        'smtp_user' => 'testuser@test.com',
        'smtp_from_name' => 'Test System ' . date('H:i:s')
    ];
    
    info("Speichere " . count($multiSettings) . " Einstellungen...");
    Settings::setMany($multiSettings);
    
    // Alle prüfen
    $allCorrect = true;
    foreach ($multiSettings as $key => $expectedValue) {
        $stmt = $pdo->prepare("SELECT `value` FROM settings WHERE `key` = ?");
        $stmt->execute([$key]);
        $dbValue = $stmt->fetchColumn();
        
        if ($dbValue === $expectedValue) {
            success("  $key = $expectedValue ✓");
        } else {
            error("  $key = '$dbValue' (erwartet: '$expectedValue')");
            $allCorrect = false;
        }
    }
    
    if ($allCorrect) {
        success("Alle Einstellungen korrekt gespeichert");
    }
    
    // Test 3: System-Log schreiben
    info("\n=== TEST 3: System-Log Einträge ===");
    
    // Vorher zählen
    $countBefore = $pdo->query("SELECT COUNT(*) FROM system_log")->fetchColumn();
    info("Anzahl Logs vorher: $countBefore");
    
    // Log-Eintrag erstellen
    SystemLogger::log(
        'settings_test',
        'Test-Eintrag von ' . __FILE__,
        'test',
        null,
        ['timestamp' => time(), 'test' => true]
    );
    
    // Nachher zählen
    $countAfter = $pdo->query("SELECT COUNT(*) FROM system_log")->fetchColumn();
    info("Anzahl Logs nachher: $countAfter");
    
    if ($countAfter > $countBefore) {
        success("Log-Eintrag erfolgreich geschrieben");
        
        // Letzten Eintrag prüfen
        $lastLog = $pdo->query("
            SELECT action_type, action_description 
            FROM system_log 
            ORDER BY created_at DESC 
            LIMIT 1
        ")->fetch(PDO::FETCH_ASSOC);
        
        info("Letzter Log: " . $lastLog['action_type'] . " - " . $lastLog['action_description']);
    } else {
        error("Kein Log-Eintrag geschrieben!");
    }
    
    // Test 4: Settings-Änderung mit Logging
    info("\n=== TEST 4: Settings-Änderung mit Logging ===");
    
    $countBefore = $pdo->query("SELECT COUNT(*) FROM system_log")->fetchColumn();
    
    // Einstellung ändern und loggen
    Settings::set('test_with_log', 'Wert mit Log ' . date('H:i:s'));
    SystemLogger::logSettingsChanged('test', ['test_with_log' => 'neuer Wert']);
    
    $countAfter = $pdo->query("SELECT COUNT(*) FROM system_log")->fetchColumn();
    
    if ($countAfter > $countBefore) {
        success("Settings-Änderung wurde geloggt");
    } else {
        warning("Settings-Änderung wurde nicht geloggt");
    }
    
    // Test 5: Fehlerbehandlung
    info("\n=== TEST 5: Fehlerbehandlung ===");
    
    try {
        // Ungültige Tabelle
        $pdo->exec("INSERT INTO nicht_existierende_tabelle VALUES (1)");
        error("Fehler wurde nicht abgefangen");
    } catch (PDOException $e) {
        success("PDO-Fehler korrekt abgefangen");
    }
    
    // Zusammenfassung
    info("\n=== ZUSAMMENFASSUNG ===");
    
    // Aktuelle Settings anzeigen
    $settings = $pdo->query("
        SELECT `key`, `value`, updated_at 
        FROM settings 
        ORDER BY updated_at DESC 
        LIMIT 10
    ")->fetchAll(PDO::FETCH_ASSOC);
    
    info("\nLetzte 10 Settings:");
    foreach ($settings as $setting) {
        $value = substr($setting['value'], 0, 50);
        if (strlen($setting['value']) > 50) $value .= '...';
        info("  " . $setting['key'] . " = " . $value . " (" . $setting['updated_at'] . ")");
    }
    
    // Letzte Logs anzeigen
    $logs = $pdo->query("
        SELECT action_type, action_description, created_at 
        FROM system_log 
        ORDER BY created_at DESC 
        LIMIT 5
    ")->fetchAll(PDO::FETCH_ASSOC);
    
    info("\nLetzte 5 System-Logs:");
    foreach ($logs as $log) {
        info("  [" . $log['created_at'] . "] " . $log['action_type'] . ": " . $log['action_description']);
    }
    
    success("\n✓ Alle Tests abgeschlossen!");
    
} catch (Exception $e) {
    error("\nFEHLER: " . $e->getMessage());
    error("Stack-Trace:");
    echo $e->getTraceAsString() . "\n";
}
