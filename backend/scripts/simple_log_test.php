<?php
/**
 * Direkter System-Log Test mit SQL Insert
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/src/autoload.php';

try {
    $pdo = App\Database::pdo();
    
    // Direct SQL insert test
    $stmt = $pdo->prepare("
        INSERT INTO system_log (id, user_email, action_type, action_description, user_ip, created_at) 
        VALUES (UUID(), ?, ?, ?, ?, NOW())
    ");
    
    $testEntries = [
        ['claude@test.com', 'system_test', 'Claude Test-Eintrag: System-Log funktioniert', '127.0.0.1'],
        ['admin@test.com', 'protocol_test', 'Claude Test: Protokoll-Logging repariert', '127.0.0.1'],
        ['system', 'fix_applied', 'Claude Fix: Logging-Funktionen wiederhergestellt', '127.0.0.1']
    ];
    
    foreach ($testEntries as $entry) {
        $stmt->execute($entry);
    }
    
    echo "✓ Test-Einträge erfolgreich eingefügt\n";
    
    // Verify entries
    $stmt = $pdo->query("
        SELECT user_email, action_type, action_description, created_at 
        FROM system_log 
        WHERE action_description LIKE '%Claude%' 
        ORDER BY created_at DESC 
        LIMIT 5
    ");
    
    $logs = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo "✓ " . count($logs) . " Claude Test-Einträge gefunden\n";
    
    foreach ($logs as $log) {
        echo "  - [{$log['created_at']}] {$log['user_email']}: {$log['action_type']}\n";
    }
    
} catch (Exception $e) {
    echo "✗ Fehler: " . $e->getMessage() . "\n";
}
?>