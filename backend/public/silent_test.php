<?php
/**
 * Silent Test - Echtes Browser-Verhalten ohne Output
 */

declare(strict_types=1);

// Verhindere jeglichen Output
ob_start();

require __DIR__ . '/../vendor/autoload.php';
if (is_file(__DIR__ . '/../.env')) {
    Dotenv\Dotenv::createImmutable(dirname(__DIR__))->load();
}

try {
    $pdo = App\Database::pdo();
    $protocolId = '82cc7de7-7d1e-11f0-89a6-822b82242c5d';
    
    // Session starten
    session_start();
    $_SESSION['user'] = [
        'id' => 'silent-test-user',
        'email' => 'silent@test.com',
        'name' => 'Silent Test User'
    ];
    
    // Vorher-Zustand
    $stmt = $pdo->prepare("SELECT tenant_name, updated_at FROM protocols WHERE id = ?");
    $stmt->execute([$protocolId]);
    $before = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$before) {
        ob_end_clean();
        http_response_code(404);
        echo json_encode(['error' => 'Protocol not found']);
        exit;
    }
    
    // POST-Daten setzen
    $newTenantName = 'SILENT TEST ' . date('H:i:s');
    $_POST = [
        'id' => $protocolId,
        'type' => 'auszug',
        'tenant_name' => $newTenantName,
        'address' => [
            'city' => 'Silent Stadt',
            'street' => 'Silent Straße',
            'house_no' => '123',
            'unit_label' => 'Silent Einheit'
        ],
        'meta' => [
            'notes' => 'Silent Test vom ' . date('Y-m-d H:i:s')
        ]
    ];
    $_SERVER['REQUEST_METHOD'] = 'POST';
    
    // Buffer leeren für sauberen Start
    ob_end_clean();
    
    // Controller aufrufen
    $controller = new App\Controllers\ProtocolsController();
    
    // Save ausführen - soll jetzt funktionieren
    $controller->save();
    
    // Falls wir hier ankommen, ist etwas schiefgelaufen
    // (normalerweise sollte exit/redirect vorher erfolgen)
    
} catch (Throwable $e) {
    // Sauberer Error-Response
    if (ob_get_level()) {
        ob_end_clean();
    }
    
    header('Content-Type: application/json');
    http_response_code(500);
    echo json_encode([
        'error' => true,
        'message' => $e->getMessage(),
        'file' => basename($e->getFile()),
        'line' => $e->getLine(),
        'success' => false
    ]);
}
?>