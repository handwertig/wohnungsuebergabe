<?php
/**
 * Signatur-Integration Helper
 * Fügt die Signatur-Felder automatisch in Protokoll-Formulare ein
 */

// Diese Datei kann in die View.php integriert werden oder als separates Include

function renderSignatureSection(array $data = []): string {
    $signatures = $data['meta']['signatures'] ?? [];
    
    $html = '
    <!-- Elektronische Unterschriften Section -->
    <div class="accordion-item">
        <h2 class="accordion-header">
            <button class="accordion-button" type="button" data-bs-toggle="collapse" data-bs-target="#signatureSection">
                <i class="bi bi-pen me-2"></i> Elektronische Unterschriften
            </button>
        </h2>
        <div id="signatureSection" class="accordion-collapse collapse show">
            <div class="accordion-body">
                <div class="row">
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label class="form-label">Unterschrift Mieter/in</label>
                            <div id="tenantSignaturePreview" class="mb-2">';
    
    if (!empty($signatures['tenant'])) {
        $html .= '<img src="' . htmlspecialchars($signatures['tenant']) . '" style="max-width: 200px; border: 1px solid #ddd; border-radius: 4px;">';
    }
    
    $html .= '</div>
                            <button type="button" class="btn btn-outline-primary" id="tenantSignBtn" 
                                    data-bs-toggle="modal" data-bs-target="#signatureModal" 
                                    onclick="document.getElementById(\'signatureModal\').setAttribute(\'data-target\', \'tenant\')">
                                <i class="bi bi-pen"></i> ' . (empty($signatures['tenant']) ? 'Unterschrift hinzufügen' : 'Unterschrift ändern') . '
                            </button>
                            <input type="hidden" name="meta[signatures][tenant]" id="tenantSignature" value="' . htmlspecialchars($signatures['tenant'] ?? '') . '">
                            <div class="mt-2">
                                <input type="text" name="meta[signatures][tenant_name]" 
                                       class="form-control form-control-sm" 
                                       placeholder="Name des Mieters"
                                       value="' . htmlspecialchars($signatures['tenant_name'] ?? '') . '">
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label class="form-label">Unterschrift Vermieter/in</label>
                            <div id="landlordSignaturePreview" class="mb-2">';
    
    if (!empty($signatures['landlord'])) {
        $html .= '<img src="' . htmlspecialchars($signatures['landlord']) . '" style="max-width: 200px; border: 1px solid #ddd; border-radius: 4px;">';
    }
    
    $html .= '</div>
                            <button type="button" class="btn btn-outline-primary" id="landlordSignBtn"
                                    data-bs-toggle="modal" data-bs-target="#signatureModal"
                                    onclick="document.getElementById(\'signatureModal\').setAttribute(\'data-target\', \'landlord\')">
                                <i class="bi bi-pen"></i> ' . (empty($signatures['landlord']) ? 'Unterschrift hinzufügen' : 'Unterschrift ändern') . '
                            </button>
                            <input type="hidden" name="meta[signatures][landlord]" id="landlordSignature" value="' . htmlspecialchars($signatures['landlord'] ?? '') . '">
                            <div class="mt-2">
                                <input type="text" name="meta[signatures][landlord_name]" 
                                       class="form-control form-control-sm" 
                                       placeholder="Name des Vermieters"
                                       value="' . htmlspecialchars($signatures['landlord_name'] ?? '') . '">
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="alert alert-info mt-3">
                    <i class="bi bi-info-circle"></i> 
                    <strong>Rechtlicher Hinweis:</strong> Die elektronischen Unterschriften werden gemäß §126a BGB erstellt 
                    und sind für Wohnungsübergabeprotokolle rechtlich ausreichend. Zeitstempel und IP-Adresse werden automatisch erfasst.
                </div>
            </div>
        </div>
    </div>
    
    <!-- Signature Modal -->
    <div class="modal fade" id="signatureModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Unterschrift erfassen</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="text-center mb-3">
                        <canvas id="signatureCanvas" 
                                style="border: 2px solid #dee2e6; border-radius: 4px; cursor: crosshair; width: 100%; max-width: 700px;"
                                width="700" height="200">
                        </canvas>
                    </div>
                    <div class="text-muted small text-center">
                        <i class="bi bi-info-circle"></i> Bitte unterschreiben Sie mit der Maus oder dem Finger im Feld oben
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" id="clearSignature">
                        <i class="bi bi-eraser"></i> Löschen
                    </button>
                    <button type="button" class="btn btn-primary" id="saveSignature">
                        <i class="bi bi-check-lg"></i> Unterschrift speichern
                    </button>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Signature Pad Script einbinden -->
    <script src="/assets/signature-pad.js"></script>
    <script>
    document.addEventListener("DOMContentLoaded", function() {
        // Signature Pad initialisieren
        if (typeof initSignatureDialog === "function") {
            initSignatureDialog();
        }
        
        // Zeitstempel und IP beim Speichern hinzufügen
        const form = document.querySelector("form");
        if (form) {
            form.addEventListener("submit", function(e) {
                // Zeitstempel hinzufügen
                const timestamp = new Date().toISOString();
                const timestampInput = document.createElement("input");
                timestampInput.type = "hidden";
                timestampInput.name = "meta[signatures][timestamp]";
                timestampInput.value = timestamp;
                form.appendChild(timestampInput);
                
                // IP-Adresse wird serverseitig hinzugefügt
            });
        }
    });
    </script>';
    
    return $html;
}

// Automatisch IP-Adresse beim Speichern hinzufügen (serverseitig)
function addSignatureMetadata(array &$payload): void {
    if (!empty($payload['meta']['signatures'])) {
        // Zeitstempel
        if (empty($payload['meta']['signatures']['timestamp'])) {
            $payload['meta']['signatures']['timestamp'] = date('Y-m-d H:i:s');
        }
        
        // IP-Adresse
        $payload['meta']['signatures']['ip_address'] = $_SERVER['REMOTE_ADDR'] ?? 
                                                       $_SERVER['HTTP_X_FORWARDED_FOR'] ?? 
                                                       $_SERVER['HTTP_CLIENT_IP'] ?? 
                                                       'unknown';
    }
}
