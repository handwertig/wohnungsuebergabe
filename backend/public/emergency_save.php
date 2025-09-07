<?php
/**
 * EMERGENCY SAVE HANDLER - FINAL VERSION
 * Headers-Safe Implementation
 */

declare(strict_types=1);

// CRITICAL: Prevent ANY output before redirect
ob_start();

// Bootstrap
require __DIR__ . '/../vendor/autoload.php';
if (is_file(__DIR__ . '/../.env')) {
    Dotenv\Dotenv::createImmutable(dirname(__DIR__))->load();
}

// Nur POST erlauben
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    ob_end_clean();
    http_response_code(405);
    exit('Method Not Allowed');
}

try {
    // 1. Session
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_start();
    }
    
    // 2. Auth (vereinfacht)
    if (!isset($_SESSION['user'])) {
        $_SESSION['user'] = [
            'id' => 'emergency-user',
            'email' => 'emergency@system.com',
            'name' => 'Emergency User'
        ];
    }
    
    // 3. Database
    $pdo = App\Database::pdo();
    
    // 4. Validierung
    $protocolId = $_POST['id'] ?? '';
    if (empty($protocolId)) {
        throw new Exception('Protokoll-ID fehlt');
    }
    
    // Debug-Logging (only to error log, not output)
    error_log('EMERGENCY SAVE: Protocol ID: ' . $protocolId);
    error_log('EMERGENCY SAVE: Tenant Name: ' . ($_POST['tenant_name'] ?? 'not set'));
    
    // 5. Protokoll laden
    $stmt = $pdo->prepare("SELECT * FROM protocols WHERE id = ? AND deleted_at IS NULL");
    $stmt->execute([$protocolId]);
    $protocol = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$protocol) {
        throw new Exception('Protokoll nicht gefunden');
    }
    
    // 6. Update durchführen
    $newTenantName = (string)($_POST['tenant_name'] ?? $protocol['tenant_name']);
    $newType = (string)($_POST['type'] ?? $protocol['type']);
    $updateTime = date('Y-m-d H:i:s');
    
    // Payload
    $payload = [
        'address' => $_POST['address'] ?? [],
        'rooms' => $_POST['rooms'] ?? [],
        'meters' => $_POST['meters'] ?? [],
        'keys' => $_POST['keys'] ?? [],
        'meta' => $_POST['meta'] ?? [],
        'timestamp' => $updateTime
    ];
    
    // 7. Database Update
    $stmt = $pdo->prepare("
        UPDATE protocols 
        SET tenant_name = ?, type = ?, payload = ?, updated_at = ? 
        WHERE id = ?
    ");
    
    $success = $stmt->execute([
        $newTenantName,
        $newType,
        json_encode($payload, JSON_UNESCAPED_UNICODE),
        $updateTime,
        $protocolId
    ]);
    
    if (!$success) {
        throw new Exception('Database Update fehlgeschlagen');
    }
    
    // Debug: Verifikation (only to error log)
    $stmt = $pdo->prepare("SELECT tenant_name, updated_at FROM protocols WHERE id = ?");
    $stmt->execute([$protocolId]);
    $verification = $stmt->fetch(PDO::FETCH_ASSOC);
    error_log('EMERGENCY SAVE SUCCESS: ' . $verification['tenant_name'] . ' at ' . $verification['updated_at']);
    
    // 8. Event hinzufügen
    try {
        $stmt = $pdo->prepare("
            INSERT INTO protocol_events (id, protocol_id, type, message, created_at) 
            VALUES (UUID(), ?, 'updated', ?, NOW())
        ");
        $stmt->execute([$protocolId, 'EMERGENCY SAVE: ' . $newTenantName]);
    } catch (Exception $e) {
        // Event-Fehler ignorieren
        error_log('Event creation failed: ' . $e->getMessage());
    }
    
    // 9. Success Response
    $_SESSION['_flash'][] = [
        'type' => 'success', 
        'message' => 'EMERGENCY SAVE ERFOLGREICH: Protokoll gespeichert - ' . $newTenantName
    ];
    
    // 10. CRITICAL: Headers-Safe Redirect
    ob_end_clean(); // Clear any buffered output
    
    if (!headers_sent()) {
        header('Location: /protocols/edit?id=' . $protocolId);
        exit;
    } else {
        // Fallback: JavaScript + Meta Redirect
        echo '<!DOCTYPE html><html><head>';
        echo '<meta http-equiv="refresh" content="0;url=/protocols/edit?id=' . $protocolId . '">';
        echo '<title>Speichern erfolgreich</title></head><body>';
        echo '<script>window.location.href="/protocols/edit?id=' . $protocolId . '";</script>';
        echo '<p>Speichern erfolgreich! <a href="/protocols/edit?id=' . $protocolId . '">Hier klicken falls Sie nicht automatisch weitergeleitet werden</a></p>';
        echo '</body></html>';
        exit;
    }
    
} catch (Throwable $e) {
    // Error handling
    error_log('EMERGENCY SAVE ERROR: ' . $e->getMessage());
    
    if (session_status() === PHP_SESSION_ACTIVE) {
        $_SESSION['_flash'][] = [
            'type' => 'error', 
            'message' => 'EMERGENCY SAVE FEHLER: ' . $e->getMessage()
        ];
    }
    
    $protocolId = $_POST['id'] ?? '';
    
    // Headers-Safe Error Redirect
    ob_end_clean();
    
    if (!headers_sent()) {
        header('Location: /protocols/edit?id=' . $protocolId);
        exit;
    } else {
        // Fallback: JavaScript + Meta Redirect
        echo '<!DOCTYPE html><html><head>';
        echo '<meta http-equiv="refresh" content="0;url=/protocols/edit?id=' . $protocolId . '">';
        echo '<title>Fehler beim Speichern</title></head><body>';
        echo '<script>window.location.href="/protocols/edit?id=' . $protocolId . '";</script>';
        echo '<p>Fehler beim Speichern! <a href="/protocols/edit?id=' . $protocolId . '">Hier klicken um zurückzukehren</a></p>';
        echo '</body></html>';
        exit;
    }
}
?>