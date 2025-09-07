<?php
declare(strict_types=1);

// Debug-Skript für die Protokoll-Versionierung

require __DIR__ . '/../vendor/autoload.php';
if (is_file(__DIR__ . '/../.env')) {
    Dotenv\Dotenv::createImmutable(dirname(__DIR__))->load();
}

try {
    $pdo = \App\Database::pdo();
    $protocolId = '82cc7de7-7d1e-11f0-89a6-822b82242c5d';
    
    echo "=== DEBUG: Protokoll-Versionierung ===\n";
    echo "Protokoll ID: $protocolId\n\n";
    
    // 1. Prüfe aktuelles Protokoll
    $st = $pdo->prepare("SELECT p.*, u.label AS unit_label, o.city, o.street FROM protocols p 
                         JOIN units u ON u.id=p.unit_id 
                         JOIN objects o ON o.id=u.object_id 
                         WHERE p.id=?");
    $st->execute([$protocolId]);
    $protocol = $st->fetch(PDO::FETCH_ASSOC);
    
    if (!$protocol) {
        echo "❌ FEHLER: Protokoll nicht gefunden!\n";
        exit;
    }
    
    echo "✅ Protokoll gefunden:\n";
    echo "   - Mieter: {$protocol['tenant_name']}\n";
    echo "   - Typ: {$protocol['type']}\n";
    echo "   - Adresse: {$protocol['city']}, {$protocol['street']}\n\n";
    
    // 2. Prüfe vorhandene Versionen
    $st = $pdo->prepare("SELECT version_no, created_at, created_by FROM protocol_versions WHERE protocol_id=? ORDER BY version_no DESC");
    $st->execute([$protocolId]);
    $versions = $st->fetchAll(PDO::FETCH_ASSOC);
    
    echo "📋 Vorhandene Versionen (" . count($versions) . "):\n";
    foreach ($versions as $v) {
        echo "   - v{$v['version_no']} - {$v['created_at']} - {$v['created_by']}\n";
    }
    echo "\n";
    
    // 3. Berechne nächste Versionsnummer
    $st = $pdo->prepare("SELECT COALESCE(MAX(version_no), 0) + 1 FROM protocol_versions WHERE protocol_id=?");
    $st->execute([$protocolId]);
    $nextVersion = (int)$st->fetchColumn();
    echo "🔢 Nächste Versionsnummer: $nextVersion\n\n";
    
    // 4. Prüfe Events
    $st = $pdo->prepare("SELECT type, message, created_at FROM protocol_events WHERE protocol_id=? ORDER BY created_at DESC LIMIT 5");
    $st->execute([$protocolId]);
    $events = $st->fetchAll(PDO::FETCH_ASSOC);
    
    echo "📝 Letzte Events (" . count($events) . "):\n";
    foreach ($events as $e) {
        echo "   - {$e['created_at']}: {$e['type']} - {$e['message']}\n";
    }
    echo "\n";
    
    // 5. Prüfe SystemLogger
    if (class_exists('\App\SystemLogger')) {
        echo "✅ SystemLogger Klasse existiert\n";
        
        // Prüfe system_log Tabelle
        $st = $pdo->prepare("SELECT COUNT(*) FROM system_log WHERE resource_id=?");
        $st->execute([$protocolId]);
        $systemLogCount = $st->fetchColumn();
        echo "📊 SystemLog Einträge für dieses Protokoll: $systemLogCount\n";
        
        // Zeige letzte SystemLog Einträge
        $st = $pdo->prepare("SELECT action_type, action_description, created_at FROM system_log WHERE resource_id=? ORDER BY created_at DESC LIMIT 3");
        $st->execute([$protocolId]);
        $systemLogs = $st->fetchAll(PDO::FETCH_ASSOC);
        
        echo "🔍 Letzte SystemLog Einträge:\n";
        foreach ($systemLogs as $log) {
            echo "   - {$log['created_at']}: {$log['action_type']} - {$log['action_description']}\n";
        }
    } else {
        echo "❌ SystemLogger Klasse nicht gefunden!\n";
    }
    
    // 6. Teste eine einfache Version-Erstellung
    echo "\n=== TEST: Neue Version erstellen ===\n";
    
    $testData = [
        'tenant_name' => $protocol['tenant_name'] . ' [DEBUG TEST]',
        'type' => $protocol['type'],
        'test' => true,
        'timestamp' => date('Y-m-d H:i:s')
    ];
    
    try {
        $pdo->beginTransaction();
        
        // Version erstellen
        $st = $pdo->prepare("INSERT INTO protocol_versions (id, protocol_id, version_no, data, created_by, created_at) VALUES (UUID(), ?, ?, ?, ?, NOW())");
        $result = $st->execute([$protocolId, $nextVersion, json_encode($testData), 'debug@test.com']);
        
        if ($result) {
            echo "✅ Test-Version $nextVersion erfolgreich erstellt\n";
            
            // Event erstellen
            $st = $pdo->prepare("INSERT INTO protocol_events (id, protocol_id, type, message, created_at) VALUES (UUID(), ?, 'other', ?, NOW())");
            $st->execute([$protocolId, "Debug Test-Version $nextVersion erstellt"]);
            echo "✅ Test-Event erstellt\n";
            
            // SystemLogger testen
            if (class_exists('\App\SystemLogger')) {
                \App\SystemLogger::logProtocolUpdated($protocolId, [
                    'tenant_name' => $testData['tenant_name'],
                    'type' => $protocol['type'],
                    'city' => $protocol['city'],
                    'street' => $protocol['street'],
                    'unit' => $protocol['unit_label']
                ], ['test' => 'debug_version']);
                echo "✅ SystemLogger Test aufgerufen\n";
            }
            
        } else {
            echo "❌ Fehler beim Erstellen der Test-Version\n";
        }
        
        $pdo->commit();
        echo "✅ Transaktion committed\n";
        
    } catch (\Throwable $e) {
        $pdo->rollback();
        echo "❌ FEHLER: " . $e->getMessage() . "\n";
        echo "   Datei: " . $e->getFile() . ":" . $e->getLine() . "\n";
    }
    
    echo "\n=== DEBUG ABGESCHLOSSEN ===\n";
    
} catch (\Throwable $e) {
    echo "❌ KRITISCHER FEHLER: " . $e->getMessage() . "\n";
    echo "   Datei: " . $e->getFile() . ":" . $e->getLine() . "\n";
}
