<?php
/**
 * Einfache SQL-Reparatur - führt nur die kritischen Befehle aus
 */

require __DIR__ . '/../vendor/autoload.php';
if (is_file(__DIR__ . '/../.env')) {
    Dotenv\Dotenv::createImmutable(dirname(__DIR__))->load();
}

try {
    $pdo = App\Database::pdo();
    
    echo "Führe SQL-Reparatur aus...\n\n";
    
    // 1. created_by Spalte hinzufügen
    $pdo->exec("ALTER TABLE protocol_events ADD COLUMN IF NOT EXISTS created_by VARCHAR(255) NULL AFTER message");
    echo "✅ created_by Spalte hinzugefügt\n";
    
    // 2. Test-Event mit aktueller Zeit
    $stmt = $pdo->prepare("INSERT INTO protocol_events (id, protocol_id, type, message, created_by, created_at) VALUES (UUID(), ?, 'other', ?, ?, NOW())");
    $stmt->execute([
        '82cc7de7-7d1e-11f0-89a6-822b82242c5d',
        'Reparatur durchgeführt: ' . date('Y-m-d H:i:s'),
        'repair@script.com'
    ]);
    echo "✅ Test-Event eingefügt\n";
    
    // 3. Anzahl Events prüfen
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM protocol_events WHERE protocol_id = ?");
    $stmt->execute(['82cc7de7-7d1e-11f0-89a6-822b82242c5d']);
    $count = $stmt->fetchColumn();
    echo "📊 {$count} Events für Test-Protokoll gefunden\n\n";
    
    echo "✅ REPARATUR ABGESCHLOSSEN!\n";
    echo "Teste jetzt das normale Speichern.\n";
    
} catch (Throwable $e) {
    echo "❌ FEHLER: " . $e->getMessage() . "\n";
}
?>