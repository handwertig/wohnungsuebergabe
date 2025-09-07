<?php
/**
 * Prüft ob der Silent Test erfolgreich war
 */

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';
if (is_file(__DIR__ . '/../.env')) {
    Dotenv\Dotenv::createImmutable(dirname(__DIR__))->load();
}

header('Content-Type: application/json');

try {
    $pdo = App\Database::pdo();
    $protocolId = '82cc7de7-7d1e-11f0-89a6-822b82242c5d';
    
    // Aktueller Zustand
    $stmt = $pdo->prepare("SELECT tenant_name, updated_at FROM protocols WHERE id = ?");
    $stmt->execute([$protocolId]);
    $current = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$current) {
        echo json_encode(['error' => 'Protocol not found']);
        exit;
    }
    
    // Prüfe ob kürzlich geändert
    $recentlyUpdated = strtotime($current['updated_at']) > (time() - 300); // 5 Minuten
    $hasSilentTest = strpos($current['tenant_name'], 'SILENT TEST') !== false;
    
    // Events prüfen
    $recentEvents = 0;
    try {
        $stmt = $pdo->prepare("
            SELECT COUNT(*) 
            FROM protocol_events 
            WHERE protocol_id = ? 
              AND created_at >= ?
        ");
        $stmt->execute([$protocolId, date('Y-m-d H:i:s', strtotime('-5 minutes'))]);
        $recentEvents = (int)$stmt->fetchColumn();
    } catch (PDOException $e) {
        // Events Tabelle nicht verfügbar
    }
    
    // System-Logs prüfen
    $recentSystemLogs = 0;
    try {
        $stmt = $pdo->prepare("
            SELECT COUNT(*) 
            FROM system_log 
            WHERE resource_id = ? 
              AND created_at >= ?
        ");
        $stmt->execute([$protocolId, date('Y-m-d H:i:s', strtotime('-5 minutes'))]);
        $recentSystemLogs = (int)$stmt->fetchColumn();
    } catch (PDOException $e) {
        // System-Log Tabelle nicht verfügbar
    }
    
    $success = $recentlyUpdated && $hasSilentTest;
    
    echo json_encode([
        'success' => $success,
        'current_tenant' => $current['tenant_name'],
        'last_updated' => $current['updated_at'],
        'recently_updated' => $recentlyUpdated,
        'has_silent_test' => $hasSilentTest,
        'recent_events' => $recentEvents,
        'recent_system_logs' => $recentSystemLogs,
        'timestamp' => date('Y-m-d H:i:s'),
        'message' => $success ? 'Save-Funktion arbeitet korrekt!' : 'Save-Funktion benötigt weitere Reparatur'
    ], JSON_PRETTY_PRINT);
    
} catch (Throwable $e) {
    echo json_encode([
        'error' => true,
        'message' => $e->getMessage(),
        'file' => basename($e->getFile()),
        'line' => $e->getLine()
    ]);
}
?>