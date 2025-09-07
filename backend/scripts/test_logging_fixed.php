<?php
/**
 * Test-Script für Logging-Funktionalität
 * Führt verschiedene Logging-Tests durch und prüft die Funktionsfähigkeit
 */

declare(strict_types=1);

// Bootstrap
require_once dirname(__DIR__) . '/src/autoload.php';

echo "=== Logging-Test gestartet ===\n\n";

try {
    $pdo = App\Database::pdo();
    echo "✓ Datenbankverbindung erfolgreich\n";
    
    // 1. Prüfe ob system_log Tabelle existiert
    echo "\n1. Prüfe system_log Tabelle...\n";
    try {
        $stmt = $pdo->query("SHOW TABLES LIKE 'system_log'");
        if ($stmt->fetch()) {
            echo "✓ system_log Tabelle existiert\n";
            
            // Struktur prüfen
            $stmt = $pdo->query("DESCRIBE system_log");
            $columns = $stmt->fetchAll(PDO::FETCH_COLUMN);
            echo "✓ Spalten: " . implode(", ", $columns) . "\n";
            
            // Anzahl aktueller Einträge
            $stmt = $pdo->query("SELECT COUNT(*) FROM system_log");
            $count = $stmt->fetchColumn();
            echo "✓ Aktuelle Einträge: {$count}\n";
        } else {
            echo "✗ system_log Tabelle nicht gefunden\n";
            echo "➤ Führen Sie die Migration aus: ./ultimate_fix.sh\n";
            exit(1);
        }
    } catch (Exception $e) {
        echo "✗ Fehler beim Prüfen der system_log Tabelle: " . $e->getMessage() . "\n";
        exit(1);
    }
    
    // 2. Test SystemLogger Klasse
    echo "\n2. Teste SystemLogger...\n";
    try {
        if (class_exists('App\SystemLogger')) {
            echo "✓ SystemLogger Klasse gefunden\n";
            
            // Test einfaches Logging
            App\SystemLogger::log('test_basic', 'Basis-Test des SystemLogger');
            echo "✓ Basis-Logging funktioniert\n";
            
            // Test mit zusätzlichen Daten
            App\SystemLogger::log('test_advanced', 'Erweiterter Test', 'test', 'test-123', [
                'test_key' => 'test_value',
                'timestamp' => date('Y-m-d H:i:s')
            ]);
            echo "✓ Erweiterte Logging-Funktionen funktionieren\n";
            
            // Test Protokoll-spezifische Methoden
            App\SystemLogger::logProtocolViewed('test-protocol-id', [
                'type' => 'einzug',
                'tenant_name' => 'Test Mieter',
                'city' => 'Teststadt',
                'street' => 'Teststraße',
                'unit' => 'EG'
            ]);
            echo "✓ Protokoll-spezifische Logging-Methoden funktionieren\n";
            
        } else {
            echo "✗ SystemLogger Klasse nicht gefunden\n";
        }
    } catch (Exception $e) {
        echo "✗ Fehler beim Testen des SystemLogger: " . $e->getMessage() . "\n";
    }
    
    // 3. Test AuditLogger Klasse
    echo "\n3. Teste AuditLogger...\n";
    try {
        if (class_exists('App\AuditLogger')) {
            echo "✓ AuditLogger Klasse gefunden\n";
            
            // Prüfe ob audit_log Tabelle existiert
            $stmt = $pdo->query("SHOW TABLES LIKE 'audit_log'");
            if ($stmt->fetch()) {
                echo "✓ audit_log Tabelle existiert\n";
                
                // Test AuditLogger
                App\AuditLogger::log('protocol', 'test-protocol-123', 'created', [
                    'tenant_name' => 'Test Mieter',
                    'type' => 'einzug'
                ]);
                echo "✓ AuditLogger funktioniert\n";
            } else {
                echo "⚠ audit_log Tabelle nicht gefunden - AuditLogger wird nicht funktionieren\n";
            }
        } else {
            echo "✗ AuditLogger Klasse nicht gefunden\n";
        }
    } catch (Exception $e) {
        echo "✗ Fehler beim Testen des AuditLogger: " . $e->getMessage() . "\n";
    }
    
    // 4. Prüfe neueste Log-Einträge
    echo "\n4. Prüfe neueste Log-Einträge...\n";
    try {
        $stmt = $pdo->query("
            SELECT user_email, action_type, action_description, created_at 
            FROM system_log 
            ORDER BY created_at DESC 
            LIMIT 5
        ");
        $logs = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        if (!empty($logs)) {
            echo "✓ Neueste Log-Einträge:\n";
            foreach ($logs as $log) {
                $time = date('H:i:s', strtotime($log['created_at']));
                echo "  [{$time}] {$log['user_email']}: {$log['action_type']} - {$log['action_description']}\n";
            }
        } else {
            echo "⚠ Keine Log-Einträge gefunden\n";
        }
    } catch (Exception $e) {
        echo "✗ Fehler beim Abrufen der Log-Einträge: " . $e->getMessage() . "\n";
    }
    
    // 5. Test der Settings-Controller System-Log Anzeige
    echo "\n5. Teste System-Log Anzeige (SettingsController)...\n";
    try {
        // Simuliere die gleiche Abfrage wie im SettingsController
        $queries = [
            "SELECT 
                COALESCE(user_email, 'system') as user_email,
                COALESCE(user_ip, '127.0.0.1') as ip_address,
                COALESCE(action_type, 'unknown') as action,
                COALESCE(action_description, 'No description') as details,
                created_at as timestamp
            FROM system_log 
            ORDER BY created_at DESC 
            LIMIT 3",
            
            "SELECT 
                user_email,
                user_ip as ip_address,
                action_type as action,
                action_description as details,
                created_at as timestamp
            FROM system_log 
            ORDER BY created_at DESC 
            LIMIT 3"
        ];
        
        $success = false;
        foreach ($queries as $i => $query) {
            try {
                $stmt = $pdo->query($query);
                $testLogs = $stmt->fetchAll(PDO::FETCH_ASSOC);
                if (!empty($testLogs)) {
                    echo "✓ Query " . ($i + 1) . " erfolgreich - " . count($testLogs) . " Einträge\n";
                    $success = true;
                    break;
                }
            } catch (PDOException $e) {
                echo "⚠ Query " . ($i + 1) . " fehlgeschlagen: " . $e->getMessage() . "\n";
            }
        }
        
        if (!$success) {
            echo "✗ Alle Queries für System-Log Anzeige fehlgeschlagen\n";
        }
    } catch (Exception $e) {
        echo "✗ Fehler beim Testen der System-Log Anzeige: " . $e->getMessage() . "\n";
    }
    
    echo "\n=== Logging-Test abgeschlossen ===\n";
    echo "Status: ";
    
    // Final Status Check
    $stmt = $pdo->query("SELECT COUNT(*) FROM system_log WHERE action_type LIKE 'test_%'");
    $testEntries = $stmt->fetchColumn();
    
    if ($testEntries >= 3) {
        echo "✓ ERFOLGREICH - Logging funktioniert korrekt!\n";
        echo "➤ {$testEntries} Test-Einträge wurden erfolgreich erstellt\n";
        echo "➤ Sie können nun http://localhost:8080/settings/systemlogs aufrufen\n";
        exit(0);
    } else {
        echo "⚠ TEILWEISE ERFOLGREICH - Einige Logging-Funktionen könnten nicht funktionieren\n";
        echo "➤ Nur {$testEntries} Test-Einträge gefunden\n";
        exit(1);
    }
    
} catch (Exception $e) {
    echo "✗ KRITISCHER FEHLER: " . $e->getMessage() . "\n";
    echo "➤ Überprüfen Sie die Datenbank-Konfiguration und Migrationen\n";
    exit(1);
}
?>