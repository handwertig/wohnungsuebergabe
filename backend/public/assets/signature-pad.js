/**
 * Open-Source Signature Pad für Wohnungsübergabe
 * Basiert auf HTML5 Canvas - keine externen Abhängigkeiten
 */

class SignaturePad {
    constructor(canvasId, options = {}) {
        this.canvas = document.getElementById(canvasId);
        if (!this.canvas) {
            console.error('Canvas element not found:', canvasId);
            return;
        }
        
        this.ctx = this.canvas.getContext('2d');
        this.isDrawing = false;
        this.hasSignature = false;
        
        // Optionen
        this.strokeStyle = options.strokeStyle || '#000000';
        this.lineWidth = options.lineWidth || 2;
        this.backgroundColor = options.backgroundColor || '#ffffff';
        
        // Canvas Setup
        this.setupCanvas();
        this.attachEvents();
        this.clear();
    }
    
    setupCanvas() {
        // Retina Display Support
        const rect = this.canvas.getBoundingClientRect();
        const dpr = window.devicePixelRatio || 1;
        
        this.canvas.width = rect.width * dpr;
        this.canvas.height = rect.height * dpr;
        this.canvas.style.width = rect.width + 'px';
        this.canvas.style.height = rect.height + 'px';
        
        this.ctx.scale(dpr, dpr);
        
        // Stil setzen
        this.ctx.strokeStyle = this.strokeStyle;
        this.ctx.lineWidth = this.lineWidth;
        this.ctx.lineCap = 'round';
        this.ctx.lineJoin = 'round';
    }
    
    attachEvents() {
        // Mouse Events
        this.canvas.addEventListener('mousedown', this.startDrawing.bind(this));
        this.canvas.addEventListener('mousemove', this.draw.bind(this));
        this.canvas.addEventListener('mouseup', this.stopDrawing.bind(this));
        this.canvas.addEventListener('mouseout', this.stopDrawing.bind(this));
        
        // Touch Events für Mobile
        this.canvas.addEventListener('touchstart', this.handleTouch.bind(this), { passive: false });
        this.canvas.addEventListener('touchmove', this.handleTouch.bind(this), { passive: false });
        this.canvas.addEventListener('touchend', this.stopDrawing.bind(this));
        
        // Prevent scrolling when touching canvas
        this.canvas.addEventListener('touchstart', function(e) {
            e.preventDefault();
        }, { passive: false });
    }
    
    getPosition(e) {
        const rect = this.canvas.getBoundingClientRect();
        const x = e.clientX - rect.left;
        const y = e.clientY - rect.top;
        return { x, y };
    }
    
    startDrawing(e) {
        this.isDrawing = true;
        this.hasSignature = true;
        const pos = this.getPosition(e);
        this.ctx.beginPath();
        this.ctx.moveTo(pos.x, pos.y);
    }
    
    draw(e) {
        if (!this.isDrawing) return;
        
        const pos = this.getPosition(e);
        this.ctx.lineTo(pos.x, pos.y);
        this.ctx.stroke();
    }
    
    stopDrawing() {
        this.isDrawing = false;
    }
    
    handleTouch(e) {
        e.preventDefault();
        const touch = e.touches[0];
        if (!touch) return;
        
        const mouseEvent = new MouseEvent(e.type === 'touchstart' ? 'mousedown' : 
                                         e.type === 'touchmove' ? 'mousemove' : 'mouseup', {
            clientX: touch.clientX,
            clientY: touch.clientY
        });
        
        this.canvas.dispatchEvent(mouseEvent);
    }
    
    clear() {
        const rect = this.canvas.getBoundingClientRect();
        this.ctx.fillStyle = this.backgroundColor;
        this.ctx.fillRect(0, 0, rect.width, rect.height);
        this.hasSignature = false;
    }
    
    isEmpty() {
        return !this.hasSignature;
    }
    
    toDataURL(type = 'image/png') {
        return this.canvas.toDataURL(type);
    }
    
    fromDataURL(dataURL) {
        const img = new Image();
        img.onload = () => {
            this.clear();
            this.ctx.drawImage(img, 0, 0);
            this.hasSignature = true;
        };
        img.src = dataURL;
    }
}

// Signature Dialog Funktionen
function initSignatureDialog() {
    const modal = document.getElementById('signatureModal');
    if (!modal) return;
    
    // Signature Pad initialisieren
    const pad = new SignaturePad('signatureCanvas', {
        strokeStyle: '#000033',
        lineWidth: 2
    });
    
    // Clear Button
    const clearBtn = document.getElementById('clearSignature');
    if (clearBtn) {
        clearBtn.addEventListener('click', () => {
            pad.clear();
        });
    }
    
    // Save Button
    const saveBtn = document.getElementById('saveSignature');
    if (saveBtn) {
        saveBtn.addEventListener('click', () => {
            if (pad.isEmpty()) {
                alert('Bitte unterschreiben Sie zuerst.');
                return;
            }
            
            const dataURL = pad.toDataURL();
            const targetField = modal.getAttribute('data-target');
            
            // Speichere Signatur
            if (targetField === 'tenant') {
                document.getElementById('tenantSignature').value = dataURL;
                document.getElementById('tenantSignaturePreview').innerHTML = 
                    `<img src="${dataURL}" style="max-width: 200px; border: 1px solid #ddd; border-radius: 4px;">`;
                document.getElementById('tenantSignBtn').textContent = 'Unterschrift ändern';
            } else if (targetField === 'landlord') {
                document.getElementById('landlordSignature').value = dataURL;
                document.getElementById('landlordSignaturePreview').innerHTML = 
                    `<img src="${dataURL}" style="max-width: 200px; border: 1px solid #ddd; border-radius: 4px;">`;
                document.getElementById('landlordSignBtn').textContent = 'Unterschrift ändern';
            }
            
            // Modal schließen
            const bsModal = bootstrap.Modal.getInstance(modal);
            bsModal.hide();
            pad.clear();
        });
    }
}

// Signature Felder zu Protokoll hinzufügen
function addSignatureFields() {
    const container = document.querySelector('.protocol-signatures');
    if (!container) return;
    
    const html = `
        <div class="card mt-4">
            <div class="card-header">
                <h5 class="mb-0">Elektronische Unterschriften</h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6">
                        <label class="form-label">Unterschrift Mieter/in</label>
                        <div id="tenantSignaturePreview" class="mb-2"></div>
                        <button type="button" class="btn btn-outline-primary" id="tenantSignBtn" 
                                data-bs-toggle="modal" data-bs-target="#signatureModal" 
                                onclick="document.getElementById('signatureModal').setAttribute('data-target', 'tenant')">
                            <i class="bi bi-pen"></i> Unterschrift hinzufügen
                        </button>
                        <input type="hidden" name="meta[signatures][tenant]" id="tenantSignature">
                        <div class="form-text">Name: <input type="text" name="meta[signatures][tenant_name]" 
                                                           class="form-control form-control-sm mt-1" 
                                                           placeholder="Vor- und Nachname"></div>
                    </div>
                    
                    <div class="col-md-6">
                        <label class="form-label">Unterschrift Vermieter/in</label>
                        <div id="landlordSignaturePreview" class="mb-2"></div>
                        <button type="button" class="btn btn-outline-primary" id="landlordSignBtn"
                                data-bs-toggle="modal" data-bs-target="#signatureModal"
                                onclick="document.getElementById('signatureModal').setAttribute('data-target', 'landlord')">
                            <i class="bi bi-pen"></i> Unterschrift hinzufügen
                        </button>
                        <input type="hidden" name="meta[signatures][landlord]" id="landlordSignature">
                        <div class="form-text">Name: <input type="text" name="meta[signatures][landlord_name]" 
                                                           class="form-control form-control-sm mt-1" 
                                                           placeholder="Vor- und Nachname"></div>
                    </div>
                </div>
                
                <div class="alert alert-info mt-3">
                    <i class="bi bi-info-circle"></i> 
                    <strong>Hinweis:</strong> Die elektronischen Unterschriften werden zusammen mit Zeitstempel 
                    und IP-Adresse gespeichert und sind für Wohnungsübergabeprotokolle rechtlich ausreichend.
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
                                    style="border: 2px solid #dee2e6; border-radius: 4px; cursor: crosshair;"
                                    width="700" height="200">
                            </canvas>
                        </div>
                        <div class="text-muted small text-center">
                            Bitte unterschreiben Sie mit der Maus oder dem Finger im Feld oben
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
    `;
    
    container.innerHTML = html;
    
    // Init nach DOM Update
    setTimeout(initSignatureDialog, 100);
}

// Auto-Init wenn DOM ready
document.addEventListener('DOMContentLoaded', function() {
    // Prüfe ob wir auf der Protokoll-Seite sind
    if (window.location.pathname.includes('/protocols/wizard/') || 
        window.location.pathname.includes('/protocols/edit')) {
        
        // Füge Signature Container hinzu falls nicht vorhanden
        const metaSection = document.querySelector('.accordion-item:last-child .accordion-body');
        if (metaSection && !document.querySelector('.protocol-signatures')) {
            const sigContainer = document.createElement('div');
            sigContainer.className = 'protocol-signatures';
            metaSection.appendChild(sigContainer);
            addSignatureFields();
        }
    }
});
