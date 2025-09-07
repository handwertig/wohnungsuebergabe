<?php
/**
 * REQUEST FLOW TRACER
 * Verfolgt jeden Schritt des Save-Requests
 */

declare(strict_types=1);

// Log File für Request Tracing
$logFile = __DIR__ . '/request_trace.log';

function logTrace($message) {
    global $logFile;
    $timestamp = date('Y-m-d H:i:s');
    $line = "[{$timestamp}] {$message}\n";
    file_put_contents($logFile, $line, FILE_APPEND | LOCK_EX);
    error_log("TRACE: {$message}");
}

logTrace("=== REQUEST TRACE START ===");
logTrace("Method: " . ($_SERVER['REQUEST_METHOD'] ?? 'UNKNOWN'));
logTrace("URI: " . ($_SERVER['REQUEST_URI'] ?? 'UNKNOWN'));
logTrace("Script: " . ($_SERVER['SCRIPT_NAME'] ?? 'UNKNOWN'));
logTrace("POST data count: " . count($_POST));
logTrace("Protocol ID from POST: " . ($_POST['id'] ?? 'NOT_SET'));
logTrace("Tenant name from POST: " . ($_POST['tenant_name'] ?? 'NOT_SET'));

// Bootstrap
require __DIR__ . '/../vendor/autoload.php';
if (is_file(__DIR__ . '/../.env')) {
    Dotenv\Dotenv::createImmutable(dirname(__DIR__))->load();
}

logTrace("Bootstrap completed");

// Nur POST erlauben
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    logTrace("NOT POST - method is: " . ($_SERVER['REQUEST_METHOD'] ?? 'UNKNOWN'));
    http_response_code(405);
    exit('Method Not Allowed');
}

logTrace("POST method confirmed");

try {
    // Session
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_start();
        logTrace("Session started");
    } else {
        logTrace("Session already active");
    }
    
    // Auth
    if (!isset($_SESSION['user'])) {
        $_SESSION['user'] = [
            'id' => 'trace-user',
            'email' => 'trace@test.com',
            'name' => 'Trace User'
        ];
        logTrace("Emergency user created");
    } else {
        logTrace("User already in session: " . ($_SESSION['user']['email'] ?? 'unknown'));
    }
    
    // Database Connection
    logTrace("Attempting database connection...");
    $pdo = App\Database::pdo();
    logTrace("Database connection successful");
    
    // Validierung
    $protocolId = $_POST['id'] ?? '';
    if (empty($protocolId)) {
        logTrace("ERROR: Protocol ID is empty");
        throw new Exception('Protokoll-ID fehlt');
    }
    
    logTrace("Protocol ID validated: {$protocolId}");
    
    // Protokoll laden
    logTrace("Loading protocol from database...");
    $stmt = $pdo->prepare("SELECT id, tenant_name, updated_at FROM protocols WHERE id = ? AND deleted_at IS NULL");
    $stmt->execute([$protocolId]);
    $protocol = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$protocol) {
        logTrace("ERROR: Protocol not found in database");
        throw new Exception('Protokoll nicht gefunden');
    }
    
    logTrace("Protocol found - Current tenant: " . $protocol['tenant_name']);
    logTrace("Protocol last updated: " . $protocol['updated_at']);
    
    // Update-Daten sammeln
    $newTenantName = (string)($_POST['tenant_name'] ?? $protocol['tenant_name']);
    $newType = (string)($_POST['type'] ?? 'auszug');
    $updateTime = date('Y-m-d H:i:s');
    
    logTrace("New tenant name: {$newTenantName}");
    logTrace("New type: {$newType}");
    logTrace("Update time: {$updateTime}");
    
    // Änderungen prüfen
    $hasChanges = ($protocol['tenant_name'] !== $newTenantName);
    logTrace("Has changes: " . ($hasChanges ? 'YES' : 'NO'));
    
    if ($hasChanges) {
        logTrace("Change detected: '{$protocol['tenant_name']}' -> '{$newTenantName}'");
    }
    
    // Database Update durchführen
    logTrace("Preparing database update...");
    
    $payload = [
        'address' => $_POST['address'] ?? [],
        'rooms' => $_POST['rooms'] ?? [],
        'meters' => $_POST['meters'] ?? [],
        'keys' => $_POST['keys'] ?? [],
        'meta' => $_POST['meta'] ?? [],
        'timestamp' => $updateTime
    ];
    
    $stmt = $pdo->prepare("
        UPDATE protocols 
        SET tenant_name = ?, type = ?, payload = ?, updated_at = ? 
        WHERE id = ?
    ");
    
    logTrace("Executing update query...");
    $success = $stmt->execute([
        $newTenantName,
        $newType,
        json_encode($payload, JSON_UNESCAPED_UNICODE),
        $updateTime,
        $protocolId
    ]);
    
    if (!$success) {
        logTrace("ERROR: Database update failed");
        $errorInfo = $stmt->errorInfo();
        logTrace("SQL Error: " . json_encode($errorInfo));
        throw new Exception('Database Update fehlgeschlagen');
    }
    
    logTrace("Database update successful");
    
    // Verifikation
    logTrace("Verifying update...");
    $stmt = $pdo->prepare("SELECT tenant_name, updated_at FROM protocols WHERE id = ?");
    $stmt->execute([$protocolId]);
    $verification = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($verification) {
        logTrace("Verification successful:");
        logTrace("  - New tenant name: " . $verification['tenant_name']);
        logTrace("  - New updated_at: " . $verification['updated_at']);
        
        if ($verification['tenant_name'] === $newTenantName) {
            logTrace("SUCCESS: Update confirmed in database");
        } else {
            logTrace("ERROR: Update not reflected - expected '{$newTenantName}', got '{$verification['tenant_name']}'");
        }
    } else {
        logTrace("ERROR: Could not verify update - protocol not found");
    }
    
    // Flash message
    $_SESSION['_flash'][] = [
        'type' => 'success', 
        'message' => 'TRACE SUCCESS: ' . $newTenantName . ' at ' . $updateTime
    ];
    logTrace("Flash message added");
    
    // Event hinzufügen
    try {
        $stmt = $pdo->prepare("
            INSERT INTO protocol_events (id, protocol_id, type, message, created_at) 
            VALUES (UUID(), ?, 'updated', ?, NOW())
        ");
        $stmt->execute([$protocolId, 'TRACE SAVE: ' . $newTenantName]);
        logTrace("Event added to protocol_events");
    } catch (Exception $e) {
        logTrace("Event creation failed: " . $e->getMessage());
    }
    
    logTrace("=== REQUEST TRACE SUCCESS ===");
    
    // Response
    header('Content-Type: application/json');
    echo json_encode([
        'success' => true,
        'message' => 'Save completed successfully',
        'protocol_id' => $protocolId,
        'new_tenant_name' => $newTenantName,
        'update_time' => $updateTime,
        'verification' => $verification,
        'has_changes' => $hasChanges,
        'timestamp' => date('Y-m-d H:i:s')
    ], JSON_PRETTY_PRINT);
    
} catch (Throwable $e) {
    logTrace("=== REQUEST TRACE ERROR ===");
    logTrace("Exception: " . $e->getMessage());
    logTrace("File: " . $e->getFile() . ":" . $e->getLine());
    logTrace("Trace: " . $e->getTraceAsString());
    
    header('Content-Type: application/json');
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
        'file' => $e->getFile(),
        'line' => $e->getLine(),
        'timestamp' => date('Y-m-d H:i:s')
    ], JSON_PRETTY_PRINT);
}
?>