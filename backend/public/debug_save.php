<?php
/**
 * Debug-Endpoint für Protocol Save ohne CSRF-Prüfung
 */

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';
if (is_file(__DIR__ . '/../.env')) {
    Dotenv\Dotenv::createImmutable(dirname(__DIR__))->load();
}

// Session starten
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Benutzer simulieren
$_SESSION['user'] = [
    'id' => 'debug-user-123',
    'email' => 'debug@test.com',
    'name' => 'Debug User'
];

echo "=== PROTOCOL SAVE DEBUG ENDPOINT ===\n\n";

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        echo "Nur POST-Requests erlaubt\n";
        echo "Test-Aufruf:\n";
        echo "curl -X POST http://localhost:8080/debug_save.php \\\n";
        echo "  -H 'Content-Type: application/x-www-form-urlencoded' \\\n";
        echo "  -d 'id=82cc7de7-7d1e-11f0-89a6-822b82242c5d&tenant_name=Test+Debug&type=auszug'\n";
        exit;
    }

    $pdo = App\Database::pdo();
    $protocolId = (string)($_POST['id'] ?? '');
    
    if (empty($protocolId)) {
        echo "❌ Protokoll-ID fehlt\n";
        exit;
    }

    echo "🔍 Protokoll ID: {$protocolId}\n";
    echo "📦 POST-Daten empfangen: " . json_encode($_POST, JSON_UNESCAPED_UNICODE) . "\n\n";

    // Aktuelles Protokoll laden
    $stmt = $pdo->prepare("SELECT * FROM protocols WHERE id = ? AND deleted_at IS NULL");
    $stmt->execute([$protocolId]);
    $currentProtocol = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$currentProtocol) {
        echo "❌ Protokoll nicht gefunden\n";
        exit;
    }

    echo "✅ Aktuelles Protokoll gefunden:\n";
    echo "   - Mieter: {$currentProtocol['tenant_name']}\n";
    echo "   - Typ: {$currentProtocol['type']}\n";
    echo "   - Updated: {$currentProtocol['updated_at']}\n\n";

    $pdo->beginTransaction();

    // Update-Daten vorbereiten
    $updateData = [
        'type' => (string)($_POST['type'] ?? $currentProtocol['type']),
        'tenant_name' => (string)($_POST['tenant_name'] ?? $currentProtocol['tenant_name']),
        'owner_id' => !empty($_POST['owner_id']) ? (string)$_POST['owner_id'] : $currentProtocol['owner_id'],
        'manager_id' => !empty($_POST['manager_id']) ? (string)$_POST['manager_id'] : $currentProtocol['manager_id'],
        'updated_at' => date('Y-m-d H:i:s')
    ];

    // Payload
    $payload = [
        'address' => $_POST['address'] ?? [],
        'rooms' => $_POST['rooms'] ?? [],
        'meters' => $_POST['meters'] ?? [],
        'keys' => $_POST['keys'] ?? [],
        'meta' => $_POST['meta'] ?? [],
        'timestamp' => date('Y-m-d H:i:s')
    ];

    $updateData['payload'] = json_encode($payload, JSON_UNESCAPED_UNICODE);

    echo "🔄 Update-Daten:\n";
    foreach ($updateData as $key => $value) {
        if ($key === 'payload') {
            echo "   - {$key}: " . substr($value, 0, 100) . "...\n";
        } else {
            echo "   - {$key}: {$value}\n";
        }
    }
    echo "\n";

    // Protokoll aktualisieren
    $setParts = [];
    $params = [];
    foreach ($updateData as $field => $value) {
        $setParts[] = "`{$field}` = ?";
        $params[] = $value;
    }
    $params[] = $protocolId;
    
    $sql = "UPDATE protocols SET " . implode(', ', $setParts) . " WHERE id = ?";
    echo "📝 SQL: {$sql}\n\n";
    
    $stmt = $pdo->prepare($sql);
    $updateResult = $stmt->execute($params);
    
    if (!$updateResult) {
        throw new Exception('Protokoll-Update fehlgeschlagen');
    }

    echo "✅ Protokoll erfolgreich aktualisiert\n";

    // Änderungen ermitteln
    $changes = [];
    foreach (['type', 'tenant_name', 'owner_id', 'manager_id'] as $field) {
        if (($currentProtocol[$field] ?? '') !== ($updateData[$field] ?? '')) {
            $changes[$field] = [
                'from' => $currentProtocol[$field] ?? '',
                'to' => $updateData[$field] ?? ''
            ];
        }
    }

    // Payload-Änderungen
    $oldPayload = json_decode($currentProtocol['payload'] ?? '{}', true) ?: [];
    if (json_encode($oldPayload) !== json_encode($payload)) {
        $changes['payload'] = 'modified';
    }

    echo "📊 Änderungen erkannt: " . (empty($changes) ? 'keine' : implode(', ', array_keys($changes))) . "\n\n";

    // Versionierung
    try {
        $pdo->query("SELECT 1 FROM protocol_versions LIMIT 1");
        
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
            'debug@test.com'
        ]);
        
        echo "📋 Version {$nextVersion} erstellt\n";
        
    } catch (PDOException $e) {
        echo "⚠ Versionierung nicht verfügbar: " . $e->getMessage() . "\n";
    }

    // Events
    try {
        $changesDescription = !empty($changes) ? implode(', ', array_keys($changes)) : 'keine Änderungen';
        
        $stmt = $pdo->prepare("
            INSERT INTO protocol_events (id, protocol_id, type, message, created_by, created_at) 
            VALUES (UUID(), ?, 'updated', ?, ?, NOW())
        ");
        $stmt->execute([$protocolId, 'Debug-Update: ' . $changesDescription, 'debug@test.com']);
        
        echo "📝 Event erstellt\n";
        
    } catch (PDOException $e) {
        echo "⚠ Events nicht verfügbar: " . $e->getMessage() . "\n";
    }

    $pdo->commit();
    echo "✅ Transaktion committed\n\n";

    // Logging
    try {
        if (class_exists('App\SystemLogger')) {
            App\SystemLogger::logProtocolUpdated($protocolId, [
                'type' => $updateData['type'],
                'tenant_name' => $updateData['tenant_name'],
                'city' => $payload['address']['city'] ?? '',
                'street' => $payload['address']['street'] ?? '',
                'unit' => $payload['address']['unit_label'] ?? ''
            ], array_keys($changes));
            
            echo "📊 SystemLogger aufgerufen\n";
        }

        if (class_exists('App\AuditLogger')) {
            App\AuditLogger::log('protocol', $protocolId, 'updated', $changes);
            echo "📋 AuditLogger aufgerufen\n";
        }
    } catch (Throwable $e) {
        echo "⚠ Logging-Fehler: " . $e->getMessage() . "\n";
    }

    // Verifikation: Protokoll erneut laden
    $stmt = $pdo->prepare("SELECT tenant_name, updated_at FROM protocols WHERE id = ?");
    $stmt->execute([$protocolId]);
    $verification = $stmt->fetch(PDO::FETCH_ASSOC);

    echo "\n=== VERIFIKATION ===\n";
    echo "✅ Protokoll erfolgreich gespeichert!\n";
    echo "   - Neuer Mieter: {$verification['tenant_name']}\n";
    echo "   - Letztes Update: {$verification['updated_at']}\n";

    // Neueste Logs zeigen
    try {
        $stmt = $pdo->prepare("
            SELECT action_type, action_description, created_at 
            FROM system_log 
            WHERE resource_id = ? 
            ORDER BY created_at DESC 
            LIMIT 2
        ");
        $stmt->execute([$protocolId]);
        $logs = $stmt->fetchAll(PDO::FETCH_ASSOC);

        echo "\n📊 Neueste System-Logs:\n";
        foreach ($logs as $log) {
            echo "   - [{$log['created_at']}] {$log['action_type']}: {$log['action_description']}\n";
        }
    } catch (PDOException $e) {
        echo "⚠ System-Logs nicht verfügbar\n";
    }

} catch (Throwable $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    
    echo "❌ FEHLER: " . $e->getMessage() . "\n";
    echo "   Datei: " . $e->getFile() . ":" . $e->getLine() . "\n";
}
?>