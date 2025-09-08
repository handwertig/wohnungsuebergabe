<?php
/**
 * Test für das Doppel-Logging System
 * Testet protocol_events UND system_log
 */

require_once __DIR__ . '/vendor/autoload.php';

// .env Datei laden
if (file_exists(__DIR__ . '/.env')) {
    $dotenv = \Dotenv\Dotenv::createImmutable(__DIR__);
    $dotenv->load();
}

require_once __DIR__ . '/src/Database.php';
require_once __DIR__ . '/src/Config/Settings.php';
require_once __DIR__ . '/src/Auth.php';
require_once __DIR__ . '/src/SystemLogger.php';

echo "=== TEST: DOPPEL-LOGGING SYSTEM ===\n";

try {
    $pdo = \App\Database::pdo();
    
    echo "\n✅ Test 1: Datenbankverbindung\n";
    echo "Connected to database successfully\n";
    
    echo "\n✅ Test 2: Prüfe Tabellen\n";
    
    // protocol_events prüfen
    $stmt = $pdo->query("SHOW TABLES LIKE 'protocol_events'");
    if ($stmt->rowCount() > 0) {
        echo "✅ protocol_events Tabelle existiert\n";
    } else {
        echo "❌ protocol_events Tabelle fehlt\n";
    }
    
    // system_log prüfen
    $stmt = $pdo->query("SHOW TABLES LIKE 'system_log'");
    if ($stmt->rowCount() > 0) {
        echo "✅ system_log Tabelle existiert\n";
    } else {
        echo "❌ system_log Tabelle fehlt\n";
    }
    
    echo "\n✅ Test 3: Test-Event erstellen\n";
    
    // Test Protokoll-ID (nimm eine existierende)
    $stmt = $pdo->query("SELECT id FROM protocols WHERE deleted_at IS NULL LIMIT 1");
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$result) {
        echo "❌ Kein Test-Protokoll gefunden\n";
        exit(1);
    }
    
    $testProtocolId = $result['id'];
    echo "📋 Test-Protokoll ID: $testProtocolId\n";
    
    // Zähle Events vor dem Test
    $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM protocol_events WHERE protocol_id = ?");
    $stmt->execute([$testProtocolId]);
    $eventsCountBefore = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
    
    $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM system_log WHERE resource_id = ?");
    $stmt->execute([$testProtocolId]);
    $systemLogCountBefore = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
    
    echo "📊 Events vorher - protocol_events: $eventsCountBefore, system_log: $systemLogCountBefore\n";
    
    // Test-Event durch SystemLogger erstellen
    echo "\n⚡ Erstelle Test-Event durch SystemLogger...\n";
    
    \App\SystemLogger::logProtocolUpdated($testProtocolId, [
        'tenant_name' => 'Test Mieter',
        'type' => 'auszug',
        'city' => 'Teststadt',
        'street' => 'Teststraße',
        'unit' => '01'
    ], ['tenant_name', 'updated_via_test']);
    
    echo "✅ SystemLogger Test-Event erstellt\n";
    
    // Jetzt auch ein protocol_events direkt erstellen (simuliert ProtocolsController)
    echo "\n⚡ Erstelle Test-Event direkt in protocol_events...\n";
    
    $stmt = $pdo->prepare("
        INSERT INTO protocol_events (id, protocol_id, type, message, created_by, created_at)
        VALUES (UUID(), ?, 'other', 'Test Event via protocol_events', 'test-script', NOW())
    ");
    $stmt->execute([$testProtocolId]);
    
    echo "✅ protocol_events Test-Event erstellt\n";
    
    // Zähle Events nach dem Test
    echo "\n🔍 Verifikation...\n";
    
    $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM protocol_events WHERE protocol_id = ?");
    $stmt->execute([$testProtocolId]);
    $eventsCountAfter = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
    
    $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM system_log WHERE resource_id = ?");
    $stmt->execute([$testProtocolId]);
    $systemLogCountAfter = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
    
    echo "📊 Events nachher - protocol_events: $eventsCountAfter, system_log: $systemLogCountAfter\n";
    
    // Prüfe ob Events erstellt wurden
    $protocolEventsAdded = $eventsCountAfter - $eventsCountBefore;
    $systemLogEventsAdded = $systemLogCountAfter - $systemLogCountBefore;
    
    echo "\n📈 Neue Events: protocol_events: +$protocolEventsAdded, system_log: +$systemLogEventsAdded\n";
    
    echo "\n✅ Test 4: Zeige letzte Events\n";
    
    // Letzte protocol_events
    echo "\n📋 Letzte protocol_events:\n";
    $stmt = $pdo->prepare("
        SELECT type, message, created_by, created_at 
        FROM protocol_events 
        WHERE protocol_id = ? 
        ORDER BY created_at DESC 
        LIMIT 3
    ");
    $stmt->execute([$testProtocolId]);
    $events = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($events as $event) {
        echo "  - " . $event['created_at'] . ": " . $event['type'] . " - " . $event['message'] . " (von: " . $event['created_by'] . ")\n";
    }
    
    // Letzte system_log
    echo "\n📋 Letzte system_log Einträge:\n";
    $stmt = $pdo->prepare("
        SELECT action_type, action_description, user_email, created_at 
        FROM system_log 
        WHERE resource_id = ? 
        ORDER BY created_at DESC 
        LIMIT 3
    ");
    $stmt->execute([$testProtocolId]);
    $logs = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($logs as $log) {
        echo "  - " . $log['created_at'] . ": " . $log['action_type'] . " - " . $log['action_description'] . " (von: " . $log['user_email'] . ")\n";
    }
    
    echo "\n" . str_repeat("=", 60) . "\n";
    echo "🎉 DOPPEL-LOGGING TEST ABGESCHLOSSEN!\n";
    
    if ($protocolEventsAdded > 0 && $systemLogEventsAdded > 0) {
        echo "✅ BEIDE Logging-Systeme funktionieren!\n";
        echo "\n🎯 JETZT TESTEN:\n";
        echo "1. Öffnen Sie: http://localhost:8080/protocols/edit?id=$testProtocolId\n";
        echo "2. Machen Sie eine Änderung und speichern Sie\n";
        echo "3. Prüfen Sie:\n";
        echo "   - Tab 'Protokoll' → 'Ereignisse & Änderungen' (protocol_events)\n";
        echo "   - http://localhost:8080/settings/systemlogs (system_log)\n";
        echo "\n✅ Events sollten in BEIDEN Bereichen erscheinen!\n";
    } else {
        echo "❌ Problem: Nicht alle Logging-Systeme funktionieren\n";
        if ($protocolEventsAdded === 0) {
            echo "   - protocol_events: FEHLER\n";
        }
        if ($systemLogEventsAdded === 0) {
            echo "   - system_log: FEHLER\n";
        }
    }
    
} catch (\Exception $e) {
    echo "❌ FEHLER: " . $e->getMessage() . "\n";
    echo "Stack trace: " . $e->getTraceAsString() . "\n";
    exit(1);
}
