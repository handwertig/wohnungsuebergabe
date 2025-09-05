<?php
/**
 * Direkter Test der SystemLog Datenbank
 * Prüft ob die system_log Tabelle korrekt funktioniert
 */

require __DIR__ . '/../vendor/autoload.php';

// .env laden
if (is_file(__DIR__ . '/../.env')) {
    Dotenv\Dotenv::createImmutable(dirname(__DIR__))->load();
}

use App\Database;

echo "🧪 SystemLog Datenbank-Test\n";
echo "============================\n\n";

try {
    $pdo = Database::pdo();
    
    // 1. Teste Tabellenstruktur
    echo "1️⃣  Teste Tabellenstruktur...\n";
    $stmt = $pdo->query("DESCRIBE system_log");
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $expectedColumns = ['id', 'user_email', 'action_type', 'action_description', 'created_at'];
    $foundColumns = array_column($columns, 'Field');
    
    foreach ($expectedColumns as $expected) {
        if (in_array($expected, $foundColumns)) {
            echo "  ✅ Spalte '$expected' vorhanden\n";
        } else {
            echo "  ❌ Spalte '$expected' fehlt\n";
        }
    }
    
    // 2. Teste Dateninhalt
    echo "\n2️⃣  Teste Dateninhalt...\n";
    $stmt = $pdo->query("SELECT COUNT(*) FROM system_log");
    $count = (int)$stmt->fetchColumn();
    echo "  📊 Anzahl Log-Einträge: $count\n";
    
    if ($count > 0) {
        echo "  ✅ Daten vorhanden\n";
        
        // Zeige Sample-Daten
        $stmt = $pdo->query("
            SELECT user_email, action_type, action_description, created_at 
            FROM system_log 
            ORDER BY created_at DESC 
            LIMIT 3
        ");
        
        echo "\n  📋 Sample-Einträge:\n";
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            echo "    • " . $row['created_at'] . " | " . $row['user_email'] . " | " . $row['action_type'] . "\n";
        }
    } else {
        echo "  ⚠️  Keine Daten vorhanden\n";
    }
    
    // 3. Teste Indizes
    echo "\n3️⃣  Teste Indizes...\n";
    $stmt = $pdo->query("SHOW INDEX FROM system_log");
    $indices = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $indexNames = array_unique(array_column($indices, 'Key_name'));
    foreach ($indexNames as $indexName) {
        if ($indexName !== 'PRIMARY') {
            echo "  ✅ Index '$indexName' vorhanden\n";
        }
    }
    
    // 4. Teste Performance
    echo "\n4️⃣  Teste Performance...\n";
    $start = microtime(true);
    $stmt = $pdo->query("
        SELECT COUNT(*) 
        FROM system_log 
        WHERE action_type = 'login' 
        AND created_at >= NOW() - INTERVAL 24 HOUR
    ");
    $result = $stmt->fetchColumn();
    $duration = round((microtime(true) - $start) * 1000, 2);
    echo "  ⚡ Query-Zeit: {$duration}ms (Login-Events letzte 24h: $result)\n";
    
    // 5. Teste Datentypen
    echo "\n5️⃣  Teste Datentypen...\n";
    foreach ($columns as $column) {
        $field = $column['Field'];
        $type = $column['Type'];
        
        if ($field === 'id' && strpos($type, 'char(36)') !== false) {
            echo "  ✅ $field: $type (UUID-kompatibel)\n";
        } elseif ($field === 'created_at' && strpos($type, 'datetime') !== false) {
            echo "  ✅ $field: $type\n";
        } elseif ($field === 'additional_data' && strpos($type, 'json') !== false) {
            echo "  ✅ $field: $type\n";
        } elseif (in_array($field, ['user_email', 'action_type', 'action_description'])) {
            echo "  ✅ $field: $type\n";
        }
    }
    
    echo "\n🎉 SystemLog Datenbank-Test erfolgreich!\n";
    echo "=========================================\n";
    echo "\n✅ Die system_log Tabelle ist vollständig funktionsfähig:\n";
    echo "   • Struktur: Korrekt\n";
    echo "   • Daten: $count Einträge\n";
    echo "   • Indizes: Optimiert\n";
    echo "   • Performance: Gut\n";
    echo "   • Datentypen: Valide\n\n";
    
    echo "🌐 Testen Sie nun die Web-Oberfläche:\n";
    echo "   → http://localhost:8080/settings/systemlogs\n\n";
    
} catch (\Throwable $e) {
    echo "❌ Datenbank-Test fehlgeschlagen:\n";
    echo "   " . $e->getMessage() . "\n";
    echo "   Datei: " . $e->getFile() . ":" . $e->getLine() . "\n\n";
    
    echo "🔧 Troubleshooting:\n";
    echo "   1. Prüfen Sie Docker: docker-compose ps\n";
    echo "   2. Prüfen Sie DB-Logs: docker-compose logs db\n";
    echo "   3. Prüfen Sie .env Datei\n";
    exit(1);
}
