<?php
/**
 * Automatische SQL-Reparatur für protocol_events Tabelle
 * Diese Datei führt die notwendigen SQL-Befehle aus
 */

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';
if (is_file(__DIR__ . '/../.env')) {
    Dotenv\Dotenv::createImmutable(dirname(__DIR__))->load();
}

echo "=== AUTOMATISCHE SQL-REPARATUR ===\n\n";

try {
    $pdo = App\Database::pdo();
    
    echo "✅ Datenbankverbindung hergestellt\n\n";
    
    // 1. created_by Spalte hinzufügen falls sie fehlt
    echo "1. Prüfe und repariere protocol_events Tabelle...\n";
    
    try {
        $pdo->exec("ALTER TABLE protocol_events ADD COLUMN IF NOT EXISTS created_by VARCHAR(255) NULL AFTER message");
        echo "   ✅ created_by Spalte hinzugefügt (oder war bereits vorhanden)\n";
    } catch (PDOException $e) {
        echo "   ⚠ created_by Spalte: " . $e->getMessage() . "\n";
    }
    
    // 2. Index hinzufügen
    try {
        $pdo->exec("CREATE INDEX IF NOT EXISTS idx_events_created_by ON protocol_events (created_by)");
        echo "   ✅ Index für created_by erstellt\n";
    } catch (PDOException $e) {
        echo "   ⚠ Index: " . $e->getMessage() . "\n";
    }
    
    // 3. Test-Event einfügen
    echo "\n2. Füge Test-Event hinzu...\n";
    
    $stmt = $pdo->prepare("
        INSERT INTO protocol_events (id, protocol_id, type, message, created_by, created_at) 
        VALUES (UUID(), ?, 'other', ?, ?, NOW())
    ");
    
    $testInserted = $stmt->execute([
        '82cc7de7-7d1e-11f0-89a6-822b82242c5d',
        'SQL-Reparatur Test durchgeführt',
        'sql@repair.com'
    ]);
    
    if ($testInserted) {
        echo "   ✅ Test-Event erfolgreich eingefügt\n";
    } else {
        echo "   ❌ Test-Event konnte nicht eingefügt werden\n";
    }
    
    // 4. Tabellenstruktur prüfen
    echo "\n3. Prüfe Tabellenstruktur...\n";
    
    $stmt = $pdo->query("DESCRIBE protocol_events");
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $hasCreatedBy = false;
    foreach ($columns as $column) {
        if ($column['Field'] === 'created_by') {
            $hasCreatedBy = true;
            echo "   ✅ created_by Spalte gefunden: " . $column['Type'] . "\n";
            break;
        }
    }
    
    if (!$hasCreatedBy) {
        echo "   ❌ created_by Spalte fehlt immer noch!\n";
    }
    
    // 5. Aktuelle Events für Test-Protokoll zeigen
    echo "\n4. Zeige Events für Test-Protokoll...\n";
    
    $stmt = $pdo->prepare("
        SELECT type, message, created_by, created_at 
        FROM protocol_events 
        WHERE protocol_id = ? 
        ORDER BY created_at DESC 
        LIMIT 5
    ");
    $stmt->execute(['82cc7de7-7d1e-11f0-89a6-822b82242c5d']);
    $events = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (!empty($events)) {
        echo "   ✅ " . count($events) . " Events gefunden:\n";
        foreach ($events as $event) {
            echo "      - [{$event['created_at']}] {$event['type']}: {$event['message']} (von {$event['created_by']})\n";
        }
    } else {
        echo "   ⚠ Keine Events gefunden\n";
    }
    
    // 6. Status-Report
    echo "\n5. Status-Report...\n";
    
    $stmt = $pdo->query("
        SELECT 
            COUNT(*) as anzahl_events,
            COUNT(DISTINCT protocol_id) as protokolle_mit_events,
            MAX(created_at) as neuestes_event
        FROM protocol_events
    ");
    $stats = $stmt->fetch(PDO::FETCH_ASSOC);
    
    echo "   📊 Gesamt Events: {$stats['anzahl_events']}\n";
    echo "   📊 Protokolle mit Events: {$stats['protokolle_mit_events']}\n";
    echo "   📊 Neuestes Event: {$stats['neuestes_event']}\n";
    
    // 7. System-Log Tabelle prüfen
    echo "\n6. Prüfe system_log Tabelle...\n";
    
    try {
        $stmt = $pdo->query("SELECT COUNT(*) as count FROM system_log LIMIT 1");
        $systemLogCount = $stmt->fetch(PDO::FETCH_ASSOC);
        echo "   ✅ system_log Tabelle verfügbar mit {$systemLogCount['count']} Einträgen\n";
    } catch (PDOException $e) {
        echo "   ⚠ system_log Tabelle: " . $e->getMessage() . "\n";
    }
    
    echo "\n" . str_repeat("=", 50) . "\n";
    echo "✅ SQL-REPARATUR ERFOLGREICH ABGESCHLOSSEN!\n";
    echo str_repeat("=", 50) . "\n\n";
    
    echo "🎯 NÄCHSTE SCHRITTE:\n";
    echo "1. Gehe zu: http://localhost:8080/protocols/edit?id=82cc7de7-7d1e-11f0-89a6-822b82242c5d\n";
    echo "2. Ändere den Mieternamen\n";
    echo "3. Klicke 'Speichern'\n";
    echo "4. Prüfe ob Success-Message erscheint\n";
    echo "5. Prüfe ob unter 'Ereignisse & Änderungen' neue Einträge stehen\n\n";
    
} catch (Throwable $e) {
    echo "❌ KRITISCHER FEHLER: " . $e->getMessage() . "\n";
    echo "   Datei: " . $e->getFile() . ":" . $e->getLine() . "\n";
    echo "\n🔧 MANUELLE REPARATUR ERFORDERLICH:\n";
    echo "Führe diese SQL-Befehle manuell in deiner Datenbank aus:\n\n";
    echo "ALTER TABLE protocol_events ADD COLUMN IF NOT EXISTS created_by VARCHAR(255) NULL AFTER message;\n";
    echo "CREATE INDEX IF NOT EXISTS idx_events_created_by ON protocol_events (created_by);\n";
}
?>