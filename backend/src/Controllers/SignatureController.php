<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Auth;
use App\Database;
use App\View;
use App\Flash;
use App\AuditLogger;
use PDO;

/**
 * Digital Signature Controller
 * Open Source Lösung für rechtssichere digitale Signaturen
 * Implementiert mit Signature Pad (MIT License)
 */
final class SignatureController
{
    /**
     * Zeigt die Signatur-Seite für ein Protokoll
     */
    public function sign(): void
    {
        Auth::requireAuth();
        
        $protocolId = $_GET['protocol_id'] ?? '';
        if (!$protocolId) {
            Flash::add('error', 'Kein Protokoll angegeben.');
            header('Location: /protocols');
            exit;
        }
        
        $pdo = Database::pdo();
        $stmt = $pdo->prepare('
            SELECT p.*, u.label as unit_label, o.street, o.house_no, o.city 
            FROM protocols p 
            JOIN units u ON p.unit_id = u.id 
            JOIN objects o ON u.object_id = o.id 
            WHERE p.id = ? AND p.deleted_at IS NULL
        ');
        $stmt->execute([$protocolId]);
        $protocol = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$protocol) {
            Flash::add('error', 'Protokoll nicht gefunden.');
            header('Location: /protocols');
            exit;
        }
        
        $payload = json_decode($protocol['payload'] ?? '{}', true) ?: [];
        
        ob_start(); ?>
        
        <div class="container-fluid">
            <div class="row justify-content-center">
                <div class="col-lg-8">
                    <!-- Header -->
                    <div class="card mb-4">
                        <div class="card-body">
                            <h4 class="card-title mb-3">
                                <i class="bi bi-pen"></i> Digitale Unterschriften
                            </h4>
                            <p class="text-muted mb-0">
                                Protokoll für <?= htmlspecialchars($protocol['unit_label']) ?>, 
                                <?= htmlspecialchars($protocol['street']) ?> <?= htmlspecialchars($protocol['house_no']) ?>, 
                                <?= htmlspecialchars($protocol['city']) ?>
                            </p>
                            <p class="text-muted">
                                <small>Typ: <?= ucfirst($protocol['type']) ?> | 
                                Datum: <?= date('d.m.Y', strtotime($protocol['created_at'])) ?></small>
                            </p>
                        </div>
                    </div>
                    
                    <!-- Signatur-Formular -->
                    <form id="signatureForm" method="post" action="/signature/save">
                        <input type="hidden" name="protocol_id" value="<?= htmlspecialchars($protocolId) ?>">
                        
                        <!-- Mieter Unterschrift -->
                        <div class="card mb-4">
                            <div class="card-header">
                                <h5 class="mb-0">
                                    <i class="bi bi-person"></i> Unterschrift Mieter
                                </h5>
                            </div>
                            <div class="card-body">
                                <div class="row mb-3">
                                    <div class="col-md-6">
                                        <label class="form-label">Name *</label>
                                        <input type="text" class="form-control" name="tenant_name" 
                                               value="<?= htmlspecialchars($payload['tenant']['name'] ?? '') ?>" required>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">E-Mail</label>
                                        <input type="email" class="form-control" name="tenant_email" 
                                               value="<?= htmlspecialchars($payload['tenant']['email'] ?? '') ?>">
                                    </div>
                                </div>
                                
                                <div id="tenantSignature"></div>
                                <input type="hidden" name="tenant_signature" id="tenantSignatureData">
                                
                                <?php if (!empty($protocol['signature_tenant_data'])): ?>
                                <div class="alert alert-success mt-3">
                                    <i class="bi bi-check-circle"></i> 
                                    Bereits unterschrieben am <?= date('d.m.Y H:i', strtotime($protocol['signature_tenant_timestamp'])) ?>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <!-- Vermieter Unterschrift -->
                        <div class="card mb-4">
                            <div class="card-header">
                                <h5 class="mb-0">
                                    <i class="bi bi-building"></i> Unterschrift Vermieter/Verwalter
                                </h5>
                            </div>
                            <div class="card-body">
                                <div class="row mb-3">
                                    <div class="col-md-6">
                                        <label class="form-label">Name *</label>
                                        <input type="text" class="form-control" name="landlord_name" 
                                               value="<?= htmlspecialchars($payload['landlord']['name'] ?? '') ?>" required>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">E-Mail</label>
                                        <input type="email" class="form-control" name="landlord_email" 
                                               value="<?= htmlspecialchars($payload['landlord']['email'] ?? '') ?>">
                                    </div>
                                </div>
                                
                                <div id="landlordSignature"></div>
                                <input type="hidden" name="landlord_signature" id="landlordSignatureData">
                                
                                <?php if (!empty($protocol['signature_landlord_data'])): ?>
                                <div class="alert alert-success mt-3">
                                    <i class="bi bi-check-circle"></i> 
                                    Bereits unterschrieben am <?= date('d.m.Y H:i', strtotime($protocol['signature_landlord_timestamp'])) ?>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <!-- Optional: Zeuge -->
                        <div class="card mb-4">
                            <div class="card-header">
                                <h5 class="mb-0">
                                    <i class="bi bi-people"></i> Unterschrift Zeuge (optional)
                                </h5>
                            </div>
                            <div class="card-body">
                                <div class="form-check mb-3">
                                    <input class="form-check-input" type="checkbox" id="enableWitness">
                                    <label class="form-check-label" for="enableWitness">
                                        Zeuge hinzufügen
                                    </label>
                                </div>
                                
                                <div id="witnessSection" style="display: none;">
                                    <div class="row mb-3">
                                        <div class="col-md-6">
                                            <label class="form-label">Name</label>
                                            <input type="text" class="form-control" name="witness_name">
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label">E-Mail</label>
                                            <input type="email" class="form-control" name="witness_email">
                                        </div>
                                    </div>
                                    
                                    <div id="witnessSignature"></div>
                                    <input type="hidden" name="witness_signature" id="witnessSignatureData">
                                </div>
                            </div>
                        </div>
                        
                        <!-- Rechtliche Hinweise -->
                        <div class="card mb-4">
                            <div class="card-body">
                                <h6 class="card-subtitle mb-2 text-muted">
                                    <i class="bi bi-shield-check"></i> Rechtliche Hinweise
                                </h6>
                                <ul class="small text-muted mb-3">
                                    <li>Die digitale Unterschrift ist rechtlich bindend gemäß eIDAS-Verordnung (EU) Nr. 910/2014</li>
                                    <li>Ihre Unterschrift wird verschlüsselt gespeichert und mit einem Zeitstempel versehen</li>
                                    <li>IP-Adresse und technische Metadaten werden zur Beweissicherung protokolliert</li>
                                    <li>Die Unterschrift kann nicht nachträglich verändert werden</li>
                                </ul>
                                
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="acceptTerms" required>
                                    <label class="form-check-label" for="acceptTerms">
                                        Ich bestätige die Richtigkeit der Angaben im Protokoll und akzeptiere die digitale Signatur als rechtsverbindlich. *
                                    </label>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Buttons -->
                        <div class="d-flex justify-content-between mb-4">
                            <a href="/protocols" class="btn btn-outline-secondary">
                                <i class="bi bi-arrow-left"></i> Zurück
                            </a>
                            <div>
                                <button type="button" class="btn btn-outline-primary me-2" onclick="previewSignatures()">
                                    <i class="bi bi-eye"></i> Vorschau
                                </button>
                                <button type="submit" class="btn btn-primary">
                                    <i class="bi bi-check-lg"></i> Unterschriften speichern
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        
        <!-- Modal für Vorschau -->
        <div class="modal fade" id="previewModal" tabindex="-1">
            <div class="modal-dialog modal-lg">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">Vorschau der Unterschriften</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body" id="previewContent"></div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Schließen</button>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- JavaScript -->
        <script src="/js/signature-module.js"></script>
        <script>
            // Initialisiere Signature Pads
            let tenantSig = new SignatureModule('tenantSignature', {
                width: 600,
                height: 150
            });
            
            let landlordSig = new SignatureModule('landlordSignature', {
                width: 600,
                height: 150
            });
            
            let witnessSig = null;
            
            // Zeuge aktivieren/deaktivieren
            document.getElementById('enableWitness').addEventListener('change', function(e) {
                const witnessSection = document.getElementById('witnessSection');
                if (e.target.checked) {
                    witnessSection.style.display = 'block';
                    if (!witnessSig) {
                        witnessSig = new SignatureModule('witnessSignature', {
                            width: 600,
                            height: 150
                        });
                    }
                } else {
                    witnessSection.style.display = 'none';
                }
            });
            
            // Form Submit
            document.getElementById('signatureForm').addEventListener('submit', function(e) {
                e.preventDefault();
                
                // Validierung
                const tenantValid = tenantSig.validateSignature();
                const landlordValid = landlordSig.validateSignature();
                
                if (!tenantValid.valid) {
                    alert('Mieter: ' + tenantValid.message);
                    return;
                }
                
                if (!landlordValid.valid) {
                    alert('Vermieter: ' + landlordValid.message);
                    return;
                }
                
                // Signatur-Daten sammeln
                document.getElementById('tenantSignatureData').value = JSON.stringify(tenantSig.getSignatureData());
                document.getElementById('landlordSignatureData').value = JSON.stringify(landlordSig.getSignatureData());
                
                if (witnessSig && !witnessSig.isEmpty()) {
                    document.getElementById('witnessSignatureData').value = JSON.stringify(witnessSig.getSignatureData());
                }
                
                // Formular absenden
                this.submit();
            });
            
            // Vorschau
            function previewSignatures() {
                const modal = new bootstrap.Modal(document.getElementById('previewModal'));
                const content = document.getElementById('previewContent');
                
                let html = '<div class="row">';
                
                // Mieter
                if (!tenantSig.isEmpty()) {
                    html += '<div class="col-md-6 mb-3">';
                    html += '<h6>Mieter: ' + document.querySelector('[name="tenant_name"]').value + '</h6>';
                    html += '<img src="' + tenantSig.getSignatureData().dataUrl + '" class="img-fluid border">';
                    html += '</div>';
                }
                
                // Vermieter
                if (!landlordSig.isEmpty()) {
                    html += '<div class="col-md-6 mb-3">';
                    html += '<h6>Vermieter: ' + document.querySelector('[name="landlord_name"]').value + '</h6>';
                    html += '<img src="' + landlordSig.getSignatureData().dataUrl + '" class="img-fluid border">';
                    html += '</div>';
                }
                
                // Zeuge
                if (witnessSig && !witnessSig.isEmpty()) {
                    html += '<div class="col-md-6 mb-3">';
                    html += '<h6>Zeuge: ' + document.querySelector('[name="witness_name"]').value + '</h6>';
                    html += '<img src="' + witnessSig.getSignatureData().dataUrl + '" class="img-fluid border">';
                    html += '</div>';
                }
                
                html += '</div>';
                content.innerHTML = html;
                modal.show();
            }
        </script>
        
        <?php
        $content = ob_get_clean();
        View::render('Digitale Unterschriften', $content);
    }
    
    /**
     * Speichert die digitalen Signaturen
     */
    public function save(): void
    {
        Auth::requireAuth();
        
        $protocolId = $_POST['protocol_id'] ?? '';
        if (!$protocolId) {
            Flash::add('error', 'Kein Protokoll angegeben.');
            header('Location: /protocols');
            exit;
        }
        
        $pdo = Database::pdo();
        
        // Signatur-Daten verarbeiten
        $tenantSig = json_decode($_POST['tenant_signature'] ?? '{}', true);
        $landlordSig = json_decode($_POST['landlord_signature'] ?? '{}', true);
        $witnessSig = json_decode($_POST['witness_signature'] ?? '{}', true);
        
        // IP und User Agent für Audit
        $ipAddress = $_SERVER['REMOTE_ADDR'] ?? '';
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
        
        try {
            $pdo->beginTransaction();
            
            // Update Protocol mit Signaturen
            $stmt = $pdo->prepare('
                UPDATE protocols SET 
                    signature_tenant_data = ?,
                    signature_tenant_metadata = ?,
                    signature_tenant_timestamp = NOW(),
                    signature_landlord_data = ?,
                    signature_landlord_metadata = ?,
                    signature_landlord_timestamp = NOW(),
                    signature_witness_data = ?,
                    signature_witness_metadata = ?,
                    signature_witness_timestamp = IF(? IS NOT NULL, NOW(), NULL),
                    updated_at = NOW()
                WHERE id = ?
            ');
            
            $stmt->execute([
                $tenantSig['dataUrl'] ?? null,
                json_encode(array_merge($tenantSig['metadata'] ?? [], [
                    'name' => $_POST['tenant_name'] ?? '',
                    'email' => $_POST['tenant_email'] ?? '',
                    'ip' => $ipAddress,
                    'userAgent' => $userAgent
                ])),
                $landlordSig['dataUrl'] ?? null,
                json_encode(array_merge($landlordSig['metadata'] ?? [], [
                    'name' => $_POST['landlord_name'] ?? '',
                    'email' => $_POST['landlord_email'] ?? '',
                    'ip' => $ipAddress,
                    'userAgent' => $userAgent
                ])),
                $witnessSig['dataUrl'] ?? null,
                $witnessSig ? json_encode(array_merge($witnessSig['metadata'] ?? [], [
                    'name' => $_POST['witness_name'] ?? '',
                    'email' => $_POST['witness_email'] ?? '',
                    'ip' => $ipAddress,
                    'userAgent' => $userAgent
                ])) : null,
                $witnessSig['dataUrl'] ?? null,
                $protocolId
            ]);
            
            // Signatur-Historie speichern (für Audit-Trail)
            if (!empty($tenantSig['dataUrl'])) {
                $this->saveSignatureHistory($pdo, $protocolId, 'tenant', $_POST['tenant_name'] ?? '', 
                    $_POST['tenant_email'] ?? '', $tenantSig, $ipAddress, $userAgent);
            }
            
            if (!empty($landlordSig['dataUrl'])) {
                $this->saveSignatureHistory($pdo, $protocolId, 'landlord', $_POST['landlord_name'] ?? '', 
                    $_POST['landlord_email'] ?? '', $landlordSig, $ipAddress, $userAgent);
            }
            
            if (!empty($witnessSig['dataUrl'])) {
                $this->saveSignatureHistory($pdo, $protocolId, 'witness', $_POST['witness_name'] ?? '', 
                    $_POST['witness_email'] ?? '', $witnessSig, $ipAddress, $userAgent);
            }
            
            $pdo->commit();
            
            // Audit Log
            if (class_exists('App\\AuditLogger')) {
                AuditLogger::log('protocol_signatures', $protocolId, 'sign', [
                    'tenant' => $_POST['tenant_name'] ?? '',
                    'landlord' => $_POST['landlord_name'] ?? '',
                    'witness' => $_POST['witness_name'] ?? ''
                ]);
            }
            
            Flash::add('success', 'Digitale Unterschriften wurden erfolgreich gespeichert.');
            header('Location: /protocols/view?id=' . $protocolId);
            
        } catch (\Exception $e) {
            $pdo->rollBack();
            Flash::add('error', 'Fehler beim Speichern der Unterschriften: ' . $e->getMessage());
            header('Location: /signature/sign?protocol_id=' . $protocolId);
        }
        
        exit;
    }
    
    /**
     * Speichert Signatur in Historie-Tabelle
     */
    private function saveSignatureHistory(PDO $pdo, string $protocolId, string $signerType, 
        string $name, string $email, array $signatureData, string $ip, string $userAgent): void
    {
        $stmt = $pdo->prepare('
            INSERT INTO protocol_signatures 
            (protocol_id, signer_type, signer_name, signer_email, signature_data, 
             signature_hash, metadata, ip_address, user_agent)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
        ');
        
        $stmt->execute([
            $protocolId,
            $signerType,
            $name,
            $email ?: null,
            $signatureData['dataUrl'] ?? '',
            $signatureData['hash'] ?? '',
            json_encode($signatureData['metadata'] ?? []),
            $ip,
            $userAgent
        ]);
    }
    
    /**
     * Verifiziert eine Signatur
     */
    public function verify(): void
    {
        $protocolId = $_GET['protocol_id'] ?? '';
        if (!$protocolId) {
            echo json_encode(['valid' => false, 'message' => 'Kein Protokoll angegeben']);
            exit;
        }
        
        $pdo = Database::pdo();
        $stmt = $pdo->prepare('
            SELECT signature_tenant_data, signature_tenant_metadata, signature_tenant_timestamp,
                   signature_landlord_data, signature_landlord_metadata, signature_landlord_timestamp,
                   signature_witness_data, signature_witness_metadata, signature_witness_timestamp
            FROM protocols 
            WHERE id = ? AND deleted_at IS NULL
        ');
        $stmt->execute([$protocolId]);
        $protocol = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$protocol) {
            echo json_encode(['valid' => false, 'message' => 'Protokoll nicht gefunden']);
            exit;
        }
        
        $signatures = [];
        
        if ($protocol['signature_tenant_data']) {
            $signatures['tenant'] = [
                'signed' => true,
                'timestamp' => $protocol['signature_tenant_timestamp'],
                'metadata' => json_decode($protocol['signature_tenant_metadata'], true)
            ];
        }
        
        if ($protocol['signature_landlord_data']) {
            $signatures['landlord'] = [
                'signed' => true,
                'timestamp' => $protocol['signature_landlord_timestamp'],
                'metadata' => json_decode($protocol['signature_landlord_metadata'], true)
            ];
        }
        
        if ($protocol['signature_witness_data']) {
            $signatures['witness'] = [
                'signed' => true,
                'timestamp' => $protocol['signature_witness_timestamp'],
                'metadata' => json_decode($protocol['signature_witness_metadata'], true)
            ];
        }
        
        echo json_encode([
            'valid' => true,
            'signatures' => $signatures,
            'message' => 'Signaturen verifiziert'
        ]);
        exit;
    }
}
