<?php
/**
 * Test-Script für System Logging
 * Überprüft ob alles korrekt funktioniert
 */

require_once __DIR__ . '/../vendor/autoload.php';

use App\SystemLogger;

echo "System Logging Test\n";
echo "==================\n\n";

try {
    // Test 1: Grundlegendes Logging
    echo "Test 1: Grundlegendes Logging...\n";
    SystemLogger::log('test_action', 'Test-Eintrag vom Test-Script');
    echo "✅ Erfolgreich\n\n";

    // Test 2: Logs abrufen
    echo "Test 2: Logs abrufen...\n";
    $result = SystemLogger::getLogs(1, 5);
    echo "✅ {$result['pagination']['total_count']} Einträge gefunden\n\n";

    // Test 3: Filter-Funktionen
    echo "Test 3: Filter-Funktionen...\n";
    $actions = SystemLogger::getAvailableActions();
    $users = SystemLogger::getAvailableUsers();
    echo "✅ " . count($actions) . " verfügbare Aktionen\n";
    echo "✅ " . count($users) . " verfügbare Benutzer\n\n";

    // Test 4: Spezifische Logging-Methoden
    echo "Test 4: Spezifische Logging-Methoden...\n";
    SystemLogger::logProtocolCreated('test-123', 'Test Mieter', 'einzug');
    SystemLogger::logPdfGenerated('test-123', 1);
    SystemLogger::logEmailSent('test-123', 'owner', 'test@example.com', true);
    echo "✅ Spezifische Log-Methoden funktionieren\n\n";

    echo "🎉 Alle Tests erfolgreich!\n";
    echo "Das System-Logging funktioniert korrekt.\n\n";

    echo "Letzte 3 Log-Einträge:\n";
    echo "---------------------\n";
    $recent = SystemLogger::getLogs(1, 3);
    foreach ($recent['logs'] as $log) {
        echo sprintf(
            "%s | %s | %s | %s\n",
            date('d.m.Y H:i:s', strtotime($log['timestamp'])),
            $log['user_email'],
            $log['action'],
            substr($log['details'], 0, 50)
        );
    }

} catch (Exception $e) {
    echo "❌ Fehler: " . $e->getMessage() . "\n";
    echo "Bitte führen Sie zuerst das Setup-Script aus.\n";
}
