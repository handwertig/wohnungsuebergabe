<?php
/**
 * Test-Script für die Protocol Save-Funktionalität
 */

declare(strict_types=1);

// Bootstrap
require __DIR__ . '/../vendor/autoload.php';
if (is_file(__DIR__ . '/../.env')) {
    Dotenv\Dotenv::createImmutable(dirname(__DIR__))->load();
}

echo "=== PROTOCOL SAVE DEBUG TEST ===\n\n";

try {
    $pdo = App\Database::pdo();
    $protocolId = '82cc7de7-7d1e-11f0-89a6-822b82242c5d';
    
    echo "1. Prüfe aktuelles Protokoll...\n";
    
    // Aktuelles Protokoll laden
    $stmt = $pdo->prepare("SELECT * FROM protocols WHERE id = ? AND deleted_at IS NULL");
    $stmt->execute([$protocolId]);
    $protocol = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$protocol) {
        echo "❌ Protokoll nicht gefunden!\n";
        exit(1);
    }
    
    echo "✅ Protokoll gefunden:\n";
    echo "   - ID: {$protocol['id']}\n";
    echo "   - Mieter: {$protocol['tenant_name']}\n";
    echo "   - Typ: {$protocol['type']}\n";
    echo "   - Updated: {$protocol['updated_at']}\n\n";
    
    echo "2. Simuliere CSRF-Token...\n";
    
    // Session simulieren für CSRF
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    
    // CSRF Token generieren
    $csrfToken = App\Csrf::generateToken();
    echo "✅ CSRF Token generiert: " . substr($csrfToken, 0, 10) . "...\n\n";
    
    echo "3. Simuliere POST-Daten...\n";
    
    // POST-Daten simulieren
    $_POST = [
        '_csrf_token' => $csrfToken,
        'id' => $protocolId,
        'type' => $protocol['type'],
        'tenant_name' => $protocol['tenant_name'] . ' [TEST UPDATE ' . date('H:i:s') . ']',
        'owner_id' => $protocol['owner_id'],
        'manager_id' => $protocol['manager_id'],
        'address' => [
            'city' => 'Test Stadt',
            'street' => 'Test Straße',
            'house_no' => '123',
            'unit_label' => 'Test Einheit'
        ],
        'rooms' => [
            [
                'name' => 'Wohnzimmer Test',
                'state' => 'Gut [Debug Test]',
                'smell' => 'Neutral',
                'accepted' => true
            ]
        ],
        'meters' => [
            'strom_wohnung' => [
                'number' => 'TEST123',
                'value' => '12345'
            ]
        ],
        'keys' => [
            [
                'label' => 'Haustür Test',
                'qty' => 2,
                'no' => 'T123'
            ]
        ],
        'meta' => [
            'notes' => 'Debug Test Notizen: ' . date('Y-m-d H:i:s'),
            'bank' => [
                'bank' => 'Test Bank',
                'iban' => 'DE12345678901234567890',
                'holder' => 'Test Inhaber'
            ]
        ]
    ];
    
    echo "✅ POST-Daten vorbereitet\n";
    echo "   - CSRF Token: " . substr($_POST['_csrf_token'], 0, 10) . "...\n";
    echo "   - Protokoll ID: {$_POST['id']}\n";
    echo "   - Neuer Mieter: {$_POST['tenant_name']}\n\n";
    
    echo "4. Teste Save-Funktion direkt...\n";
    
    // Benutzer simulieren (für Auth::user())
    $_SESSION['user'] = [
        'id' => 'debug-user-123',
        'email' => 'debug@test.com',
        'name' => 'Debug User'
    ];
    
    // HTTP-Methode setzen
    $_SERVER['REQUEST_METHOD'] = 'POST';
    
    // ProtocolsController instanziieren und save() aufrufen
    $controller = new App\Controllers\ProtocolsController();
    
    // Output-Buffering für Redirects
    ob_start();
    
    try {\n        // Save-Methode aufrufen\n        $controller->save();\n        \n        // Falls wir hier ankommen, gab es keinen Redirect\n        $output = ob_get_clean();\n        echo \"⚠ Kein Redirect erfolgt - das könnte ein Problem sein\\n\";\n        \n    } catch (\\Throwable $e) {\n        ob_end_clean();\n        \n        if (strpos($e->getMessage(), 'headers already sent') !== false || \n            strpos($e->getMessage(), 'Cannot modify header') !== false) {\n            echo \"✅ Save-Funktion aufgerufen (Redirect-Fehler ist normal im Script)\\n\";\n        } else {\n            echo \"❌ Fehler beim Aufruf der Save-Funktion: \" . $e->getMessage() . \"\\n\";\n            echo \"   Datei: \" . $e->getFile() . \":\" . $e->getLine() . \"\\n\";\n            throw $e;\n        }\n    }\n    \n    echo \"\\n5. Prüfe ob Protokoll aktualisiert wurde...\\n\";\n    \n    // Protokoll erneut laden\n    $stmt = $pdo->prepare(\"SELECT * FROM protocols WHERE id = ? AND deleted_at IS NULL\");\n    $stmt->execute([$protocolId]);\n    $updatedProtocol = $stmt->fetch(PDO::FETCH_ASSOC);\n    \n    if ($updatedProtocol['tenant_name'] !== $protocol['tenant_name']) {\n        echo \"✅ Protokoll wurde aktualisiert!\\n\";\n        echo \"   - Alt: {$protocol['tenant_name']}\\n\";\n        echo \"   - Neu: {$updatedProtocol['tenant_name']}\\n\";\n        echo \"   - Updated: {$updatedProtocol['updated_at']}\\n\";\n    } else {\n        echo \"❌ Protokoll wurde NICHT aktualisiert\\n\";\n        echo \"   - Mieter unverändert: {$updatedProtocol['tenant_name']}\\n\";\n    }\n    \n    echo \"\\n6. Prüfe Versionierung...\\n\";\n    \n    // Neueste Version prüfen\n    try {\n        $stmt = $pdo->prepare(\"SELECT * FROM protocol_versions WHERE protocol_id = ? ORDER BY version_no DESC LIMIT 1\");\n        $stmt->execute([$protocolId]);\n        $latestVersion = $stmt->fetch(PDO::FETCH_ASSOC);\n        \n        if ($latestVersion) {\n            echo \"✅ Neue Version erstellt:\\n\";\n            echo \"   - Version: {$latestVersion['version_no']}\\n\";\n            echo \"   - Erstellt: {$latestVersion['created_at']}\\n\";\n            echo \"   - Von: {$latestVersion['created_by']}\\n\";\n        } else {\n            echo \"⚠ Keine Versionen gefunden\\n\";\n        }\n    } catch (\\PDOException $e) {\n        echo \"⚠ protocol_versions Tabelle nicht verfügbar: \" . $e->getMessage() . \"\\n\";\n    }\n    \n    echo \"\\n7. Prüfe Events...\\n\";\n    \n    // Neueste Events prüfen\n    try {\n        $stmt = $pdo->prepare(\"SELECT * FROM protocol_events WHERE protocol_id = ? ORDER BY created_at DESC LIMIT 3\");\n        $stmt->execute([$protocolId]);\n        $events = $stmt->fetchAll(PDO::FETCH_ASSOC);\n        \n        if (!empty($events)) {\n            echo \"✅ Events gefunden (\" . count($events) . \"):\" . \"\\n\";\n            foreach ($events as $event) {\n                echo \"   - [{$event['created_at']}] {$event['type']}: {$event['message']}\\n\";\n            }\n        } else {\n            echo \"⚠ Keine Events gefunden\\n\";\n        }\n    } catch (\\PDOException $e) {\n        echo \"⚠ protocol_events Tabelle nicht verfügbar: \" . $e->getMessage() . \"\\n\";\n    }\n    \n    echo \"\\n8. Prüfe System-Logs...\\n\";\n    \n    // Neueste System-Logs prüfen\n    try {\n        $stmt = $pdo->prepare(\"\\n            SELECT action_type, action_description, created_at \\n            FROM system_log \\n            WHERE resource_id = ? \\n            ORDER BY created_at DESC \\n            LIMIT 3\\n        \");\n        $stmt->execute([$protocolId]);\n        $systemLogs = $stmt->fetchAll(PDO::FETCH_ASSOC);\n        \n        if (!empty($systemLogs)) {\n            echo \"✅ System-Logs gefunden (\" . count($systemLogs) . \"):\" . \"\\n\";\n            foreach ($systemLogs as $log) {\n                echo \"   - [{$log['created_at']}] {$log['action_type']}: {$log['action_description']}\\n\";\n            }\n        } else {\n            echo \"⚠ Keine System-Logs gefunden\\n\";\n        }\n    } catch (\\PDOException $e) {\n        echo \"⚠ system_log Tabelle nicht verfügbar: \" . $e->getMessage() . \"\\n\";\n    }\n    \n    echo \"\\n=== TEST ABGESCHLOSSEN ===\\n\";\n    \n    // Flash-Messages prüfen\n    if (isset($_SESSION['flash_messages'])) {\n        echo \"\\n📨 Flash Messages:\\n\";\n        foreach ($_SESSION['flash_messages'] as $type => $messages) {\n            foreach ($messages as $message) {\n                echo \"   [{$type}] {$message}\\n\";\n            }\n        }\n        unset($_SESSION['flash_messages']);\n    }\n    \n} catch (\\Throwable $e) {\n    echo \"❌ KRITISCHER FEHLER: \" . $e->getMessage() . \"\\n\";\n    echo \"   Datei: \" . $e->getFile() . \":\" . $e->getLine() . \"\\n\";\n    echo \"   Stack Trace:\\n\" . $e->getTraceAsString() . \"\\n\";\n}\n?>