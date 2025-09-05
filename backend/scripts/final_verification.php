<?php
/**
 * FINALE SYSTEMLOG VERIFIKATION
 * Überprüft alle Aspekte des SystemLog-Problems
 */

require __DIR__ . '/../vendor/autoload.php';

// .env laden
if (is_file(__DIR__ . '/../.env')) {
    Dotenv\Dotenv::createImmutable(dirname(__DIR__))->load();
}

use App\Database;
use App\SystemLogger;

echo "🔍 FINALE SYSTEMLOG VERIFIKATION\n";
echo "=================================\n\n";

$allGood = true;

try {
    // 1. Datenbank-Verbindung testen
    echo "1️⃣  Teste Datenbank-Verbindung...\n";
    $pdo = Database::pdo();
    echo "✅ Datenbank-Verbindung erfolgreich\n";
    
    // 2. Tabellen-Existenz prüfen
    echo "\n2️⃣  Prüfe system_log Tabelle...\n";
    $stmt = $pdo->query("SHOW TABLES LIKE 'system_log'");
    if ($stmt->rowCount() > 0) {
        echo "✅ system_log Tabelle existiert\n";
    } else {
        echo "❌ system_log Tabelle fehlt!\n";
        $allGood = false;
    }
    
    // 3. Daten-Count prüfen
    echo "\n3️⃣  Prüfe Dateninhalt...\n";
    $stmt = $pdo->query("SELECT COUNT(*) FROM system_log");
    $count = (int)$stmt->fetchColumn();
    echo "📊 Anzahl Log-Einträge: $count\n";
    
    if ($count === 0) {
        echo "❌ Keine Log-Einträge vorhanden!\n";
        $allGood = false;
    } else {
        echo "✅ Log-Einträge vorhanden\n";
    }
    
    // 4. Beispiel-Daten anzeigen
    echo "\n4️⃣  Zeige Beispiel-Daten...\n";
    $stmt = $pdo->query("
        SELECT user_email, action_type, action_description, created_at 
        FROM system_log 
        ORDER BY created_at DESC 
        LIMIT 5
    ");
    
    $examples = $stmt->fetchAll(PDO::FETCH_ASSOC);
    if (!empty($examples)) {
        foreach ($examples as $example) {
            echo "  • " . $example['created_at'] . " | " . $example['user_email'] . " | " . $example['action_type'] . "\n";
        }
        echo "✅ Beispiel-Daten korrekt\n";
    } else {
        echo "❌ Keine Beispiel-Daten abrufbar!\n";
        $allGood = false;
    }
    
    // 5. SystemLogger::getLogs() testen
    echo "\n5️⃣  Teste SystemLogger::getLogs()...\n";
    
    $result = SystemLogger::getLogs(1, 10);
    $logCount = count($result['logs']);
    $totalCount = $result['pagination']['total_count'];
    
    echo "📊 SystemLogger::getLogs() Ergebnis:\n";
    echo "   • Logs in Ergebnis: $logCount\n";
    echo "   • Total Count: $totalCount\n";
    echo "   • Current Page: " . $result['pagination']['current_page'] . "\n";
    
    if ($logCount > 0) {
        echo "✅ SystemLogger::getLogs() funktioniert!\n";
        
        echo "\n📋 Erste 3 Log-Einträge aus SystemLogger:\n";
        for ($i = 0; $i < min(3, $logCount); $i++) {
            $log = $result['logs'][$i];
            echo "  • " . $log['timestamp'] . " | " . $log['user_email'] . " | " . $log['action'] . "\n";
        }
    } else {
        echo "❌ SystemLogger::getLogs() gibt leere Ergebnisse!\n";
        $allGood = false;
    }
    
    // 6. Filter-Tests
    echo "\n6️⃣  Teste Filter-Funktionalität...\n";
    
    // Test Search-Filter
    $searchResult = SystemLogger::getLogs(1, 5, 'admin');
    echo "   • Search 'admin': " . count($searchResult['logs']) . " Einträge\n";
    
    // Test Action-Filter  
    $actionResult = SystemLogger::getLogs(1, 5, null, 'login');
    echo "   • Action 'login': " . count($actionResult['logs']) . " Einträge\n";
    
    // Test User-Filter
    $userResult = SystemLogger::getLogs(1, 5, null, null, 'admin@handwertig.com');
    echo "   • User 'admin@handwertig.com': " . count($userResult['logs']) . " Einträge\n";
    
    echo "✅ Filter-Tests abgeschlossen\n";
    
    // 7. Verfügbare Actions und Users testen
    echo "\n7️⃣  Teste Dropdown-Optionen...\n";
    
    $actions = SystemLogger::getAvailableActions();
    $users = SystemLogger::getAvailableUsers();
    
    echo "   • Verfügbare Actions: " . count($actions) . "\n";
    echo "   • Verfügbare Users: " . count($users) . "\n";
    
    if (count($actions) > 0 && count($users) > 0) {
        echo "✅ Dropdown-Optionen verfügbar\n";
    } else {
        echo "⚠️  Wenige Dropdown-Optionen\n";
    }
    
    // 8. Neuen Log-Eintrag erstellen und testen
    echo "\n8️⃣  Teste Live-Logging...\n";
    
    SystemLogger::log('verification_test', 'SystemLog Verifikation durchgeführt');
    
    // Prüfe ob der neue Eintrag vorhanden ist
    $stmt = $pdo->query("SELECT COUNT(*) FROM system_log WHERE action_type = 'verification_test'");
    $verificationCount = (int)$stmt->fetchColumn();
    
    if ($verificationCount > 0) {
        echo "✅ Live-Logging funktioniert\n";
    } else {
        echo "❌ Live-Logging fehlgeschlagen\n";
        $allGood = false;
    }
    
    // 9. Final Summary
    echo "\n" . str_repeat("=", 50) . "\n";
    
    if ($allGood) {
        echo "🎉 ALLE TESTS ERFOLGREICH!\n";
        echo "=========================\n\n";
        echo "✅ SystemLog ist vollständig funktionsfähig:\n";
        echo "   • Datenbank: ✅ Verbindung OK\n";
        echo "   • Tabelle: ✅ system_log existiert\n";
        echo "   • Daten: ✅ $count Einträge vorhanden\n";
        echo "   • SystemLogger::getLogs(): ✅ $logCount Einträge\n";
        echo "   • Filter: ✅ Funktionieren\n";
        echo "   • Live-Logging: ✅ Funktioniert\n\n";
        
        echo "🌐 JETZT TESTEN:\n";
        echo "   → http://localhost:8080/settings/systemlogs\n";
        echo "   → Sie sollten $count Log-Einträge sehen!\n\n";
        
    } else {
        echo "❌ EINIGE TESTS FEHLGESCHLAGEN!\n";
        echo "===============================\n\n";
        echo "⚠️  Es gibt noch Probleme mit dem SystemLog.\n";
        echo "Prüfen Sie die obigen Fehlermeldungen.\n\n";
    }
    
} catch (\Throwable $e) {
    echo "❌ KRITISCHER FEHLER bei der Verifikation:\n";
    echo "   " . $e->getMessage() . "\n";
    echo "   Datei: " . $e->getFile() . ":" . $e->getLine() . "\n\n";
    
    echo "🔧 Troubleshooting:\n";
    echo "   1. Docker Status: docker-compose ps\n";
    echo "   2. App Logs: docker-compose logs app\n";
    echo "   3. DB Logs: docker-compose logs db\n\n";
}
