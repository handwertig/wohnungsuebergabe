<?php
/**
 * Direkter Save-Test - umgeht alle potentiellen Probleme
 */

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';
if (is_file(__DIR__ . '/../.env')) {
    Dotenv\Dotenv::createImmutable(dirname(__DIR__))->load();
}

echo "=== DIREKTER SAVE-TEST ===\n\n";

try {
    $pdo = App\Database::pdo();
    $protocolId = '82cc7de7-7d1e-11f0-89a6-822b82242c5d';
    
    // 1. Aktueller Status
    echo "1. Aktueller Status prüfen...\n";
    $stmt = $pdo->prepare("SELECT tenant_name, updated_at FROM protocols WHERE id = ?");
    $stmt->execute([$protocolId]);
    $before = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$before) {
        echo "❌ Protokoll nicht gefunden!\n";
        exit;
    }
    
    echo "   - Aktueller Mieter: {$before['tenant_name']}\n";
    echo "   - Letztes Update: {$before['updated_at']}\n\n";
    
    // 2. Direktes Update ohne Controller
    echo "2. Führe direktes SQL-Update durch...\n";
    
    $newTenantName = $before['tenant_name'] . ' [DIREKT ' . date('H:i:s') . ']';
    $newUpdateTime = date('Y-m-d H:i:s');
    
    $stmt = $pdo->prepare("UPDATE protocols SET tenant_name = ?, updated_at = ? WHERE id = ?");
    $result = $stmt->execute([$newTenantName, $newUpdateTime, $protocolId]);
    
    if ($result) {
        echo "   ✅ SQL-Update erfolgreich\n";
        echo "   - Neuer Mieter: {$newTenantName}\n";
        echo "   - Neues Update: {$newUpdateTime}\n";
    } else {
        echo "   ❌ SQL-Update fehlgeschlagen\n";
    }
    
    // 3. Verifikation
    echo "\n3. Verifikation...\n";
    $stmt = $pdo->prepare("SELECT tenant_name, updated_at FROM protocols WHERE id = ?");
    $stmt->execute([$protocolId]);
    $after = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($after['tenant_name'] !== $before['tenant_name']) {
        echo "   ✅ Änderung wurde gespeichert!\n";
        echo "   - Vorher: {$before['tenant_name']}\n";
        echo "   - Nachher: {$after['tenant_name']}\n";
    } else {
        echo "   ❌ Keine Änderung erkennbar\n";
    }
    
    // 4. Event erstellen
    echo "\n4. Protocol Event erstellen...\n";
    
    try {
        $stmt = $pdo->prepare("
            INSERT INTO protocol_events (id, protocol_id, type, message, created_by, created_at) 
            VALUES (UUID(), ?, 'other', ?, ?, NOW())
        ");
        $stmt->execute([$protocolId, 'Direkter Test: ' . date('H:i:s'), 'direct@test.com']);
        echo "   ✅ Event erstellt\n";
    } catch (PDOException $e) {
        echo "   ❌ Event-Fehler: " . $e->getMessage() . "\n";
    }
    
    // 5. System-Log erstellen
    echo "\n5. System-Log erstellen...\n";
    
    try {
        if (class_exists('\\App\\SystemLogger')) {
            \App\SystemLogger::logProtocolUpdated($protocolId, [
                'type' => 'auszug',
                'tenant_name' => $newTenantName,
                'city' => 'Test Stadt',
                'street' => 'Test Straße',
                'unit' => 'Test Einheit'
            ], ['tenant_name']);
            echo "   ✅ SystemLogger aufgerufen\n";
        } else {
            echo "   ❌ SystemLogger nicht verfügbar\n";
        }
    } catch (Throwable $e) {
        echo "   ❌ SystemLogger Fehler: " . $e->getMessage() . "\n";
    }
    
    echo "\n" . str_repeat("=", 50) . "\n";
    echo "✅ DIREKTER TEST ABGESCHLOSSEN\n";
    echo str_repeat("=", 50) . "\n\n";
    
    echo "🔍 NÄCHSTE SCHRITTE:\n";
    echo "1. Gehe zu: http://localhost:8080/protocols/edit?id={$protocolId}\n";
    echo "2. Prüfe ob der neue Mieter angezeigt wird\n";
    echo "3. Prüfe unter 'Ereignisse & Änderungen' ob neue Einträge da sind\n";
    echo "4. Teste erneut das normale Speichern\n\n";
    
    echo "💡 Falls der direkte Test funktioniert, aber das normale Speichern nicht:\n";
    echo "   → Das Problem liegt im ProtocolsController oder Routing\n";
    echo "   → Prüfe die Server-Logs auf Fehler\n\n";
    
    echo "📝 Falls auch der direkte Test nicht funktioniert:\n";
    echo "   → Datenbank-Verbindung oder -Berechtigung Problem\n";
    echo "   → MySQL-Logs prüfen\n";
    
} catch (Throwable $e) {
    echo "❌ KRITISCHER FEHLER: " . $e->getMessage() . "\n";
    echo "   Datei: " . $e->getFile() . ":" . $e->getLine() . "\n";
    echo "   Stack: " . $e->getTraceAsString() . "\n";
}
?>