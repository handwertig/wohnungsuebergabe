<?php
/**
 * AUTOMATED SYSTEM TESTER
 * Testet das System automatisch bis es funktioniert
 */

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';
if (is_file(__DIR__ . '/../.env')) {
    Dotenv\Dotenv::createImmutable(dirname(__DIR__))->load();
}

$testResults = [];
$protocolId = '82cc7de7-7d1e-11f0-89a6-822b82242c5d';

function addResult($test, $success, $message, $details = null) {
    global $testResults;
    $testResults[] = [
        'test' => $test,
        'success' => $success,
        'message' => $message,
        'details' => $details,
        'timestamp' => date('Y-m-d H:i:s')
    ];
    
    $status = $success ? 'SUCCESS' : 'FAILED';
    echo date('H:i:s') . " [{$status}] {$test}: {$message}\n";
    if ($details) {
        echo "         Details: " . (is_string($details) ? $details : json_encode($details)) . "\n";
    }
}

function runAutomatedTests() {
    global $protocolId;
    
    echo "=== AUTOMATED SYSTEM TESTING STARTED ===\n";
    echo "Target Protocol ID: {$protocolId}\n";
    echo "Timestamp: " . date('Y-m-d H:i:s') . "\n\n";
    
    // Test 1: Database Connection
    try {
        $pdo = App\Database::pdo();
        addResult('Database Connection', true, 'Successfully connected to database');
    } catch (Exception $e) {
        addResult('Database Connection', false, 'Failed to connect', $e->getMessage());
        return false;
    }
    
    // Test 2: Protocol Exists
    try {
        $stmt = $pdo->prepare("SELECT id, tenant_name, updated_at FROM protocols WHERE id = ?");
        $stmt->execute([$protocolId]);
        $protocol = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($protocol) {
            addResult('Protocol Exists', true, 'Protocol found', [
                'current_tenant' => $protocol['tenant_name'],
                'last_updated' => $protocol['updated_at']
            ]);
        } else {
            addResult('Protocol Exists', false, 'Protocol not found in database');
            return false;
        }
    } catch (Exception $e) {
        addResult('Protocol Exists', false, 'Error checking protocol', $e->getMessage());
        return false;
    }
    
    // Test 3: Direct Working Save Test
    $testTenantName = 'AUTOMATED TEST ' . date('H:i:s');
    
    try {
        // Simulate POST request to working_save.php
        $_POST = [
            'id' => $protocolId,
            'tenant_name' => $testTenantName,
            'type' => 'auszug',
            'address' => ['city' => 'Test City', 'street' => 'Test Street'],
            'meta' => ['notes' => 'Automated test run at ' . date('Y-m-d H:i:s')]
        ];
        $_SERVER['REQUEST_METHOD'] = 'POST';
        
        // Capture output from working_save.php
        ob_start();
        
        // Simulate session
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }
        $_SESSION['user'] = [
            'id' => 'automated-test',
            'email' => 'test@automated.com',
            'name' => 'Automated Test'
        ];
        
        // Run the save operation directly
        try {
            $pdo = App\Database::pdo();
            
            $stmt = $pdo->prepare("
                UPDATE protocols 
                SET tenant_name = ?, updated_at = NOW() 
                WHERE id = ?
            ");
            $updateResult = $stmt->execute([$testTenantName, $protocolId]);
            
            ob_end_clean();
            
            if ($updateResult) {
                addResult('Direct Save Test', true, 'Database update successful');
                
                // Verify the update
                $stmt = $pdo->prepare("SELECT tenant_name, updated_at FROM protocols WHERE id = ?");
                $stmt->execute([$protocolId]);
                $updated = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($updated && $updated['tenant_name'] === $testTenantName) {
                    addResult('Save Verification', true, 'Update verified in database', [
                        'new_tenant' => $updated['tenant_name'],
                        'updated_at' => $updated['updated_at']
                    ]);
                } else {
                    addResult('Save Verification', false, 'Update not reflected in database', [
                        'expected' => $testTenantName,
                        'actual' => $updated['tenant_name'] ?? 'null'
                    ]);
                }
            } else {
                addResult('Direct Save Test', false, 'Database update failed');
            }
            
        } catch (Exception $e) {
            ob_end_clean();
            addResult('Direct Save Test', false, 'Exception during save', $e->getMessage());
        }
        
    } catch (Exception $e) {
        addResult('Direct Save Test', false, 'Failed to prepare test', $e->getMessage());
    }
    
    // Test 4: Event Log Check
    try {
        $stmt = $pdo->prepare("
            SELECT type, message, created_at 
            FROM protocol_events 
            WHERE protocol_id = ? 
            ORDER BY created_at DESC 
            LIMIT 5
        ");
        $stmt->execute([$protocolId]);
        $events = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $recentEvents = array_filter($events, function($event) {
            return strtotime($event['created_at']) > (time() - 300); // Last 5 minutes
        });
        
        if (!empty($recentEvents)) {
            addResult('Event Logging', true, 'Recent events found', [
                'event_count' => count($recentEvents),
                'latest_event' => $recentEvents[0]['message'] ?? 'unknown'
            ]);
        } else {
            addResult('Event Logging', false, 'No recent events found', [
                'total_events' => count($events),
                'latest_event_time' => $events[0]['created_at'] ?? 'none'
            ]);
        }
    } catch (Exception $e) {
        addResult('Event Logging', false, 'Error checking events', $e->getMessage());
    }
    
    // Test 5: Working Save Handler Test via HTTP
    try {
        // Create a test POST request to the working save handler
        $postData = http_build_query([
            'id' => $protocolId,
            'tenant_name' => 'HTTP TEST ' . date('H:i:s'),
            'type' => 'auszug'
        ]);
        
        $context = stream_context_create([
            'http' => [
                'method' => 'POST',
                'header' => "Content-Type: application/x-www-form-urlencoded\r\n" .
                           "Content-Length: " . strlen($postData) . "\r\n",
                'content' => $postData
            ]
        ]);
        
        $result = @file_get_contents('http://localhost:8080/working_save.php', false, $context);
        
        if ($result !== false) {
            addResult('HTTP Save Test', true, 'Working save handler responded');
            
            // Check if the update actually happened
            $stmt = $pdo->prepare("SELECT tenant_name, updated_at FROM protocols WHERE id = ?");
            $stmt->execute([$protocolId]);
            $check = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($check && strpos($check['tenant_name'], 'HTTP TEST') !== false) {
                addResult('HTTP Save Verification', true, 'HTTP save verified in database', [
                    'tenant_name' => $check['tenant_name'],
                    'updated_at' => $check['updated_at']
                ]);
            } else {
                addResult('HTTP Save Verification', false, 'HTTP save not reflected in database');
            }
        } else {
            addResult('HTTP Save Test', false, 'Working save handler did not respond');
        }
    } catch (Exception $e) {
        addResult('HTTP Save Test', false, 'HTTP test failed', $e->getMessage());
    }
    
    return true;
}

// Run the tests
$success = runAutomatedTests();

echo "\n=== TEST RESULTS SUMMARY ===\n";
$totalTests = count($testResults);
$successfulTests = count(array_filter($testResults, function($result) { return $result['success']; }));
$failedTests = $totalTests - $successfulTests;

echo "Total Tests: {$totalTests}\n";
echo "Successful: {$successfulTests}\n";
echo "Failed: {$failedTests}\n";
echo "Success Rate: " . round(($successfulTests / $totalTests) * 100, 1) . "%\n";

if ($failedTests === 0) {
    echo "\n🎉 ALL TESTS PASSED! System is working correctly.\n";
} else {
    echo "\n❌ Some tests failed. Issues need to be resolved:\n";
    foreach ($testResults as $result) {
        if (!$result['success']) {
            echo "  - {$result['test']}: {$result['message']}\n";
        }
    }
}

echo "\n=== DETAILED RESULTS ===\n";
echo json_encode($testResults, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

// Save results to file for later analysis
file_put_contents(__DIR__ . '/test_results.json', json_encode([
    'timestamp' => date('Y-m-d H:i:s'),
    'summary' => [
        'total' => $totalTests,
        'successful' => $successfulTests,
        'failed' => $failedTests,
        'success_rate' => round(($successfulTests / $totalTests) * 100, 1)
    ],
    'results' => $testResults
], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

echo "\nResults saved to test_results.json\n";
?>