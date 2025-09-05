<?php
/**
 * MINIMAL SYSTEMLOG TEST
 * Direkte, einfachste Implementierung ohne komplexe Logik
 */

require __DIR__ . '/../vendor/autoload.php';

// .env laden
if (is_file(__DIR__ . '/../.env')) {
    Dotenv\Dotenv::createImmutable(dirname(__DIR__))->load();
}

use App\Database;

echo "🔧 MINIMAL SYSTEMLOG TEST\n";
echo "=========================\n\n";

try {
    $pdo = Database::pdo();
    echo "✅ Datenbank-Verbindung OK\n";
    
    // 1. Direkte SQL-Abfrage - einfachst möglich
    echo "\n1️⃣  Direkte COUNT-Abfrage...\n";
    $stmt = $pdo->query("SELECT COUNT(*) FROM system_log");
    $count = $stmt->fetchColumn();
    echo "📊 Einträge in DB: $count\n";
    
    // 2. Direkte SELECT-Abfrage
    echo "\n2️⃣  Direkte SELECT-Abfrage...\n";
    $stmt = $pdo->query("SELECT user_email, action_type, action_description, created_at FROM system_log ORDER BY created_at DESC LIMIT 5");
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "📋 Gefundene Einträge: " . count($rows) . "\n";
    foreach ($rows as $row) {
        echo "  • " . $row['created_at'] . " | " . $row['user_email'] . " | " . $row['action_type'] . " | " . $row['action_description'] . "\n";
    }
    
    // 3. Teste SystemLogger::getLogs() minimal
    echo "\n3️⃣  Teste SystemLogger::getLogs()...\n";
    
    $result = \App\SystemLogger::getLogs(1, 10);
    echo "📊 SystemLogger Ergebnis:\n";
    echo "   • Count: " . count($result['logs']) . "\n";
    echo "   • Total: " . $result['pagination']['total_count'] . "\n";
    
    if (count($result['logs']) > 0) {
        echo "✅ SystemLogger funktioniert!\n";
        foreach ($result['logs'] as $log) {
            echo "  • " . $log['timestamp'] . " | " . $log['user_email'] . " | " . $log['action'] . "\n";
        }
    } else {
        echo "❌ SystemLogger gibt leere Ergebnisse!\n";
        
        // Debug: Was macht getLogs() genau?
        echo "\n🔍 Debug SystemLogger::getLogs()...\n";
        echo "Calling: SystemLogger::getLogs(1, 10, null, null, null, null, null)\n";
        
        // Manueller Test der getLogs-Logik
        $page = 1;
        $perPage = 10;
        $offset = ($page - 1) * $perPage;
        
        $testSql = "SELECT 
            user_email, 
            IFNULL(user_ip, '') as ip_address, 
            action_type as action, 
            action_description as details, 
            IFNULL(resource_type, '') as entity_type, 
            IFNULL(resource_id, '') as entity_id, 
            created_at as timestamp
        FROM system_log 
        ORDER BY created_at DESC 
        LIMIT $perPage OFFSET $offset";
        
        echo "SQL: $testSql\n";
        
        $testStmt = $pdo->query($testSql);
        $testRows = $testStmt->fetchAll(PDO::FETCH_ASSOC);
        echo "Direct SQL Result: " . count($testRows) . " rows\n";
        
        if (count($testRows) > 0) {
            echo "❗ Direct SQL works, but SystemLogger::getLogs() doesn't!\n";
            echo "   Problem ist in der SystemLogger-Implementierung!\n";
        }
    }
    
    echo "\n" . str_repeat("=", 50) . "\n";
    
    if ($count > 0 && count($rows) > 0) {
        echo "🎯 DATENBANK IST OK - Problem liegt im PHP-Code!\n";
        echo "Datenbank hat $count Einträge, aber SystemLogger findet sie nicht.\n";
    } else {
        echo "❌ DATENBANK-PROBLEM - Keine Einträge vorhanden!\n";
    }
    
} catch (\Throwable $e) {
    echo "❌ FEHLER: " . $e->getMessage() . "\n";
    echo "   Datei: " . $e->getFile() . ":" . $e->getLine() . "\n";
}
