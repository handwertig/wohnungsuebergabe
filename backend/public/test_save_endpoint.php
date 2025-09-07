<?php
/**
 * Minimaler Test-Endpoint um zu prüfen ob Save-Route funktioniert
 */

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';
if (is_file(__DIR__ . '/../.env')) {
    Dotenv\Dotenv::createImmutable(dirname(__DIR__))->load();
}

// Log alle eingehenden Requests
$logData = [
    'timestamp' => date('Y-m-d H:i:s'),
    'method' => $_SERVER['REQUEST_METHOD'] ?? 'GET',
    'uri' => $_SERVER['REQUEST_URI'] ?? '/',
    'post_data' => $_POST,
    'get_data' => $_GET,
    'headers' => getallheaders() ?: [],
    'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown'
];

error_log('SAVE ENDPOINT TEST: ' . json_encode($logData, JSON_UNESCAPED_UNICODE));

// Session starten für Flash Messages
session_start();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    echo "✅ POST-Request empfangen!\n";
    echo "📦 POST-Daten: " . json_encode($_POST, JSON_UNESCAPED_UNICODE) . "\n";
    
    // Simuliere erfolgreiche Speicherung
    $_SESSION['_flash'][] = ['type' => 'success', 'message' => 'TEST: Save-Endpoint wurde erreicht!'];
    
    // Redirect zurück zur Edit-Seite
    $protocolId = $_POST['id'] ?? '82cc7de7-7d1e-11f0-89a6-822b82242c5d';
    header('Location: /protocols/edit?id=' . $protocolId);
    exit;
} else {
    echo "❌ Nur GET-Request - erwarte POST\n";
    echo "🔗 Test-Link: \n";
    echo "curl -X POST http://localhost:8080/test_save_endpoint.php -d 'id=82cc7de7-7d1e-11f0-89a6-822b82242c5d&tenant_name=TEST'\n";
}
?>