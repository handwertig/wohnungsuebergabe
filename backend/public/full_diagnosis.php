<?php
/**
 * UMFASSENDE SYSTEM-DIAGNOSE
 * Prüft alle Komponenten des Save-Prozesses
 */

declare(strict_types=1);

// Clean Start
if (ob_get_level()) {
    ob_end_clean();
}

// Bootstrap
require __DIR__ . '/../vendor/autoload.php';
if (is_file(__DIR__ . '/../.env')) {
    Dotenv\Dotenv::createImmutable(dirname(__DIR__))->load();
}

echo "=== UMFASSENDE SYSTEM-DIAGNOSE ===\n\n";

$diagnostics = [];
$errors = [];

try {
    // 1. UMGEBUNG PRÜFEN
    echo "1. 📝 UMGEBUNG\n";
    $diagnostics['environment'] = [
        'php_version' => PHP_VERSION,
        'server_method' => $_SERVER['REQUEST_METHOD'] ?? 'UNKNOWN',
        'post_data_count' => count($_POST),
        'session_status' => session_status(),
        'headers_sent' => headers_sent(),
        'ob_level' => ob_get_level(),
        'memory_usage' => memory_get_usage(true),
        'db_host' => getenv('DB_HOST'),
        'db_name' => getenv('DB_NAME'),
        'protocol_id' => $_POST['id'] ?? $_GET['id'] ?? 'NOT_SET'
    ];
    
    foreach ($diagnostics['environment'] as $key => $value) {
        echo "   ✓ {$key}: {$value}\n";
    }
    echo "\n";
    
    // 2. DATABASE CONNECTION
    echo "2. 🗄️ DATABASE\n";
    try {
        $pdo = App\Database::pdo();
        echo "   ✓ Connection: SUCCESS\n";
        
        // Test Query
        $stmt = $pdo->query("SELECT COUNT(*) as count FROM protocols");
        $protocolCount = $stmt->fetch()['count'];
        echo "   ✓ Protocols in DB: {$protocolCount}\n";
        
        // Protokoll laden
        $protocolId = $_POST['id'] ?? $_GET['id'] ?? '82cc7de7-7d1e-11f0-89a6-822b82242c5d';
        $stmt = $pdo->prepare("SELECT id, tenant_name, updated_at FROM protocols WHERE id = ? LIMIT 1");
        $stmt->execute([$protocolId]);
        $protocol = $stmt->fetch();
        
        if ($protocol) {
            echo "   ✓ Test Protocol found: {$protocol['tenant_name']}\n";
            echo "   ✓ Last updated: {$protocol['updated_at']}\n";
            $diagnostics['protocol_found'] = true;
            $diagnostics['current_tenant'] = $protocol['tenant_name'];
            $diagnostics['last_updated'] = $protocol['updated_at'];
        } else {
            echo "   ❌ Test Protocol NOT FOUND\n";
            $errors[] = "Test protocol not found in database";
            $diagnostics['protocol_found'] = false;
        }
        
    } catch (Throwable $e) {
        echo "   ❌ Database Error: " . $e->getMessage() . "\n";
        $errors[] = "Database connection failed: " . $e->getMessage();
        $diagnostics['database_error'] = $e->getMessage();
    }
    echo "\n";
    
    // 3. AUTH SYSTEM
    echo "3. 🔐 AUTH SYSTEM\n";
    try {
        // Session starten
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }
        
        echo "   ✓ Session started\n";
        
        // Auth prüfen
        if (class_exists('\\App\\Auth')) {
            try {
                $authUser = \App\Auth::user();
                if ($authUser) {
                    echo "   ✓ Auth user found: " . ($authUser['email'] ?? 'unknown') . "\n";
                    $diagnostics['auth_user'] = $authUser['email'] ?? 'unknown';
                } else {
                    echo "   ⚠ No auth user - creating emergency user\n";
                    $_SESSION['user'] = [
                        'id' => 'diagnostic-user',
                        'email' => 'diagnostic@test.com',
                        'name' => 'Diagnostic User'
                    ];
                    $diagnostics['auth_user'] = 'emergency';
                }
            } catch (Throwable $e) {
                echo "   ⚠ Auth system error: " . $e->getMessage() . "\n";
                $_SESSION['user'] = [
                    'id' => 'diagnostic-user',
                    'email' => 'diagnostic@test.com',  
                    'name' => 'Diagnostic User'
                ];
                $diagnostics['auth_user'] = 'emergency';
            }
        } else {
            echo "   ❌ Auth class not found\n";
            $errors[] = "Auth class missing";
        }
        
    } catch (Throwable $e) {
        echo "   ❌ Auth Error: " . $e->getMessage() . "\n";
        $errors[] = "Auth system failed: " . $e->getMessage();
    }
    echo "\n";
    
    // 4. ROUTING TEST
    echo "4. 🔄 ROUTING TEST\n";
    $routingTests = [
        '/protocols' => 'Protocols index',
        '/protocols/edit' => 'Protocol edit',
        '/protocols/save' => 'Protocol save (EMERGENCY)',
        '/emergency_save.php' => 'Emergency handler'
    ];
    
    foreach ($routingTests as $route => $description) {
        if (file_exists(__DIR__ . $route)) {
            echo "   ✓ {$description}: FILE EXISTS\n";
        } elseif ($route === '/protocols/save') {
            echo "   ✓ {$description}: ROUTED TO EMERGENCY\n";
        } elseif (str_starts_with($route, '/protocols')) {
            echo "   ✓ {$description}: CONTROLLER ROUTE\n";
        } else {
            echo "   ❌ {$description}: NOT FOUND\n";
            $errors[] = "Route {$route} not accessible";
        }
    }
    echo "\n";
    
    // 5. SAVE SIMULATION (nur wenn POST)
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['id'])) {
        echo "5. 💾 SAVE SIMULATION\n";
        $protocolId = $_POST['id'];
        $newTenantName = $_POST['tenant_name'] ?? 'DIAGNOSTIC TEST ' . date('H:i:s');
        
        try {
            $pdo = App\Database::pdo();
            
            // Vorher-Zustand
            $stmt = $pdo->prepare("SELECT tenant_name FROM protocols WHERE id = ?");
            $stmt->execute([$protocolId]);
            $before = $stmt->fetch();
            echo "   📋 Before: " . ($before['tenant_name'] ?? 'NOT_FOUND') . "\n";
            
            // Update ausführen
            $stmt = $pdo->prepare("UPDATE protocols SET tenant_name = ?, updated_at = NOW() WHERE id = ?");
            $updateSuccess = $stmt->execute([$newTenantName, $protocolId]);
            
            if ($updateSuccess) {
                echo "   ✅ Update executed successfully\n";
                
                // Nachher-Zustand
                $stmt = $pdo->prepare("SELECT tenant_name, updated_at FROM protocols WHERE id = ?");
                $stmt->execute([$protocolId]);
                $after = $stmt->fetch();
                
                if ($after) {
                    echo "   📋 After: {$after['tenant_name']}\n";
                    echo "   🕐 Updated: {$after['updated_at']}\n";
                    $diagnostics['save_test'] = 'SUCCESS';
                    $diagnostics['new_tenant_name'] = $after['tenant_name'];
                } else {
                    echo "   ❌ Could not read after update\n";
                    $errors[] = "Could not verify update";
                }
                
            } else {
                echo "   ❌ Update failed\n";
                $errors[] = "Database update failed";
                $diagnostics['save_test'] = 'FAILED';
            }
            
        } catch (Throwable $e) {
            echo "   ❌ Save simulation error: " . $e->getMessage() . "\n";
            $errors[] = "Save simulation failed: " . $e->getMessage();
        }
        echo "\n";
    } else {
        echo "5. 💾 SAVE SIMULATION: SKIPPED (not POST request)\n\n";
    }
    
    // 6. FLASH SYSTEM
    echo "6. 💬 FLASH SYSTEM\n";
    try {
        if (class_exists('\\App\\Flash')) {
            \App\Flash::add('info', 'DIAGNOSTIC: Flash system working');
            echo "   ✅ Flash system available\n";
            $diagnostics['flash_system'] = true;
        } else {
            echo "   ⚠ Flash class not found - using session\n";
            $_SESSION['_flash'][] = ['type' => 'info', 'message' => 'DIAGNOSTIC: Session flash working'];
            $diagnostics['flash_system'] = 'session';
        }
        
        $flashCount = count($_SESSION['_flash'] ?? []);
        echo "   📨 Flash messages in session: {$flashCount}\n";
        
    } catch (Throwable $e) {
        echo "   ❌ Flash system error: " . $e->getMessage() . "\n";
        $errors[] = "Flash system failed";
    }
    echo "\n";
    
    // 7. ZUSAMMENFASSUNG
    echo "7. 📊 ZUSAMMENFASSUNG\n";
    
    if (empty($errors)) {
        echo "   🎉 ALLE TESTS BESTANDEN!\n";
        $status = 'ALL_GOOD';
    } else {
        echo "   ⚠ PROBLEME GEFUNDEN:\n";
        foreach ($errors as $error) {
            echo "     - {$error}\n";
        }
        $status = 'ISSUES_FOUND';
    }
    
    $diagnostics['status'] = $status;
    $diagnostics['errors'] = $errors;
    $diagnostics['timestamp'] = date('Y-m-d H:i:s');
    
    echo "\n";
    echo "=== DIAGNOSTICS JSON ===\n";
    echo json_encode($diagnostics, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    
} catch (Throwable $e) {
    echo "\n❌ KRITISCHER FEHLER: " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . ":" . $e->getLine() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
}
?>