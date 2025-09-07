<?php
// Fix-Skript für Settings und System-Log

require_once __DIR__ . '/vendor/autoload.php';

use App\Database;

// Farbige Ausgabe
function success($msg) { echo "\033[32m✓ $msg\033[0m\n"; }
function error($msg) { echo "\033[31m✗ $msg\033[0m\n"; }
function info($msg) { echo "\033[34m→ $msg\033[0m\n"; }
function warning($msg) { echo "\033[33m⚠ $msg\033[0m\n"; }

try {
    info("=== DATENBANK-REPARATUR GESTARTET ===\n");
    
    $pdo = Database::pdo();
    
    // 1. Settings-Tabelle reparieren
    info("Repariere Settings-Tabelle...");
    
    // Backup erstellen
    try {
        $pdo->exec("CREATE TEMPORARY TABLE settings_backup AS SELECT * FROM settings");
        info("Backup der Settings erstellt");
    } catch (PDOException $e) {
        info("Kein Backup nötig (Tabelle existiert nicht oder ist leer)");
    }
    
    // Tabelle neu erstellen
    $pdo->exec("DROP TABLE IF EXISTS settings");
    $pdo->exec("
        CREATE TABLE settings (
            `key` VARCHAR(255) PRIMARY KEY,
            `value` TEXT,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_updated (updated_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    success("Settings-Tabelle neu erstellt");
    
    // Daten wiederherstellen
    try {
        $pdo->exec("INSERT IGNORE INTO settings SELECT * FROM settings_backup");
        $count = $pdo->query("SELECT COUNT(*) FROM settings")->fetchColumn();
        success("$count Settings wiederhergestellt");
    } catch (PDOException $e) {
        info("Keine Daten zum Wiederherstellen");
    }
    
    // Standard-Settings einfügen
    $defaults = [
        'smtp_host' => 'mailpit',
        'smtp_port' => '1025',
        'smtp_user' => '',
        'smtp_pass' => '',
        'smtp_secure' => '',
        'smtp_from_email' => 'no-reply@example.com',
        'smtp_from_name' => 'Wohnungsübergabe',
        'ds_base_uri' => 'https://eu.docusign.net',
        'ds_account_id' => '',
        'ds_user_id' => '',
        'ds_client_id' => '',
        'ds_client_secret' => '',
        'pdf_logo_path' => '',
        'custom_css' => '',
        'brand_primary' => '#222357',
        'brand_secondary' => '#e22278'
    ];
    
    $stmt = $pdo->prepare("INSERT IGNORE INTO settings (`key`, `value`) VALUES (?, ?)");
    foreach ($defaults as $key => $value) {
        $stmt->execute([$key, $value]);
    }
    success("Standard-Settings eingefügt");
    
    // 2. System-Log Tabelle reparieren
    info("\nRepariere System-Log Tabelle...");
    
    // Backup erstellen
    try {
        $pdo->exec("CREATE TEMPORARY TABLE system_log_backup AS SELECT * FROM system_log LIMIT 1000");
        info("Backup der System-Logs erstellt");
    } catch (PDOException $e) {
        info("Kein Backup nötig");
    }
    
    // Tabelle neu erstellen
    $pdo->exec("DROP TABLE IF EXISTS system_log");
    $pdo->exec("
        CREATE TABLE system_log (
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
    success("System-Log Tabelle neu erstellt");
    
    // Daten wiederherstellen (mit Fehlerbehandlung für alte Struktur)
    try {
        $pdo->exec("
            INSERT IGNORE INTO system_log (
                user_email, user_ip, action_type, action_description, 
                resource_type, resource_id, created_at
            )
            SELECT 
                COALESCE(user_email, 'system'),
                user_ip,
                COALESCE(action_type, 'unknown'),
                COALESCE(action_description, 'Imported from backup'),
                resource_type,
                resource_id,
                COALESCE(created_at, NOW())
            FROM system_log_backup
        ");
        $count = $pdo->query("SELECT COUNT(*) FROM system_log")->fetchColumn();
        success("$count Log-Einträge wiederhergestellt");
    } catch (PDOException $e) {
        info("Keine alten Logs wiederhergestellt: " . $e->getMessage());
    }
    
    // Test-Eintrag hinzufügen
    $pdo->exec("
        INSERT INTO system_log (
            user_email, action_type, action_description, user_ip, created_at
        ) VALUES (
            'system', 'database_repair', 'Datenbank-Reparatur durchgeführt', '127.0.0.1', NOW()
        )
    ");
    success("Test-Log-Eintrag hinzugefügt");
    
    // 3. Legal_texts Tabelle prüfen
    info("\nPrüfe legal_texts Tabelle...");
    $result = $pdo->query("SHOW TABLES LIKE 'legal_texts'")->fetch();
    if (!$result) {
        $pdo->exec("
            CREATE TABLE legal_texts (
                id CHAR(36) PRIMARY KEY,
                name VARCHAR(100) NOT NULL,
                version INT NOT NULL DEFAULT 1,
                title VARCHAR(255),
                content TEXT,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_name_version (name, version)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
        success("Legal_texts Tabelle erstellt");
        
        // Standard-Textbausteine
        $texts = [
            ['datenschutz', 'Datenschutzerklärung', 'Ihre Daten werden gemäß DSGVO verarbeitet.'],
            ['entsorgung', 'Entsorgungshinweis', 'Bitte entsorgen Sie Abfälle ordnungsgemäß.'],
            ['marketing', 'Marketing-Einwilligung', 'Ich stimme der Zusendung von Informationen zu.'],
            ['kaution_hinweis', 'Kautionsrückzahlung', 'Die Kaution wird nach Prüfung zurückerstattet.']
        ];
        
        $stmt = $pdo->prepare("
            INSERT INTO legal_texts (id, name, version, title, content, created_at)
            VALUES (UUID(), ?, 1, ?, ?, NOW())
        ");
        
        foreach ($texts as $text) {
            $stmt->execute($text);
        }
        success("Standard-Textbausteine eingefügt");
    } else {
        success("Legal_texts Tabelle existiert bereits");
    }
    
    // 4. Users-Tabelle erweitern (falls nötig)
    info("\nPrüfe Users-Tabelle...");
    $columns = $pdo->query("SHOW COLUMNS FROM users")->fetchAll(PDO::FETCH_COLUMN);
    
    $requiredColumns = ['company', 'phone', 'address'];
    $missingColumns = array_diff($requiredColumns, $columns);
    
    if (!empty($missingColumns)) {
        foreach ($missingColumns as $column) {
            try {
                $pdo->exec("ALTER TABLE users ADD COLUMN $column TEXT NULL");
                success("Spalte '$column' zur Users-Tabelle hinzugefügt");
            } catch (PDOException $e) {
                warning("Spalte '$column' konnte nicht hinzugefügt werden: " . $e->getMessage());
            }
        }
    } else {
        success("Users-Tabelle hat alle erforderlichen Spalten");
    }
    
    // 5. Zusammenfassung
    info("\n=== ZUSAMMENFASSUNG ===");
    
    $tables = [
        'settings' => "SELECT COUNT(*) as count FROM settings",
        'system_log' => "SELECT COUNT(*) as count FROM system_log",
        'legal_texts' => "SELECT COUNT(*) as count FROM legal_texts",
        'users' => "SELECT COUNT(*) as count FROM users"
    ];
    
    foreach ($tables as $table => $query) {
        try {
            $count = $pdo->query($query)->fetchColumn();
            info("Tabelle $table: $count Einträge");
        } catch (PDOException $e) {
            error("Tabelle $table: Fehler beim Zählen");
        }
    }
    
    // Test: Eine Einstellung speichern
    info("\n=== FUNKTIONSTEST ===");
    
    $testKey = 'repair_test_' . time();
    $testValue = 'Reparatur erfolgreich um ' . date('H:i:s');
    
    $stmt = $pdo->prepare("INSERT INTO settings (`key`, `value`) VALUES (?, ?) ON DUPLICATE KEY UPDATE `value` = VALUES(`value`)");
    if ($stmt->execute([$testKey, $testValue])) {
        success("Test-Einstellung gespeichert: $testKey");
        
        // Verifizieren
        $stmt = $pdo->prepare("SELECT `value` FROM settings WHERE `key` = ?");
        $stmt->execute([$testKey]);
        $dbValue = $stmt->fetchColumn();
        
        if ($dbValue === $testValue) {
            success("Einstellung korrekt verifiziert");
        } else {
            error("Verifikation fehlgeschlagen");
        }
    } else {
        error("Test-Einstellung konnte nicht gespeichert werden");
    }
    
    success("\n✅ DATENBANK-REPARATUR ABGESCHLOSSEN!");
    info("Sie können jetzt die Einstellungen-Seite verwenden.");
    
} catch (Exception $e) {
    error("\n❌ KRITISCHER FEHLER: " . $e->getMessage());
    error("Stack-Trace:");
    echo $e->getTraceAsString() . "\n";
}
