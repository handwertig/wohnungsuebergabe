<?php
/**
 * Test: SystemLogger Funktionalität
 */

require __DIR__ . '/../vendor/autoload.php';

// .env laden
if (is_file(__DIR__ . '/../.env')) {
    Dotenv\Dotenv::createImmutable(dirname(__DIR__))->load();
}

use App\SystemLogger;

try {
    echo "🧪 Teste SystemLogger Funktionalität...\n";
    
    // Test 1: Einfacher Log-Eintrag
    SystemLogger::log('test_action', 'SystemLogger Test - einfacher Eintrag');
    echo "✅ Test 1: Einfacher Log-Eintrag erfolgreich\n";
    
    // Test 2: Log-Eintrag mit Resource
    SystemLogger::logProtocolCreated('test-protocol-id', 'Test Mieter', 'einzug');
    echo "✅ Test 2: Protokoll-Log erfolgreich\n";
    
    // Test 3: Logs laden
    $result = SystemLogger::getLogs(1, 5);
    echo "✅ Test 3: Logs laden erfolgreich - " . count($result['logs']) . " Einträge gefunden\n";
    
    // Test 4: Filter testen
    $result = SystemLogger::getLogs(1, 5, 'test');
    echo "✅ Test 4: Filter-Test erfolgreich - " . count($result['logs']) . " gefilterte Einträge\n";
    
    // Test 5: Verfügbare Aktionen
    $actions = SystemLogger::getAvailableActions();
    echo "✅ Test 5: " . count($actions) . " verfügbare Aktionen gefunden\n";
    
    echo "\n🎉 Alle SystemLogger Tests erfolgreich!\n";
    
} catch (\Throwable $e) {
    echo "❌ SystemLogger Test fehlgeschlagen: " . $e->getMessage() . "\n";
    exit(1);
}
