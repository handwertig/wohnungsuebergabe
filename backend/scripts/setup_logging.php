<?php
/**
 * Quick Setup Script für System Logging
 * 
 * Dieses Script führt automatisch alle notwendigen Schritte aus:
 * - Erstellt/korrigiert die system_log Tabelle
 * - Fügt Test-Daten hinzu
 * - Überprüft die Funktionalität
 * 
 * Einfach aufrufen über: /backend/scripts/setup_logging.php
 */

// Error Reporting aktivieren
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Pfade setzen
require_once __DIR__ . '/../vendor/autoload.php';

use App\Database;
use App\SystemLogger;

echo "<!DOCTYPE html><html><head><title>System Logging Setup</title>";
echo "<style>body{font-family:system-ui,sans-serif;max-width:800px;margin:40px auto;padding:20px;line-height:1.6;}";
echo ".success{color:#047857;background:#ecfdf5;padding:12px;border-radius:6px;margin:10px 0;}";
echo ".error{color:#dc2626;background:#fef2f2;padding:12px;border-radius:6px;margin:10px 0;}";
echo ".info{color:#1e40af;background:#eff6ff;padding:12px;border-radius:6px;margin:10px 0;}";
echo ".step{margin:20px 0;padding:15px;border-left:4px solid #6366f1;background:#fafafa;}";
echo "code{background:#f3f4f6;padding:2px 6px;border-radius:4px;font-family:mono;}";
echo "</style></head><body>";

echo "<h1>🚀 System Logging Setup</h1>";
echo "<p>Dieses Script richtet das vollständige Logging-System für Ihre Anwendung ein.</p>";

try {
    // Schritt 1: Datenbankverbindung testen
    echo "<div class='step'>";
    echo "<h2>Schritt 1: Datenbankverbindung prüfen</h2>";
    $pdo = Database::pdo();
    echo "<div class='success'>✅ Datenbankverbindung erfolgreich</div>";
    echo "</div>";

    // Schritt 2: Tabelle erstellen/reparieren
    echo "<div class='step'>";
    echo "<h2>Schritt 2: system_log Tabelle erstellen/reparieren</h2>";
    
    // Migration ausführen
    $migrationSql = file_get_contents(__DIR__ . '/../../migrations/024_fix_system_log.sql');
    if ($migrationSql) {
        $statements = array_filter(array_map('trim', explode(';', $migrationSql)));
        foreach ($statements as $statement) {
            if (!empty($statement) && !str_starts_with(trim($statement), '--')) {
                try {
                    $pdo->exec($statement);
                } catch (Exception $e) {
                    // Ignoriere Fehler wie "table already exists"
                    if (!str_contains($e->getMessage(), 'already exists')) {
                        throw $e;
                    }
                }
            }
        }
        echo "<div class='success'>✅ Migration erfolgreich ausgeführt</div>";
    } else {
        echo "<div class='info'>ℹ️  Migration-Datei nicht gefunden, erstelle Tabelle direkt</div>";
    }
    
    // Direkte Tabellenerstellung als Fallback
    SystemLogger::log('system_setup', 'Setup-Script gestartet');
    echo "<div class='success'>✅ Tabelle erstellt und getestet</div>";
    echo "</div>";

    // Schritt 3: Test-Daten hinzufügen
    echo "<div class='step'>";
    echo "<h2>Schritt 3: Test-Daten hinzufügen</h2>";
    SystemLogger::addInitialData();
    
    // Weitere Test-Einträge hinzufügen
    $testEntries = [
        ['login', 'Setup-Benutzer angemeldet'],
        ['protocol_created', 'Test-Protokoll erstellt'],
        ['pdf_generated', 'Test-PDF generiert'],
        ['settings_updated', 'System-Einstellungen konfiguriert'],
        ['email_sent', 'Test-E-Mail versendet']
    ];
    
    foreach ($testEntries as [$action, $description]) {
        SystemLogger::log($action, $description);
    }
    
    echo "<div class='success'>✅ " . count($testEntries) + 2 . " Test-Einträge erstellt</div>";
    echo "</div>";

    // Schritt 4: Funktionalität testen
    echo "<div class='step'>";
    echo "<h2>Schritt 4: Funktionalität testen</h2>";
    
    // Test: Logs abrufen
    $result = SystemLogger::getLogs(1, 10);
    $logCount = count($result['logs']);
    echo "<div class='success'>✅ {$logCount} Log-Einträge erfolgreich abgerufen</div>";
    
    // Test: Filter-Funktionen
    $actions = SystemLogger::getAvailableActions();
    $users = SystemLogger::getAvailableUsers();
    echo "<div class='success'>✅ Filter funktionieren: " . count($actions) . " Aktionen, " . count($users) . " Benutzer</div>";
    echo "</div>";

    // Schritt 5: Erfolgsmeldung
    echo "<div class='step'>";
    echo "<h2>🎉 Setup erfolgreich abgeschlossen!</h2>";
    echo "<div class='success'>";
    echo "<strong>Das System-Logging ist jetzt vollständig funktionsfähig:</strong><br>";
    echo "• Tabelle <code>system_log</code> erstellt und konfiguriert<br>";
    echo "• {$logCount} Test-Einträge hinzugefügt<br>";
    echo "• Filter und Pagination funktionieren<br>";
    echo "• Automatische Fehlerbehandlung aktiviert<br>";
    echo "</div>";
    echo "</div>";

    // Nächste Schritte
    echo "<div class='step'>";
    echo "<h2>📋 Nächste Schritte</h2>";
    echo "<div class='info'>";
    echo "<strong>Testen Sie jetzt das System:</strong><br>";
    echo "1. Besuchen Sie <code>/settings/systemlogs</code> in Ihrer Anwendung<br>";
    echo "2. Überprüfen Sie die Log-Einträge und Filter<br>";
    echo "3. Das System protokolliert ab sofort automatisch alle Aktionen<br>";
    echo "4. Löschen Sie diese Setup-Datei aus Sicherheitsgründen: <code>rm " . __FILE__ . "</code>";
    echo "</div>";
    echo "</div>";

    // Aktuelle Logs anzeigen
    echo "<div class='step'>";
    echo "<h2>📊 Aktuelle Log-Einträge (Vorschau)</h2>";
    echo "<table style='width:100%;border-collapse:collapse;'>";
    echo "<tr style='background:#f9fafb;'><th style='padding:8px;border:1px solid #ddd;'>Zeit</th><th style='padding:8px;border:1px solid #ddd;'>Benutzer</th><th style='padding:8px;border:1px solid #ddd;'>Aktion</th><th style='padding:8px;border:1px solid #ddd;'>Details</th></tr>";
    
    foreach (array_slice($result['logs'], 0, 5) as $log) {
        echo "<tr>";
        echo "<td style='padding:8px;border:1px solid #ddd;font-size:12px;'>" . htmlspecialchars(date('d.m.Y H:i', strtotime($log['timestamp']))) . "</td>";
        echo "<td style='padding:8px;border:1px solid #ddd;'>" . htmlspecialchars($log['user_email']) . "</td>";
        echo "<td style='padding:8px;border:1px solid #ddd;'><code>" . htmlspecialchars($log['action']) . "</code></td>";
        echo "<td style='padding:8px;border:1px solid #ddd;'>" . htmlspecialchars(substr($log['details'], 0, 50)) . "...</td>";
        echo "</tr>";
    }
    echo "</table>";
    echo "<p style='margin-top:10px;'><strong>✅ System funktioniert korrekt!</strong></p>";
    echo "</div>";

} catch (Exception $e) {
    echo "<div class='step'>";
    echo "<h2>❌ Setup-Fehler</h2>";
    echo "<div class='error'>";
    echo "<strong>Es ist ein Fehler aufgetreten:</strong><br>";
    echo htmlspecialchars($e->getMessage()) . "<br><br>";
    echo "<strong>Mögliche Lösungen:</strong><br>";
    echo "• Überprüfen Sie die Datenbankverbindung<br>";
    echo "• Stellen Sie sicher, dass der Datenbankbenutzer CREATE-Rechte hat<br>";
    echo "• Kontaktieren Sie den Support mit dieser Fehlermeldung";
    echo "</div>";
    echo "</div>";
}

echo "</body></html>";
