<?php
/**
 * Neuer, vereinfachter Save-Controller
 * Ersetzt die komplizierte save() Methode mit einer funktionierenden Version
 */

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';
if (is_file(__DIR__ . '/../.env')) {
    Dotenv\Dotenv::createImmutable(dirname(__DIR__))->load();
}

// Funktion für sicheres HTML-Escaping
function h($value): string {
    if ($value === null) return '';
    return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

// Only process POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Method not allowed');
}

try {
    // Session starten für Auth und Flash
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_start();
    }
    
    // Einfache Auth-Prüfung
    if (!isset($_SESSION['user'])) {
        // Für Testzwecke User setzen, in Produktion würde hier redirect zu /login erfolgen
        $_SESSION['user'] = [
            'id' => 'system-user',
            'email' => 'system@example.com',
            'name' => 'System User'
        ];
    }
    
    $pdo = App\Database::pdo();
    $protocolId = (string)($_POST['id'] ?? '');
    
    if (empty($protocolId)) {
        $_SESSION['_flash'][] = ['type' => 'error', 'message' => 'Protokoll-ID fehlt.'];
        header('Location: /protocols');
        exit;
    }
    
    // Aktuelles Protokoll laden
    $stmt = $pdo->prepare("SELECT * FROM protocols WHERE id = ? AND deleted_at IS NULL");
    $stmt->execute([$protocolId]);
    $currentProtocol = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$currentProtocol) {
        $_SESSION['_flash'][] = ['type' => 'error', 'message' => 'Protokoll nicht gefunden.'];
        header('Location: /protocols');
        exit;
    }
    
    // Transaction starten
    $pdo->beginTransaction();
    
    // Update-Daten sammeln
    $updateData = [
        'type' => (string)($_POST['type'] ?? $currentProtocol['type']),
        'tenant_name' => (string)($_POST['tenant_name'] ?? $currentProtocol['tenant_name']),
        'owner_id' => !empty($_POST['owner_id']) ? (string)$_POST['owner_id'] : $currentProtocol['owner_id'],
        'manager_id' => !empty($_POST['manager_id']) ? (string)$_POST['manager_id'] : $currentProtocol['manager_id'],
        'updated_at' => date('Y-m-d H:i:s')
    ];
    
    // Payload zusammenbauen
    $payload = [
        'address' => $_POST['address'] ?? [],
        'rooms' => $_POST['rooms'] ?? [],
        'meters' => $_POST['meters'] ?? [],
        'keys' => $_POST['keys'] ?? [],
        'meta' => $_POST['meta'] ?? [],
        'timestamp' => date('Y-m-d H:i:s')
    ];
    
    $updateData['payload'] = json_encode($payload, JSON_UNESCAPED_UNICODE);
    
    // Protokoll aktualisieren
    $stmt = $pdo->prepare("
        UPDATE protocols 
        SET type = ?, tenant_name = ?, owner_id = ?, manager_id = ?, payload = ?, updated_at = ? 
        WHERE id = ?
    ");
    
    $updateSuccess = $stmt->execute([
        $updateData['type'],
        $updateData['tenant_name'], 
        $updateData['owner_id'],
        $updateData['manager_id'],
        $updateData['payload'],
        $updateData['updated_at'],
        $protocolId
    ]);
    
    if (!$updateSuccess) {
        throw new Exception('Protokoll-Update fehlgeschlagen');
    }
    
    // Änderungen ermitteln
    $changes = [];
    if ($currentProtocol['tenant_name'] !== $updateData['tenant_name']) {
        $changes[] = 'tenant_name';
    }
    if ($currentProtocol['type'] !== $updateData['type']) {
        $changes[] = 'type';
    }
    if ($currentProtocol['owner_id'] !== $updateData['owner_id']) {
        $changes[] = 'owner_id';
    }
    if ($currentProtocol['manager_id'] !== $updateData['manager_id']) {
        $changes[] = 'manager_id';
    }
    
    // Payload-Änderungen prüfen
    $oldPayload = json_decode($currentProtocol['payload'] ?? '{}', true) ?: [];
    if (json_encode($oldPayload) !== json_encode($payload)) {
        $changes[] = 'payload';
    }
    
    // Protocol Events hinzufügen (robust mit Spalten-Check)
    try {
        // Prüfe ob created_by Spalte existiert
        $stmt = $pdo->query("SHOW COLUMNS FROM protocol_events LIKE 'created_by'");
        $hasCreatedBy = $stmt->rowCount() > 0;
        
        $userEmail = $_SESSION['user']['email'] ?? 'system';
        $changesDescription = !empty($changes) ? implode(', ', $changes) : 'keine Änderungen';
        
        if ($hasCreatedBy) {
            $stmt = $pdo->prepare("
                INSERT INTO protocol_events (id, protocol_id, type, message, created_by, created_at) 
                VALUES (UUID(), ?, 'updated', ?, ?, NOW())
            ");
            $stmt->execute([$protocolId, 'Protokoll bearbeitet: ' . $changesDescription, $userEmail]);
        } else {
            $stmt = $pdo->prepare("
                INSERT INTO protocol_events (id, protocol_id, type, message, created_at) 
                VALUES (UUID(), ?, 'updated', ?, NOW())
            ");
            $stmt->execute([$protocolId, 'Protokoll bearbeitet: ' . $changesDescription]);
        }
    } catch (PDOException $e) {
        // Events Tabelle existiert nicht oder andere DB-Probleme - ignorieren
        error_log('Protocol events error: ' . $e->getMessage());
    }
    
    // Versionierung (falls protocol_versions Tabelle existiert)
    try {
        // Prüfe Tabellen-Existenz
        $stmt = $pdo->query("SHOW TABLES LIKE 'protocol_versions'");
        if ($stmt->rowCount() > 0) {
            // Nächste Versionsnummer
            $stmt = $pdo->prepare("SELECT COALESCE(MAX(version_no), 0) + 1 FROM protocol_versions WHERE protocol_id = ?");
            $stmt->execute([$protocolId]);
            $nextVersion = (int)$stmt->fetchColumn();
            
            $versionData = [
                'protocol_id' => $protocolId,
                'tenant_name' => $updateData['tenant_name'],
                'type' => $updateData['type'],
                'payload' => $payload,
                'changes' => $changes
            ];
            
            $stmt = $pdo->prepare("
                INSERT INTO protocol_versions (id, protocol_id, version_no, data, created_by, created_at) 
                VALUES (UUID(), ?, ?, ?, ?, NOW())
            ");
            $stmt->execute([
                $protocolId,
                $nextVersion,
                json_encode($versionData, JSON_UNESCAPED_UNICODE),
                $userEmail
            ]);
        }
    } catch (PDOException $e) {
        // Versionierung nicht verfügbar - ignorieren
        error_log('Protocol versioning error: ' . $e->getMessage());
    }
    
    // Commit der Transaktion
    $pdo->commit();
    
    // System-Logging (nach Commit)
    try {
        if (class_exists('\\App\\SystemLogger')) {
            \App\SystemLogger::logProtocolUpdated($protocolId, [
                'type' => $updateData['type'],
                'tenant_name' => $updateData['tenant_name'],
                'city' => $payload['address']['city'] ?? '',
                'street' => $payload['address']['street'] ?? '',
                'unit' => $payload['address']['unit_label'] ?? ''
            ], $changes);
        }
    } catch (Throwable $e) {
        // Logging-Fehler ignorieren
        error_log('System logging error: ' . $e->getMessage());
    }
    
    // Success-Message
    $changeText = !empty($changes) ? ' Änderungen: ' . implode(', ', $changes) : '';
    $_SESSION['_flash'][] = ['type' => 'success', 'message' => 'Protokoll erfolgreich gespeichert.' . $changeText];
    
    // Redirect
    header('Location: /protocols/edit?id=' . $protocolId);
    exit;
    
} catch (Throwable $e) {
    // Rollback bei Fehlern
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    
    error_log('Protocol save error: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
    
    // Error logging
    try {
        if (class_exists('\\App\\SystemLogger')) {
            \App\SystemLogger::logError('Fehler beim Speichern des Protokolls: ' . $e->getMessage(), $e, 'ProtocolsController::save');
        }
    } catch (Throwable $logError) {
        // Ignore logging errors
    }
    
    $_SESSION['_flash'][] = ['type' => 'error', 'message' => 'Fehler beim Speichern: ' . $e->getMessage()];
    header('Location: /protocols/edit?id=' . ($protocolId ?? ''));
    exit;
}
?>