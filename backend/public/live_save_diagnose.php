<?php
/**
 * Live-Diagnose der Save-Operation
 */

declare(strict_types=1);

// Clean start - kein Output vor diesem Punkt
if (ob_get_level()) {
    ob_end_clean();
}

require __DIR__ . '/../vendor/autoload.php';
if (is_file(__DIR__ . '/../.env')) {
    Dotenv\Dotenv::createImmutable(dirname(__DIR__))->load();
}

// JSON Response Header setzen
header('Content-Type: application/json');
header('Cache-Control: no-cache');

try {
    $pdo = App\Database::pdo();
    $protocolId = '82cc7de7-7d1e-11f0-89a6-822b82242c5d';
    
    $response = [
        'timestamp' => date('Y-m-d H:i:s'),
        'method' => $_SERVER['REQUEST_METHOD'] ?? 'GET',
        'post_data' => $_POST,
        'session_active' => session_status() === PHP_SESSION_ACTIVE,
        'headers_sent' => headers_sent(),
        'protocol_exists' => false,
        'current_tenant' => null,
        'save_attempted' => false,
        'save_result' => null,
        'errors' => []
    ];
    
    // 1. Protokoll prüfen
    $stmt = $pdo->prepare("SELECT tenant_name, updated_at FROM protocols WHERE id = ?");
    $stmt->execute([$protocolId]);
    $protocol = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($protocol) {
        $response['protocol_exists'] = true;
        $response['current_tenant'] = $protocol['tenant_name'];
        $response['last_updated'] = $protocol['updated_at'];
    }
    
    // 2. Wenn POST Request - Save versuchen
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $response['save_attempted'] = true;
        
        // Session für Auth vorbereiten
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }
        $_SESSION['user'] = [
            'id' => 'diagnose-user',
            'email' => 'diagnose@test.com',
            'name' => 'Diagnose User'
        ];
        
        try {
            // Controller instanziieren
            $controller = new App\Controllers\ProtocolsController();
            
            // Save versuchen - mit Output Capturing
            ob_start();
            $controller->save();
            $output = ob_get_clean();
            
            $response['save_result'] = 'completed';
            $response['output'] = $output;
            
        } catch (Throwable $e) {
            if (ob_get_level()) {
                ob_end_clean();
            }
            
            $response['save_result'] = 'error';
            $response['error_message'] = $e->getMessage();
            $response['error_file'] = $e->getFile();
            $response['error_line'] = $e->getLine();
            
            if (strpos($e->getMessage(), 'headers already sent') !== false) {
                $response['error_type'] = 'headers_already_sent';
            } elseif (strpos($e->getMessage(), 'Cannot modify header') !== false) {
                $response['error_type'] = 'cannot_modify_header';
            } else {
                $response['error_type'] = 'other';
            }
        }
        
        // Nach Save - Protokoll erneut prüfen
        $stmt = $pdo->prepare("SELECT tenant_name, updated_at FROM protocols WHERE id = ?");
        $stmt->execute([$protocolId]);
        $updatedProtocol = $stmt->fetch(PDO::FETCH_ASSOC);
        
        $response['after_save'] = [
            'tenant_name' => $updatedProtocol['tenant_name'],
            'updated_at' => $updatedProtocol['updated_at'],
            'changed' => $updatedProtocol['tenant_name'] !== $protocol['tenant_name']
        ];
    }
    
    echo json_encode($response, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    
} catch (Throwable $e) {
    $errorResponse = [
        'timestamp' => date('Y-m-d H:i:s'),
        'critical_error' => true,
        'message' => $e->getMessage(),
        'file' => $e->getFile(),
        'line' => $e->getLine()
    ];
    
    echo json_encode($errorResponse, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
}
?>