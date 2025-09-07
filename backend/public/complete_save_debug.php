<?php
/**
 * Vollständiger Debug der Save-Kette
 * Testet jeden einzelnen Schritt des Save-Prozesses
 */

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';
if (is_file(__DIR__ . '/../.env')) {
    Dotenv\Dotenv::createImmutable(dirname(__DIR__))->load();
}

echo "=== VOLLSTÄNDIGER SAVE-DEBUG ===\n\n";

try {
    // 1. Database Connection Test
    echo "1. 🔌 Database Connection...\n";
    $pdo = App\Database::pdo();
    echo "   ✅ PDO Connection erfolgreich\n";
    
    // 2. Protocol Existence Test
    echo "\n2. 📋 Protocol Existence...\n";
    $protocolId = '82cc7de7-7d1e-11f0-89a6-822b82242c5d';
    $stmt = $pdo->prepare("SELECT * FROM protocols WHERE id = ? AND deleted_at IS NULL");
    $stmt->execute([$protocolId]);
    $protocol = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($protocol) {
        echo "   ✅ Protokoll gefunden: {$protocol['tenant_name']}\n";
        echo "   📅 Erstellt: {$protocol['created_at']}\n";
        echo "   📅 Updated: {$protocol['updated_at']}\n";
    } else {
        echo "   ❌ Protokoll nicht gefunden!\n";
        exit;
    }
    
    // 3. Auth System Test
    echo "\n3. 🔐 Auth System...\n";
    session_start();
    $_SESSION['user'] = [
        'id' => 'debug-user-123',
        'email' => 'debug@test.com',
        'name' => 'Debug User'
    ];
    
    try {
        App\Auth::requireAuth(); // Should not throw
        echo "   ✅ Auth System funktional\n";
        
        $user = App\Auth::user();
        echo "   👤 User: " . ($user['email'] ?? 'unknown') . "\n";
    } catch (Throwable $e) {
        echo "   ❌ Auth Fehler: " . $e->getMessage() . "\n";
    }
    
    // 4. CSRF System Test
    echo "\n4. 🛡️ CSRF System...\n";
    try {
        $token = App\Csrf::generateToken();
        echo "   ✅ CSRF Token generiert: " . substr($token, 0, 10) . "...\n";
        
        $isValid = App\Csrf::validateToken($token);
        echo "   " . ($isValid ? "✅" : "❌") . " CSRF Validation: " . ($isValid ? "OK" : "FAILED") . "\n";
    } catch (Throwable $e) {
        echo "   ❌ CSRF Fehler: " . $e->getMessage() . "\n";
    }
    
    // 5. POST Simulation
    echo "\n5. 📤 POST Data Simulation...\n";
    $_POST = [
        '_csrf_token' => $token ?? '',
        'id' => $protocolId,
        'type' => 'auszug',
        'tenant_name' => $protocol['tenant_name'] . ' [DEBUG ' . date('H:i:s') . ']',
        'address' => [
            'city' => 'Debug Stadt',
            'street' => 'Debug Straße',
            'house_no' => '123'
        ],
        'meta' => [
            'notes' => 'Debug Test vom ' . date('Y-m-d H:i:s')
        ]
    ];
    $_SERVER['REQUEST_METHOD'] = 'POST';
    
    echo "   ✅ POST Data vorbereitet\n";
    echo "   📋 Tenant: {$_POST['tenant_name']}\n";
    
    // 6. Controller Instanziierung
    echo "\n6. 🎮 Controller Test...\n";
    try {
        $controller = new App\Controllers\ProtocolsController();
        echo "   ✅ Controller instanziiert\n";
    } catch (Throwable $e) {
        echo "   ❌ Controller Fehler: " . $e->getMessage() . "\n";
        exit;
    }
    
    // 7. Flash System Test
    echo "\n7. 💬 Flash System...\n";
    try {
        App\Flash::add('test', 'Test-Nachricht');
        $messages = App\Flash::pull();
        echo "   ✅ Flash System funktional (" . count($messages) . " messages)\n";
    } catch (Throwable $e) {
        echo "   ❌ Flash Fehler: " . $e->getMessage() . "\n";
    }
    
    // 8. Save Method Test (aber mit Output Buffering)
    echo "\n8. 💾 Save Method Test...\n";
    
    // Capture any output or redirects
    ob_start();
    
    $saveError = null;
    $saveSuccess = false;
    
    try {
        // Method call that might redirect
        $controller->save();
        $saveSuccess = true;
    } catch (Throwable $e) {
        $saveError = $e;
    }
    
    $output = ob_get_clean();
    
    if ($saveError) {
        if (strpos($saveError->getMessage(), 'headers already sent') !== false) {
            echo "   ⚠️ Save versuchte zu redirecten (das ist normal)\n";
            $saveSuccess = true;
        } else {
            echo "   ❌ Save Fehler: " . $saveError->getMessage() . "\n";
            echo "   📍 Datei: " . $saveError->getFile() . ":" . $saveError->getLine() . "\n";
        }
    } else if ($saveSuccess) {
        echo "   ✅ Save Method erfolgreich ausgeführt\n";
    }
    
    if (!empty($output)) {
        echo "   📤 Output: " . substr($output, 0, 100) . "...\n";
    }
    
    // 9. Database Verification
    echo "\n9. ✅ Database Verification...\n";
    
    $stmt = $pdo->prepare("SELECT tenant_name, updated_at FROM protocols WHERE id = ?");
    $stmt->execute([$protocolId]);
    $updatedProtocol = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($updatedProtocol['tenant_name'] !== $protocol['tenant_name']) {
        echo "   ✅ Protokoll wurde aktualisiert!\n";
        echo "   📝 Vorher: {$protocol['tenant_name']}\n";
        echo "   📝 Nachher: {$updatedProtocol['tenant_name']}\n";
        echo "   📅 Updated: {$updatedProtocol['updated_at']}\n";
    } else {
        echo "   ❌ Protokoll wurde NICHT aktualisiert\n";
        echo "   📝 Unverändert: {$updatedProtocol['tenant_name']}\n";
    }
    
    // 10. Events Check
    echo "\n10. 📝 Events Check...\n";
    
    try {
        $stmt = $pdo->prepare("
            SELECT type, message, created_by, created_at 
            FROM protocol_events 
            WHERE protocol_id = ? 
            ORDER BY created_at DESC 
            LIMIT 3
        ");
        $stmt->execute([$protocolId]);
        $events = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo "   📊 " . count($events) . " Events gefunden:\n";
        foreach ($events as $event) {
            echo "     - [{$event['created_at']}] {$event['type']}: {$event['message']}\n";
        }
    } catch (PDOException $e) {
        echo "   ❌ Events Fehler: " . $e->getMessage() . "\n";
    }
    
    // 11. System Log Check
    echo "\n11. 📊 System Log Check...\n";
    
    try {
        $stmt = $pdo->prepare("
            SELECT action_type, action_description, created_at 
            FROM system_log 
            WHERE resource_id = ? 
            ORDER BY created_at DESC 
            LIMIT 3
        ");
        $stmt->execute([$protocolId]);
        $logs = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo "   📊 " . count($logs) . " System-Logs gefunden:\n";
        foreach ($logs as $log) {
            echo "     - [{$log['created_at']}] {$log['action_type']}: {$log['action_description']}\n";
        }
    } catch (PDOException $e) {
        echo "   ❌ System-Log Fehler: " . $e->getMessage() . "\n";
    }
    
    echo "\n" . str_repeat("=", 60) . "\n";
    echo "🏁 VOLLSTÄNDIGER DEBUG ABGESCHLOSSEN\n";
    echo str_repeat("=", 60) . "\n\n";
    
    // Summary
    if ($updatedProtocol['tenant_name'] !== $protocol['tenant_name']) {
        echo "🎉 ERFOLG: Das Speichern funktioniert!\n";
        echo "   → Das Problem liegt wahrscheinlich im Browser/Frontend\n";
        echo "   → Prüfe die Browser-Konsole auf JavaScript-Fehler\n";
        echo "   → Prüfe ob das Formular korrekt submitted wird\n";
    } else {
        echo "❌ PROBLEM: Das Speichern funktioniert nicht\n";
        echo "   → Prüfe die oben gezeigten Fehler\n";
        echo "   → Das Problem liegt im Backend/Controller\n";
    }
    
} catch (Throwable $e) {
    echo "💥 KRITISCHER FEHLER: " . $e->getMessage() . "\n";
    echo "   Datei: " . $e->getFile() . ":" . $e->getLine() . "\n";
    echo "\n📋 Stack Trace:\n" . $e->getTraceAsString() . "\n";
}
?>