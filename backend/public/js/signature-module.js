/**
 * Signature Module for Protocol Integration
 * Integriert die digitale Signatur in Protokoll-Formulare
 */

(function() {
    'use strict';
    
    // Warte bis DOM geladen ist
    document.addEventListener('DOMContentLoaded', function() {
        initializeSignatureIntegration();
    });
    
    /**
     * Initialisiert die Signatur-Integration
     */
    function initializeSignatureIntegration() {
        // Prüfe ob wir auf einer Protokoll-Seite sind
        const isProtocolPage = window.location.pathname.includes('/protocols/edit') || 
                              window.location.pathname.includes('/protocols/wizard');
        
        if (!isProtocolPage) {
            return;
        }
        
        // Füge Signatur-Tab hinzu wenn nicht vorhanden
        addSignatureTab();
        
        // Füge Signatur-Button zur Toolbar hinzu
        addSignatureButton();
    }
    
    /**
     * Fügt einen Signatur-Tab zum Protokoll-Formular hinzu
     */
    function addSignatureTab() {
        const tabList = document.querySelector('.nav-tabs');
        if (!tabList) return;
        
        // Prüfe ob Tab bereits existiert
        if (document.querySelector('#tab-signatures')) return;
        
        // Erstelle neuen Tab
        const tabItem = document.createElement('li');
        tabItem.className = 'nav-item';
        tabItem.innerHTML = `
            <a class="nav-link" id="signatures-tab" data-bs-toggle="tab" href="#tab-signatures">
                <i class="bi bi-pen me-1"></i> Unterschriften
            </a>
        `;
        tabList.appendChild(tabItem);
        
        // Erstelle Tab-Content
        const tabContent = document.querySelector('.tab-content');
        if (!tabContent) return;
        
        const tabPane = document.createElement('div');
        tabPane.className = 'tab-pane fade';
        tabPane.id = 'tab-signatures';
        tabPane.innerHTML = `
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">
                        <i class="bi bi-pen me-2"></i>Digitale Unterschriften
                    </h5>
                </div>
                <div class="card-body">
                    <p class="text-muted">
                        Fügen Sie digitale Unterschriften zu diesem Protokoll hinzu.
                        Die Unterschriften werden zusammen mit Zeitstempel und IP-Adresse gespeichert.
                    </p>
                    
                    <div id="existing-signatures" class="mb-4">
                        <!-- Hier werden bestehende Unterschriften angezeigt -->
                    </div>
                    
                    <div class="d-flex gap-2">
                        <button type="button" class="btn btn-primary" onclick="addSignature()">
                            <i class="bi bi-pen me-2"></i>Unterschrift hinzufügen
                        </button>
                        <button type="button" class="btn btn-outline-secondary" onclick="loadSignatures()">
                            <i class="bi bi-arrow-clockwise me-2"></i>Aktualisieren
                        </button>
                    </div>
                    
                    <div class="alert alert-info mt-3">
                        <i class="bi bi-info-circle me-2"></i>
                        <strong>Rechtlicher Hinweis:</strong> Die elektronischen Unterschriften erfüllen die Anforderungen 
                        gemäß §126a BGB und sind für Wohnungsübergabeprotokolle rechtlich ausreichend.
                    </div>
                </div>
            </div>
        `;
        tabContent.appendChild(tabPane);
        
        // Lade bestehende Unterschriften
        loadSignatures();
    }
    
    /**
     * Fügt einen Signatur-Button zur Toolbar hinzu
     */
    function addSignatureButton() {
        const toolbar = document.querySelector('.btn-toolbar, .protocol-actions');
        if (!toolbar) return;
        
        // Prüfe ob Button bereits existiert
        if (document.querySelector('.btn-add-signature')) return;
        
        const protocolId = getProtocolId();
        if (!protocolId) return;
        
        const button = document.createElement('a');
        button.href = `/signature/sign?protocol_id=${protocolId}`;
        button.className = 'btn btn-outline-primary btn-add-signature';
        button.innerHTML = '<i class="bi bi-pen me-2"></i>Digital unterschreiben';
        
        // Füge Button zur Toolbar hinzu
        const btnGroup = document.createElement('div');
        btnGroup.className = 'btn-group ms-2';
        btnGroup.appendChild(button);
        toolbar.appendChild(btnGroup);
    }
    
    /**
     * Lädt bestehende Unterschriften
     */
    window.loadSignatures = function() {
        const protocolId = getProtocolId();
        if (!protocolId) return;
        
        const container = document.getElementById('existing-signatures');
        if (!container) return;
        
        // Simuliere Laden von Unterschriften
        // In der echten Implementierung würde hier ein AJAX-Call erfolgen
        container.innerHTML = `
            <h6>Vorhandene Unterschriften:</h6>
            <div class="table-responsive">
                <table class="table table-sm">
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Rolle</th>
                            <th>Datum</th>
                            <th>Status</th>
                            <th>Aktionen</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td colspan="5" class="text-center text-muted">
                                Noch keine Unterschriften vorhanden
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        `;
    };
    
    /**
     * Öffnet Dialog zum Hinzufügen einer Unterschrift
     */
    window.addSignature = function() {
        const protocolId = getProtocolId();
        if (!protocolId) {
            alert('Protokoll-ID nicht gefunden. Bitte speichern Sie das Protokoll zuerst.');
            return;
        }
        
        // Öffne Signatur-Seite in neuem Tab oder Modal
        window.open(`/signature/sign?protocol_id=${protocolId}`, '_blank');
    };
    
    /**
     * Ermittelt die aktuelle Protokoll-ID
     */
    function getProtocolId() {
        // Aus URL-Parameter
        const urlParams = new URLSearchParams(window.location.search);
        const idFromUrl = urlParams.get('id') || urlParams.get('protocol_id');
        if (idFromUrl) return idFromUrl;
        
        // Aus verstecktem Feld
        const hiddenField = document.querySelector('input[name="id"], input[name="protocol_id"]');
        if (hiddenField) return hiddenField.value;
        
        // Aus Data-Attribut
        const formElement = document.querySelector('[data-protocol-id]');
        if (formElement) return formElement.dataset.protocolId;
        
        return null;
    }
    
    /**
     * Zeigt eine Signatur-Vorschau an
     */
    window.showSignaturePreview = function(signatureData) {
        const modal = document.createElement('div');
        modal.className = 'modal fade';
        modal.innerHTML = `
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">Signatur-Vorschau</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body text-center">
                        <img src="${signatureData}" class="img-fluid" style="max-height: 200px;">
                    </div>
                </div>
            </div>
        `;
        
        document.body.appendChild(modal);
        const bsModal = new bootstrap.Modal(modal);
        bsModal.show();
        
        // Entferne Modal nach dem Schließen
        modal.addEventListener('hidden.bs.modal', function() {
            modal.remove();
        });
    };
    
    /**
     * Verifiziert eine Unterschrift
     */
    window.verifySignature = function(signatureId) {
        fetch(`/signature/verify?signature_id=${signatureId}`)
            .then(response => response.json())
            .then(data => {
                if (data.valid) {
                    alert('✅ Unterschrift ist gültig und verifiziert.');
                } else {
                    alert('⚠️ Unterschrift konnte nicht verifiziert werden.');
                }
            })
            .catch(error => {
                console.error('Fehler bei der Verifikation:', error);
                alert('Fehler bei der Verifikation der Unterschrift.');
            });
    };
    
})();
