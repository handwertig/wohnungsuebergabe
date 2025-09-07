<?php
/**
 * Finaler Test - ohne Output vor Headers
 */

declare(strict_types=1);

// Output buffering starten um Headers zu schützen
ob_start();

require __DIR__ . '/../vendor/autoload.php';
if (is_file(__DIR__ . '/../.env')) {
    Dotenv\Dotenv::createImmutable(dirname(__DIR__))->load();
}

try {
    $pdo = App\Database::pdo();
    $protocolId = '82cc7de7-7d1e-11f0-89a6-822b82242c5d';
    
    // Session & Auth vorbereiten
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_start();
    }
    $_SESSION['user'] = [
        'id' => 'test-user-final',
        'email' => 'final@test.com',
        'name' => 'Final Test User'
    ];
    
    // CSRF Token generieren
    $token = App\Csrf::generateToken();
    
    // POST Data vorbereiten
    $_POST = [
        '_csrf_token' => $token,
        'id' => $protocolId,
        'type' => 'auszug',
        'tenant_name' => 'FINAL TEST ' . date('H:i:s'),
        'address' => [
            'city' => 'Test Stadt',
            'street' => 'Test Straße',
            'house_no' => '123'
        ],
        'meta' => [
            'notes' => 'Final Test vom ' . date('Y-m-d H:i:s')
        ]
    ];
    $_SERVER['REQUEST_METHOD'] = 'POST';
    
    // Controller aufrufen
    $controller = new App\Controllers\ProtocolsController();
    
    // Buffer leeren bevor Save aufgerufen wird
    ob_clean();
    
    // Save aufrufen - sollte jetzt redirecten können
    $controller->save();
    
} catch (Throwable $e) {
    // Buffer leeren bei Fehler
    ob_clean();
    
    // JSON Response für AJAX
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
        'file' => $e->getFile(),
        'line' => $e->getLine()
    ]);
}

// Falls wir hier ankommen, war der Save erfolgreich
// aber kein Redirect erfolgt (sollte nicht passieren)
ob_end_clean();
echo json_encode(['success' => true, 'message' => 'Save completed but no redirect']);
?>