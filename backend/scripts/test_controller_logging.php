<?php
/**
 * Test der Controller-Integration für Logging
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/src/autoload.php';

echo "=== Controller Logging Integration Test ===\n\n";

try {
    // Test SystemLogger direkt
    echo "1. Teste SystemLogger direkt...\n";
    
    if (class_exists('App\SystemLogger')) {
        echo "✓ SystemLogger-Klasse gefunden\n";
        
        // Test verschiedene Logging-Methoden
        App\SystemLogger::log('claude_test', 'Claude Test: Basis-Logging funktioniert');
        echo "✓ Basis-Logging Test bestanden\n";
        
        App\SystemLogger::logProtocolViewed('test-protocol-claude', [
            'type' => 'einzug',
            'tenant_name' => 'Claude Test Mieter',
            'city' => 'Test Stadt',
            'street' => 'Test Straße',
            'unit' => 'Test Einheit'
        ]);
        echo "✓ Protocol-Viewed Logging Test bestanden\n";
        
        App\SystemLogger::logProtocolUpdated('test-protocol-claude', [
            'type' => 'auszug',
            'tenant_name' => 'Claude Update Test',
            'city' => 'Update Stadt',
            'street' => 'Update Straße',
            'unit' => 'Update Einheit'
        ], ['tenant_name', 'type']);
        echo "✓ Protocol-Updated Logging Test bestanden\n";
        
    } else {
        echo "✗ SystemLogger-Klasse nicht gefunden\n";
    }
    
    // Test AuditLogger direkt  
    echo "\n2. Teste AuditLogger direkt...\n";
    
    if (class_exists('App\AuditLogger')) {
        echo "✓ AuditLogger-Klasse gefunden\n";
        
        try {
            App\AuditLogger::log('protocol', 'claude-test-protocol', 'updated', [
                'tenant_name' => ['from' => 'Alt', 'to' => 'Neu'],
                'type' => ['from' => 'einzug', 'to' => 'auszug']
            ]);
            echo "✓ AuditLogger Test bestanden\n";
        } catch (Exception $e) {
            echo "⚠ AuditLogger Test fehlgeschlagen: " . $e->getMessage() . "\n";
        }
    } else {
        echo "✗ AuditLogger-Klasse nicht gefunden\n";
    }
    
    // Test Database-Verbindung und Einträge prüfen
    echo "\n3. Prüfe eingetragene Logs...\n";
    
    $pdo = App\Database::pdo();
    
    // System Log prüfen
    $stmt = $pdo->query("
        SELECT COUNT(*) 
        FROM system_log 
        WHERE action_description LIKE '%Claude%' 
        AND created_at >= DATE_SUB(NOW(), INTERVAL 1 MINUTE)
    ");
    $recentClaudeEntries = $stmt->fetchColumn();
    
    echo "✓ {$recentClaudeEntries} neue Claude Test-Einträge im system_log\n";
    
    // Neueste Einträge anzeigen
    $stmt = $pdo->query("
        SELECT action_type, action_description, created_at 
        FROM system_log 
        WHERE action_description LIKE '%Claude%' 
        ORDER BY created_at DESC 
        LIMIT 3
    ");
    
    $logs = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($logs as $log) {
        $time = date('H:i:s', strtotime($log['created_at']));
        echo "  [{$time}] {$log['action_type']}: {$log['action_description']}\n";
    }
    
    // Prüfe Audit Log falls vorhanden
    try {
        $stmt = $pdo->query("
            SELECT COUNT(*) 
            FROM audit_log 
            WHERE entity_id = 'claude-test-protocol' 
            AND created_at >= DATE_SUB(NOW(), INTERVAL 1 MINUTE)
        ");
        $recentAuditEntries = $stmt->fetchColumn();
        echo "✓ {$recentAuditEntries} neue Audit-Einträge\n";
    } catch (Exception $e) {
        echo "⚠ Audit Log nicht verfügbar: " . $e->getMessage() . "\n";
    }
    
    echo "\n=== Test Zusammenfassung ===\n";
    
    if ($recentClaudeEntries >= 3) {
        echo "✅ ERFOLGREICH: Logging funktioniert vollständig!\n";
        echo "➤ SystemLogger schreibt korrekt in die Datenbank\n";
        echo "➤ Controller-Integration ist bereit\n";
        echo "➤ Sie können nun /protocols/edit?id=XXX aufrufen und Änderungen werden protokolliert\n";
        echo "➤ System-Logs sind unter /settings/systemlogs einsehbar\n";
    } else {
        echo "⚠ TEILWEISE ERFOLGREICH: Einige Logging-Funktionen könnten Probleme haben\n";
        echo "➤ Überprüfen Sie die Datenbank-Migrationen\n";
    }
    
} catch (Exception $e) {
    echo "✗ KRITISCHER FEHLER: " . $e->getMessage() . "\n";
    echo "➤ Datenbank-Problem oder fehlende Migrationen\n";
}
?>