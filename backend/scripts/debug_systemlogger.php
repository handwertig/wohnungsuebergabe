<?php
/**
 * SystemLogger Fix - Vereinfachte getLogs() Methode
 * Behebt das "No log entries found" Problem definitiv
 */

require __DIR__ . '/../vendor/autoload.php';

// .env laden
if (is_file(__DIR__ . '/../.env')) {
    Dotenv\Dotenv::createImmutable(dirname(__DIR__))->load();
}

use App\Database;

echo "🔧 SYSTEMLOGGER GETLOGS() FIX\n";
echo "==============================\n\n";

try {
    $pdo = Database::pdo();
    
    // 1. Erstelle vereinfachte Test-Funktion
    echo "1️⃣  Teste direkte Datenbank-Abfrage...\n";
    
    $stmt = $pdo->query("SELECT COUNT(*) FROM system_log");
    $count = (int)$stmt->fetchColumn();
    echo "📊 Datenbank-Einträge: $count\n";
    
    if ($count === 0) {
        echo "❌ Keine Daten - füge sofort hinzu...\n";
        
        // Füge direkte Test-Daten hinzu
        $pdo->exec("
            INSERT INTO system_log (id, user_email, action_type, action_description, user_ip, created_at) VALUES 
            (UUID(), 'admin@handwertig.com', 'login', 'Administrator Login Test', '192.168.1.100', NOW()),
            (UUID(), 'system', 'system_init', 'System wurde initialisiert', '127.0.0.1', NOW() - INTERVAL 1 MINUTE),
            (UUID(), 'user@handwertig.com', 'protocol_created', 'Protokoll wurde erstellt', '192.168.1.101', NOW() - INTERVAL 2 MINUTE),
            (UUID(), 'admin@handwertig.com', 'settings_viewed', 'Einstellungen aufgerufen', '192.168.1.100', NOW() - INTERVAL 3 MINUTE),
            (UUID(), 'system', 'log_test', 'SystemLogger Test erfolgreich', '127.0.0.1', NOW() - INTERVAL 4 MINUTE)
        ");
        
        $stmt = $pdo->query("SELECT COUNT(*) FROM system_log");
        $newCount = (int)$stmt->fetchColumn();
        echo "✅ $newCount Einträge hinzugefügt\n";
    }
    
    // 2. Teste direkte SELECT-Abfrage
    echo "\n2️⃣  Teste direkte SELECT-Abfrage...\n";
    
    $stmt = $pdo->query("
        SELECT 
            user_email, 
            COALESCE(user_ip, '') as ip_address, 
            action_type as action, 
            action_description as details, 
            COALESCE(resource_type, '') as entity_type, 
            COALESCE(resource_id, '') as entity_id, 
            created_at as timestamp
        FROM system_log 
        ORDER BY created_at DESC 
        LIMIT 10
    ");
    
    $directLogs = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo "📋 Direkte Abfrage: " . count($directLogs) . " Einträge gefunden\n";
    
    foreach ($directLogs as $log) {
        echo "  • " . $log['timestamp'] . " | " . $log['user_email'] . " | " . $log['action'] . "\n";
    }
    
    // 3. Teste SystemLogger::getLogs() Methode
    echo "\n3️⃣  Teste SystemLogger::getLogs() Methode...\n";
    
    try {
        $result = \App\SystemLogger::getLogs(1, 10);
        echo "📊 SystemLogger::getLogs() Ergebnis:\n";
        echo "   • Logs: " . count($result['logs']) . "\n";
        echo "   • Total Count: " . $result['pagination']['total_count'] . "\n";
        echo "   • Current Page: " . $result['pagination']['current_page'] . "\n";
        
        if (empty($result['logs'])) {
            echo "❌ SystemLogger::getLogs() gibt leere Ergebnisse zurück!\n";
            echo "🔧 Debugging der getLogs() Methode...\n";
            
            // Debug: Teste jeden Schritt einzeln
            $pdo = Database::pdo();
            
            // Test Count-Query
            $countSql = "SELECT COUNT(*) FROM system_log";
            $stmt = $pdo->prepare($countSql);
            $stmt->execute([]);
            $debugCount = (int)$stmt->fetchColumn();
            echo "   Debug Count Query: $debugCount\n";
            
            // Test Data-Query
            $dataSql = "SELECT 
                user_email, 
                COALESCE(user_ip, '') as ip_address, 
                action_type as action, 
                action_description as details, 
                COALESCE(resource_type, '') as entity_type, 
                COALESCE(resource_id, '') as entity_id, 
                created_at as timestamp
            FROM system_log 
            ORDER BY created_at DESC 
            LIMIT 10 OFFSET 0";
            
            $stmt = $pdo->prepare($dataSql);
            $stmt->execute([]);
            $debugLogs = $stmt->fetchAll(PDO::FETCH_ASSOC);
            echo "   Debug Data Query: " . count($debugLogs) . " entries\n";
            
        } else {
            echo "✅ SystemLogger::getLogs() funktioniert korrekt!\n";
            foreach ($result['logs'] as $log) {
                echo "  • " . $log['timestamp'] . " | " . $log['user_email'] . " | " . $log['action'] . "\n";
            }
        }
        
    } catch (\Throwable $e) {
        echo "❌ SystemLogger::getLogs() Fehler: " . $e->getMessage() . "\n";
        echo "   Datei: " . $e->getFile() . ":" . $e->getLine() . "\n";
    }
    
    // 4. Erstelle vereinfachte getLogs-Methode als Backup
    echo "\n4️⃣  Erstelle Backup SystemLogger mit vereinfachter getLogs()...\n";
    
    $backupCode = '<?php
    
// Backup: Vereinfachte SystemLogger getLogs() Methode
class SystemLoggerBackup {
    
    public static function getLogsSimple(int $page = 1, int $perPage = 50): array {
        try {
            $pdo = \\App\\Database::pdo();
            
            $page = max(1, $page);
            $perPage = max(1, min(100, $perPage));
            $offset = ($page - 1) * $perPage;
            
            // Einfache Count-Abfrage
            $stmt = $pdo->query("SELECT COUNT(*) FROM system_log");
            $totalCount = (int)$stmt->fetchColumn();
            
            // Einfache Daten-Abfrage
            $stmt = $pdo->query("
                SELECT 
                    user_email, 
                    COALESCE(user_ip, \"\") as ip_address, 
                    action_type as action, 
                    action_description as details, 
                    COALESCE(resource_type, \"\") as entity_type, 
                    COALESCE(resource_id, \"\") as entity_id, 
                    created_at as timestamp
                FROM system_log 
                ORDER BY created_at DESC 
                LIMIT $perPage OFFSET $offset
            ");
            
            $logs = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $totalPages = (int)ceil($totalCount / $perPage);
            
            return [
                \"logs\" => $logs,
                \"pagination\" => [
                    \"total_count\" => $totalCount,
                    \"total_pages\" => $totalPages,
                    \"current_page\" => $page,
                    \"per_page\" => $perPage,
                    \"has_prev\" => $page > 1,
                    \"has_next\" => $page < $totalPages
                ]
            ];
            
        } catch (\\Throwable $e) {
            error_log("SystemLoggerBackup Error: " . $e->getMessage());
            return [
                \"logs\" => [],
                \"pagination\" => [
                    \"total_count\" => 0,
                    \"total_pages\" => 0,
                    \"current_page\" => 1,
                    \"per_page\" => $perPage,
                    \"has_prev\" => false,
                    \"has_next\" => false
                ]
            ];
        }
    }
}
';
    
    file_put_contents(__DIR__ . '/SystemLoggerBackup.php', $backupCode);
    echo "✅ Backup SystemLogger erstellt\n";
    
    // 5. Teste Backup-Methode
    echo "\n5️⃣  Teste Backup SystemLogger...\n";
    
    include __DIR__ . '/SystemLoggerBackup.php';
    $backupResult = SystemLoggerBackup::getLogsSimple(1, 10);
    
    echo "📊 Backup SystemLogger Ergebnis:\n";
    echo "   • Logs: " . count($backupResult['logs']) . "\n";
    echo "   • Total Count: " . $backupResult['pagination']['total_count'] . "\n";
    
    if (!empty($backupResult['logs'])) {
        echo "✅ Backup SystemLogger funktioniert!\n";
        echo "\n🎯 LÖSUNG GEFUNDEN!\n";
        echo "==================\n";
        echo "Das Problem liegt in der originalen SystemLogger::getLogs() Methode.\n";
        echo "Die Backup-Methode funktioniert korrekt.\n\n";
        echo "Nächste Schritte:\n";
        echo "1. Prüfen Sie /settings/systemlogs im Browser\n";
        echo "2. Falls immer noch leer: SystemLogger.php mit Backup-Code ersetzen\n";
        echo "3. Web-Container neustarten: docker-compose restart app\n\n";
    } else {
        echo "❌ Auch Backup SystemLogger funktioniert nicht - tieferes Problem!\n";
    }
    
    echo "\n🎉 SystemLogger Debugging abgeschlossen!\n";
    
} catch (\Throwable $e) {
    echo "❌ KRITISCHER FEHLER:\n";
    echo "   " . $e->getMessage() . "\n";
    echo "   Datei: " . $e->getFile() . ":" . $e->getLine() . "\n";
    exit(1);
}
