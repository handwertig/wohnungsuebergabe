<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Auth;
use App\Database;
use App\Settings;
use App\Flash;
use App\SignatureHelper;
use PDO;

/**
 * SignatureController
 * 
 * Verwaltet digitale Unterschriften für Protokolle
 * Unterstützt sowohl lokale Signatur (signature-pad.js) als auch DocuSign
 */
final class SignatureController
{
    /**
     * Zeigt das Signatur-Interface für ein Protokoll
     */
    public function sign(): void
    {
        Auth::requireAuth();
        $protocolId = $_GET['protocol_id'] ?? '';
        
        if (empty($protocolId)) {
            Flash::add('error', 'Protokoll-ID fehlt.');
            header('Location: /protocols');
            return;
        }
        
        // Provider aus Einstellungen laden
        $provider = Settings::get('signature_provider', 'local');
        
        if ($provider === 'docusign') {
            // Weiterleitung zu DocuSign
            $this->redirectToDocuSign($protocolId);
        } else {
            // Lokale Signatur anzeigen
            $this->showLocalSignature($protocolId);
        }
    }
    
    /**
     * Zeigt das lokale Signatur-Interface
     */
    private function showLocalSignature(string $protocolId): void
    {
        $pdo = Database::pdo();
        
        // Protokoll-Daten laden
        $stmt = $pdo->prepare("
            SELECT p.*, u.label as unit_label, o.city, o.street, o.house_no
            FROM protocols p 
            JOIN units u ON u.id = p.unit_id 
            JOIN objects o ON o.id = u.object_id 
            WHERE p.id = ? AND p.deleted_at IS NULL
        ");
        $stmt->execute([$protocolId]);
        $protocol = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$protocol) {
            Flash::add('error', 'Protokoll nicht gefunden.');
            header('Location: /protocols');
            return;
        }
        
        // Einstellungen laden
        $disclaimer = Settings::get('local_signature_disclaimer', 
            'Mit Ihrer digitalen Unterschrift bestätigen Sie die Richtigkeit der Angaben im Protokoll.');
        $legalText = Settings::get('local_signature_legal_text', 
            'Die digitale Unterschrift ist rechtlich bindend gemäß eIDAS-Verordnung (EU) Nr. 910/2014.');
        $requireAll = Settings::get('signature_require_all', 'false') === 'true';
        $allowWitness = Settings::get('signature_allow_witness', 'true') === 'true';
        
        // Bestehende Signaturen laden
        $stmt = $pdo->prepare("
            SELECT * FROM protocol_signatures 
            WHERE protocol_id = ? 
            ORDER BY created_at DESC
        ");
        $stmt->execute([$protocolId]);
        $existingSignatures = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // HTML generieren
        $html = '<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Protokoll unterschreiben</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        .signature-pad-wrapper {
            position: relative;
            border: 2px solid #dee2e6;
            border-radius: 0.375rem;
            background: white;
            margin-bottom: 1rem;
        }
        .signature-pad {
            width: 100%;
            height: 200px;
            cursor: crosshair;
        }
        .signature-pad-actions {
            position: absolute;
            top: 10px;
            right: 10px;
        }
        .signature-preview {
            max-width: 300px;
            height: 100px;
            border: 1px solid #dee2e6;
            border-radius: 0.25rem;
            padding: 0.25rem;
        }
        .protocol-info {
            background: #f8f9fa;
            border-left: 4px solid #0d6efd;
            padding: 1rem;
            margin-bottom: 2rem;
        }
    </style>
</head>
<body>
    <div class="container mt-4">
        <div class="row">
            <div class="col-lg-8 mx-auto">
                <h1 class="h3 mb-4">
                    <i class="bi bi-pen me-2"></i>Protokoll digital unterschreiben
                </h1>
                
                <!-- Protokoll-Info -->
                <div class="protocol-info">
                    <h6 class="text-primary mb-2">Protokoll-Details</h6>
                    <div class="row small">
                        <div class="col-sm-6">
                            <strong>Objekt:</strong> ' . htmlspecialchars($protocol['street'] . ' ' . $protocol['house_no']) . '<br>
                            <strong>Einheit:</strong> ' . htmlspecialchars($protocol['unit_label']) . '<br>
                            <strong>Ort:</strong> ' . htmlspecialchars($protocol['city']) . '
                        </div>
                        <div class="col-sm-6">
                            <strong>Typ:</strong> ' . htmlspecialchars(ucfirst($protocol['type'] ?? 'einzug')) . '<br>
                            <strong>Mieter:</strong> ' . htmlspecialchars($protocol['tenant_name']) . '<br>
                            <strong>Datum:</strong> ' . date('d.m.Y', strtotime($protocol['created_at'])) . '
                        </div>
                    </div>
                </div>
                
                <!-- Disclaimer -->
                <div class="alert alert-info">
                    <i class="bi bi-info-circle me-2"></i>
                    ' . htmlspecialchars($disclaimer) . '
                </div>
                
                <!-- Signatur-Formular -->
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">Neue Unterschrift hinzufügen</h5>
                    </div>
                    <div class="card-body">
                        <form id="signatureForm" method="post" action="/signature/save">
                            <input type="hidden" name="protocol_id" value="' . htmlspecialchars($protocolId) . '">
                            <input type="hidden" name="signature_data" id="signatureData">
                            
                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <label class="form-label">Name *</label>
                                    <input type="text" class="form-control" name="signer_name" required>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Rolle *</label>
                                    <select class="form-select" name="signer_role" required>
                                        <option value="">Bitte wählen...</option>
                                        <option value="tenant">Mieter</option>
                                        <option value="landlord">Vermieter</option>
                                        <option value="owner">Eigentümer</option>
                                        <option value="manager">Hausverwaltung</option>';
        
        if ($allowWitness) {
            $html .= '<option value="witness">Zeuge</option>';
        }
        
        $html .= '</select>
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label">E-Mail (optional)</label>
                                <input type="email" class="form-control" name="signer_email">
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label">Unterschrift *</label>
                                <div class="signature-pad-wrapper">
                                    <canvas id="signaturePad" class="signature-pad"></canvas>
                                    <div class="signature-pad-actions">
                                        <button type="button" class="btn btn-sm btn-outline-secondary" id="clearSignature">
                                            <i class="bi bi-arrow-counterclockwise"></i> Löschen
                                        </button>
                                    </div>
                                </div>
                                <small class="text-muted">Unterschreiben Sie mit der Maus oder dem Finger</small>
                            </div>
                            
                            <div class="d-flex justify-content-between">
                                <a href="/protocols/edit?id=' . htmlspecialchars($protocolId) . '" class="btn btn-outline-secondary">
                                    <i class="bi bi-arrow-left me-2"></i>Zurück
                                </a>
                                <button type="submit" class="btn btn-primary" id="submitSignature">
                                    <i class="bi bi-check-circle me-2"></i>Unterschrift speichern
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
                
                <!-- Bestehende Unterschriften -->
                ' . $this->renderExistingSignatures($existingSignatures) . '
                
                <!-- Rechtlicher Hinweis -->
                <div class="alert alert-secondary mt-4">
                    <small>
                        <i class="bi bi-shield-check me-2"></i>
                        ' . htmlspecialchars($legalText) . '
                    </small>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/signature_pad@4.1.7/dist/signature_pad.umd.min.js"></script>
    <script>
        document.addEventListener("DOMContentLoaded", function() {
            const canvas = document.getElementById("signaturePad");
            const signaturePad = new SignaturePad(canvas, {
                backgroundColor: "rgb(255, 255, 255)"
            });
            
            // Canvas Größe anpassen
            function resizeCanvas() {
                const ratio = Math.max(window.devicePixelRatio || 1, 1);
                canvas.width = canvas.offsetWidth * ratio;
                canvas.height = canvas.offsetHeight * ratio;
                canvas.getContext("2d").scale(ratio, ratio);
                signaturePad.clear();
            }
            
            window.addEventListener("resize", resizeCanvas);
            resizeCanvas();
            
            // Clear Button
            document.getElementById("clearSignature").addEventListener("click", function() {
                signaturePad.clear();
            });
            
            // Form Submit
            document.getElementById("signatureForm").addEventListener("submit", function(e) {
                e.preventDefault();
                
                if (signaturePad.isEmpty()) {
                    alert("Bitte unterschreiben Sie im vorgesehenen Feld.");
                    return false;
                }
                
                // Signatur als Data URL speichern
                document.getElementById("signatureData").value = signaturePad.toDataURL();
                
                // Formular absenden
                this.submit();
            });
        });
    </script>
</body>
</html>';
        
        echo $html;
        exit;
    }
    
    /**
     * Rendert bestehende Unterschriften
     */
    private function renderExistingSignatures(array $signatures): string
    {
        if (empty($signatures)) {
            return '';
        }
        
        $html = '<div class="card mt-4">
            <div class="card-header">
                <h5 class="mb-0">Vorhandene Unterschriften (' . count($signatures) . ')</h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-sm">
                        <thead>
                            <tr>
                                <th>Name</th>
                                <th>Rolle</th>
                                <th>Datum</th>
                                <th>Unterschrift</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>';
        
        foreach ($signatures as $sig) {
            $roleLabel = match($sig['signer_role'] ?? '') {
                'tenant' => 'Mieter',
                'landlord' => 'Vermieter',
                'owner' => 'Eigentümer',
                'manager' => 'Hausverwaltung',
                'witness' => 'Zeuge',
                default => $sig['signer_role'] ?? 'Unbekannt'
            };
            
            $statusBadge = $sig['is_valid'] ? 
                '<span class="badge bg-success">Gültig</span>' : 
                '<span class="badge bg-warning">Ungültig</span>';
            
            $html .= '<tr>
                <td>' . htmlspecialchars($sig['signer_name'] ?? '') . '</td>
                <td>' . htmlspecialchars($roleLabel) . '</td>
                <td>' . date('d.m.Y H:i', strtotime($sig['created_at'])) . '</td>
                <td>';
            
            if (!empty($sig['signature_data'])) {
                $html .= '<img src="' . htmlspecialchars($sig['signature_data']) . '" class="signature-preview">';
            } else {
                $html .= '<span class="text-muted">Keine Vorschau</span>';
            }
            
            $html .= '</td>
                <td>' . $statusBadge . '</td>
            </tr>';
        }
        
        $html .= '</tbody>
                    </table>
                </div>
            </div>
        </div>';
        
        return $html;
    }
    
    /**
     * Speichert eine digitale Unterschrift
     */
    public function save(): void
    {
        Auth::requireAuth();
        $user = Auth::user();
        
        $protocolId = $_POST['protocol_id'] ?? '';
        $signatureData = $_POST['signature_data'] ?? '';
        $signerName = $_POST['signer_name'] ?? '';
        $signerRole = $_POST['signer_role'] ?? '';
        $signerEmail = $_POST['signer_email'] ?? '';
        
        if (empty($protocolId) || empty($signatureData) || empty($signerName) || empty($signerRole)) {
            Flash::add('error', 'Pflichtfelder fehlen.');
            header('Location: /protocols/edit?id=' . $protocolId);
            return;
        }
        
        try {
            $pdo = Database::pdo();
            
            // Prüfe ob Tabelle existiert, wenn nicht erstelle sie
            $this->ensureSignatureTableExists($pdo);
            
            // UUID generieren
            $uuid = sprintf('%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
                mt_rand(0, 0xffff), mt_rand(0, 0xffff),
                mt_rand(0, 0xffff),
                mt_rand(0, 0x0fff) | 0x4000,
                mt_rand(0, 0x3fff) | 0x8000,
                mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
            );
            
            // IP-Adresse und User-Agent für Audit
            $ipAddress = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
            $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown';
            
            // Signatur speichern
            $stmt = $pdo->prepare("
                INSERT INTO protocol_signatures (
                    id, protocol_id, signer_name, signer_role, signer_email,
                    signature_data, signature_hash, ip_address, user_agent,
                    created_by, created_at, is_valid
                ) VALUES (
                    ?, ?, ?, ?, ?,
                    ?, ?, ?, ?,
                    ?, NOW(), 1
                )
            ");
            
            $signatureHash = hash('sha256', $signatureData . $signerName . time());
            
            $stmt->execute([
                $uuid,
                $protocolId,
                $signerName,
                $signerRole,
                $signerEmail ?: null,
                $signatureData,
                $signatureHash,
                $ipAddress,
                $userAgent,
                $user['email'] ?? 'system'
            ]);
            
            // Event loggen
            if (class_exists('\\App\\EventLogger')) {
                \App\EventLogger::logProtocolEvent(
                    $protocolId,
                    'signed',
                    "Digitale Unterschrift hinzugefügt von $signerName ($signerRole)",
                    $user['email'] ?? 'system'
                );
            }
            
            // E-Mail senden wenn aktiviert
            if (Settings::get('signature_send_emails', 'true') === 'true' && !empty($signerEmail)) {
                $this->sendSignatureConfirmation($protocolId, $signerEmail, $signerName);
            }
            
            Flash::add('success', 'Unterschrift erfolgreich gespeichert.');
            
            // Zurück zum Protokoll oder zur Signatur-Seite
            if (Settings::get('signature_require_all', 'false') === 'true') {
                header('Location: /signature/sign?protocol_id=' . $protocolId);
            } else {
                header('Location: /protocols/edit?id=' . $protocolId . '#tab-signatures');
            }
            
        } catch (\Exception $e) {
            Flash::add('error', 'Fehler beim Speichern der Unterschrift: ' . $e->getMessage());
            header('Location: /protocols/edit?id=' . $protocolId);
        }
    }
    
    /**
     * Stellt sicher dass die Signatur-Tabelle existiert
     */
    private function ensureSignatureTableExists(PDO $pdo): void
    {
        try {
            $pdo->query("SELECT 1 FROM protocol_signatures LIMIT 1");
        } catch (\PDOException $e) {
            // Tabelle existiert nicht, erstelle sie
            $pdo->exec("
                CREATE TABLE IF NOT EXISTS protocol_signatures (
                    id VARCHAR(36) PRIMARY KEY,
                    protocol_id VARCHAR(36) NOT NULL,
                    signer_name VARCHAR(255) NOT NULL,
                    signer_role VARCHAR(50) NOT NULL,
                    signer_email VARCHAR(255),
                    signature_data LONGTEXT,
                    signature_hash VARCHAR(64),
                    ip_address VARCHAR(45),
                    user_agent TEXT,
                    created_by VARCHAR(255),
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    is_valid BOOLEAN DEFAULT TRUE,
                    INDEX idx_protocol_id (protocol_id),
                    INDEX idx_signer_role (signer_role),
                    INDEX idx_created_at (created_at)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            ");
        }
    }
    
    /**
     * Sendet eine Bestätigungs-E-Mail nach der Unterschrift
     */
    private function sendSignatureConfirmation(string $protocolId, string $email, string $name): void
    {
        // TODO: E-Mail-Versand implementieren
        // Vorerst nur Logging
        error_log("Signature confirmation email would be sent to $email for $name (Protocol: $protocolId)");
    }
    
    /**
     * Weiterleitung zu DocuSign für externe Signatur
     */
    private function redirectToDocuSign(string $protocolId): void
    {
        // DocuSign Integration
        $baseUrl = Settings::get('docusign_base_url', '');
        $accountId = Settings::get('docusign_account_id', '');
        $integrationKey = Settings::get('docusign_integration_key', '');
        
        if (empty($baseUrl) || empty($accountId) || empty($integrationKey)) {
            Flash::add('error', 'DocuSign ist nicht konfiguriert. Bitte prüfen Sie die Einstellungen.');
            header('Location: /settings/signatures');
            return;
        }
        
        // TODO: DocuSign OAuth und Envelope-Erstellung implementieren
        Flash::add('info', 'DocuSign-Integration wird vorbereitet...');
        header('Location: /protocols/edit?id=' . $protocolId);
    }
    
    /**
     * Test-Seite für Signatur-Funktionalität
     */
    public function test(): void
    {
        Auth::requireAuth();
        
        $provider = Settings::get('signature_provider', 'local');
        
        $html = '<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Signatur-Test</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
    <div class="container mt-5">
        <h1>Signatur-Test</h1>
        <div class="alert alert-info">
            <strong>Aktiver Provider:</strong> ' . strtoupper($provider) . '
        </div>
        
        <div class="card">
            <div class="card-body">
                <h5>Test-Funktionen</h5>
                <p>Provider: ' . htmlspecialchars($provider) . '</p>
                <p>Status: ';
        
        if ($provider === 'local') {
            $html .= '<span class="badge bg-success">Bereit</span>';
        } else {
            $html .= '<span class="badge bg-warning">DocuSign-Konfiguration prüfen</span>';
        }
        
        $html .= '</p>
                <a href="/settings/signatures" class="btn btn-primary">Zurück zu den Einstellungen</a>
            </div>
        </div>
    </div>
</body>
</html>';
        
        echo $html;
        exit;
    }
    
    /**
     * Verifiziert eine digitale Unterschrift
     */
    public function verify(): void
    {
        Auth::requireAuth();
        
        $signatureId = $_GET['signature_id'] ?? '';
        
        if (empty($signatureId)) {
            http_response_code(400);
            echo json_encode(['error' => 'Signature ID required']);
            return;
        }
        
        try {
            $pdo = Database::pdo();
            
            // Signatur-Details laden
            $stmt = $pdo->prepare("
                SELECT * FROM protocol_signatures 
                WHERE id = ?
            ");
            $stmt->execute([$signatureId]);
            $signature = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$signature) {
                http_response_code(404);
                echo json_encode(['error' => 'Signature not found']);
                return;
            }
            
            // Verifikation durchführen
            $isValid = $this->verifySignatureHash(
                $signature['signature_data'],
                $signature['signer_name'],
                $signature['signature_hash'],
                $signature['created_at']
            );
            
            // Antwort senden
            header('Content-Type: application/json');
            echo json_encode([
                'valid' => $isValid,
                'signature' => [
                    'id' => $signature['id'],
                    'signer_name' => $signature['signer_name'],
                    'signer_role' => $signature['signer_role'],
                    'created_at' => $signature['created_at'],
                    'ip_address' => $signature['ip_address']
                ]
            ]);
            
        } catch (\Exception $e) {
            http_response_code(500);
            echo json_encode(['error' => 'Verification failed: ' . $e->getMessage()]);
        }
    }
    
    /**
     * Überprüft den Hash einer Signatur
     */
    private function verifySignatureHash(string $signatureData, string $signerName, string $storedHash, string $createdAt): bool
    {
        // Zeitstempel aus created_at extrahieren
        $timestamp = strtotime($createdAt);
        
        // Hash mit verschiedenen Zeitstempel-Variationen prüfen
        // (da der exakte Zeitstempel möglicherweise leicht abweicht)
        for ($i = -2; $i <= 2; $i++) {
            $testTime = $timestamp + $i;
            $testHash = hash('sha256', $signatureData . $signerName . $testTime);
            
            if ($testHash === $storedHash) {
                return true;
            }
        }
        
        return false;
    }
}
