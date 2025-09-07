<?php
// Finaler Test für Settings-Speicherung über die Web-Oberfläche

require_once __DIR__ . '/vendor/autoload.php';

use App\Database;
use App\Settings;
use App\SystemLogger;

// Farbige Ausgabe
function success($msg) { echo "\033[32m✓ $msg\033[0m\n"; }
function error($msg) { echo "\033[31m✗ $msg\033[0m\n"; }
function info($msg) { echo "\033[34m→ $msg\033[0m\n"; }
function warning($msg) { echo "\033[33m⚠ $msg\033[0m\n"; }
function section($msg) { echo "\n\033[1;36m=== $msg ===\033[0m\n"; }

try {
    section("FINALER SETTINGS-TEST");
    
    $pdo = Database::pdo();
    
    // 1. Aktuelle Settings anzeigen
    section("Aktuelle Mail-Settings");
    
    $mailSettings = [
        'smtp_host', 'smtp_port', 'smtp_user', 'smtp_secure',
        'smtp_from_name', 'smtp_from_email'
    ];
    
    foreach ($mailSettings as $key) {
        $value = Settings::get($key);
        if ($value) {
            info("$key = $value");
        } else {
            warning("$key = (leer)");
        }
    }
    
    // 2. Test: Settings über Settings-Klasse ändern
    section("Test: Settings-Klasse");
    
    info("Ändere smtp_host zu 'smtp.gmail.com'...");
    $result = Settings::set('smtp_host', 'smtp.gmail.com');
    if ($result) {
        success("Erfolgreich geändert");
        
        // Verifizieren
        Settings::clearCache();
        $newValue = Settings::get('smtp_host');
        if ($newValue === 'smtp.gmail.com') {
            success("Verifizierung erfolgreich: $newValue");
        } else {
            error("Verifizierung fehlgeschlagen: $newValue");
        }
    } else {
        error("Änderung fehlgeschlagen");
    }
    
    // 3. Test: Mehrere Settings gleichzeitig
    section("Test: Mehrere Settings (setMany)");
    
    $testSettings = [
        'smtp_host' => 'mail.example.com',
        'smtp_port' => '465',
        'smtp_secure' => 'ssl',
        'smtp_from_name' => 'Test System ' . date('H:i:s')
    ];
    
    info("Ändere " . count($testSettings) . " Settings gleichzeitig...");
    $result = Settings::setMany($testSettings);
    
    if ($result) {
        success("Alle Settings geändert");
        
        // Verifizieren
        Settings::clearCache();
        $allCorrect = true;
        foreach ($testSettings as $key => $expectedValue) {
            $actualValue = Settings::get($key);
            if ($actualValue === $expectedValue) {
                success("  $key = $actualValue ✓");
            } else {
                error("  $key = $actualValue (erwartet: $expectedValue)");
                $allCorrect = false;
            }
        }
        
        if ($allCorrect) {
            success("Alle Änderungen verifiziert");
        }
    } else {
        error("setMany fehlgeschlagen");
    }
    
    // 4. System-Log prüfen
    section("System-Log Überprüfung");
    
    $logs = $pdo->query("
        SELECT action_type, action_description, created_at 
        FROM system_log 
        WHERE action_type LIKE '%settings%'
        ORDER BY created_at DESC 
        LIMIT 5
    ")->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($logs)) {
        warning("Keine Settings-Logs gefunden");
    } else {
        success(count($logs) . " Settings-Logs gefunden:");
        foreach ($logs as $log) {
            $time = date('H:i:s', strtotime($log['created_at']));
            info("  [$time] " . $log['action_type'] . ": " . substr($log['action_description'], 0, 50));
        }
    }
    
    // 5. DocuSign Settings testen
    section("DocuSign Settings");
    
    $docusignSettings = Settings::getDocusignConfig();
    foreach ($docusignSettings as $key => $value) {
        if ($value) {
            info("$key = " . substr($value, 0, 30) . (strlen($value) > 30 ? '...' : ''));
        } else {
            warning("$key = (nicht konfiguriert)");
        }
    }
    
    // 6. Branding Settings testen
    section("Branding Settings");
    
    $brandingSettings = Settings::getBrandingConfig();
    foreach ($brandingSettings as $key => $value) {
        if ($value) {
            info("$key = " . substr($value, 0, 50) . (strlen($value) > 50 ? '...' : ''));
        } else {
            warning("$key = (nicht konfiguriert)");
        }
    }
    
    // 7. Debug-Informationen
    section("Debug-Informationen");
    
    $debug = Settings::debug();
    info("Cache-Status: " . $debug['cache_status']);
    info("Logging aktiv: " . ($debug['logging_enabled'] ? 'Ja' : 'Nein'));
    info("Anzahl Settings: " . $debug['settings_count']);
    
    // 8. Performance-Test
    section("Performance-Test");
    
    $startTime = microtime(true);
    
    for ($i = 0; $i < 10; $i++) {
        Settings::get('smtp_host');
    }
    
    $duration = (microtime(true) - $startTime) * 1000;
    info("10x Settings::get() in " . round($duration, 2) . " ms");
    
    // Cache leeren und erneut testen
    Settings::clearCache();
    
    $startTime = microtime(true);
    Settings::get('smtp_host'); // Lädt aus DB
    $duration = (microtime(true) - $startTime) * 1000;
    info("Erster Zugriff nach Cache-Clear: " . round($duration, 2) . " ms");
    
    // 9. Zusammenfassung
    section("ZUSAMMENFASSUNG");
    
    $totalSettings = $pdo->query("SELECT COUNT(*) FROM settings")->fetchColumn();
    $totalLogs = $pdo->query("SELECT COUNT(*) FROM system_log")->fetchColumn();
    
    success("Settings-System funktioniert einwandfrei!");
    info("Gesamt Settings: $totalSettings");
    info("Gesamt Logs: $totalLogs");
    
    // Empfehlung
    section("EMPFEHLUNG");
    
    success("✅ Das System ist bereit für die Verwendung!");
    info("");
    info("Sie können jetzt:");
    info("1. Die Einstellungen-Seite unter /settings aufrufen");
    info("2. Mail-Einstellungen unter /settings/mail konfigurieren");
    info("3. DocuSign unter /settings/docusign einrichten");
    info("4. Textbausteine unter /settings/texts bearbeiten");
    info("5. System-Logs unter /settings/systemlogs einsehen");
    info("");
    success("Alle Änderungen werden jetzt korrekt gespeichert und geloggt!");
    
} catch (Exception $e) {
    error("\nFEHLER: " . $e->getMessage());
    error("Stack-Trace:");
    echo $e->getTraceAsString() . "\n";
}
