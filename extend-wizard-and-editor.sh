#!/bin/bash

echo "🔧 Erweitere Wizard Schritt 4 und Editor um fehlende Felder"
echo "==========================================================="

# Backup
cp backend/src/Controllers/ProtocolWizardController.php backend/src/Controllers/ProtocolWizardController.php.backup_fields_$(date +%Y%m%d_%H%M%S)
cp backend/src/Controllers/ProtocolsController.php backend/src/Controllers/ProtocolsController.php.backup_fields_$(date +%Y%m%d_%H%M%S)

echo "📝 1. Erweitere ProtocolWizardController Schritt 4..."

# Erweiterte ProtocolWizardController mit den zusätzlichen Feldern in Schritt 4
python3 << 'PYTHON_WIZARD'
import re

# Wizard Controller lesen
with open('backend/src/Controllers/ProtocolWizardController.php', 'r') as f:
    content = f.read()

# Suche den Schritt 4 Bereich und erweitere ihn
# Finde den Bereich nach den bestehenden Einwilligungen
pattern = r'(\$html \.= \'<div class="col-md-4"><div class="form-check"><input class="form-check-input" type="checkbox" name="meta\[consents\]\[disposal\]".*?</div></div>\';)'
replacement = r'''\1

            // Zusätzliche Felder für Schritt 4
            $html .= '<div class="col-12"><hr class="my-4"></div>';
            $html .= '<div class="col-12"><h6>Weitere Angaben</h6></div>';
            
            // Mietkaution zurückzahlen
            $html .= '<div class="col-md-6">';
            $html .= '<div class="form-check">';
            $html .= '<input class="form-check-input" type="checkbox" name="meta[deposit_refund]" value="1" '.(!empty($meta['deposit_refund'])?'checked':'').'>';
            $html .= '<label class="form-check-label">Kann die Mietkaution zurückbezahlt werden?</label>';
            $html .= '</div></div>';
            
            // Anmerkungen an Hausverwaltung
            $html .= '<div class="col-12">';
            $html .= '<label class="form-label">Anmerkungen an die Hausverwaltung</label>';
            $html .= '<textarea class="form-control" name="meta[notes_management]" rows="3" placeholder="Besondere Hinweise oder Anmerkungen für die Hausverwaltung...">'.htmlspecialchars((string)($meta['notes_management'] ?? '')).'</textarea>';
            $html .= '</div>';
            
            // Anmerkungen an Eigentümer
            $html .= '<div class="col-12">';
            $html .= '<label class="form-label">Anmerkungen an den Eigentümer</label>';
            $html .= '<textarea class="form-control" name="meta[notes_owner]" rows="3" placeholder="Besondere Hinweise oder Anmerkungen für den Eigentümer...">'.htmlspecialchars((string)($meta['notes_owner'] ?? '')).'</textarea>';
            $html .= '</div>';'''

content = re.sub(pattern, replacement, content, flags=re.DOTALL)

# Datei schreiben
with open('backend/src/Controllers/ProtocolWizardController.php', 'w') as f:
    f.write(content)
PYTHON_WIZARD

echo "✅ ProtocolWizardController Schritt 4 erweitert"

echo "📝 2. Erweitere ProtocolsController Editor..."

# Erweitere den Editor um alle fehlenden Zählerfelder
python3 << 'PYTHON_EDITOR'
import re

# Editor Controller lesen
with open('backend/src/Controllers/ProtocolsController.php', 'r') as f:
    content = f.read()

# Suche den Zähler-Tab und erweitere ihn komplett
old_meters_section = r'(<!-- Tab: Zähler -->.*?<div class="col-md-6">.*?<input class="form-control" name="meters\[hot_kitchen_reading\]".*?</div>)'
new_meters_section = '''<!-- Tab: Zähler -->
            <div class="tab-pane fade" id="tab-zaehler">
              <div class="row g-3">
                <!-- Stromzähler -->
                <div class="col-12"><h6>Stromzähler</h6></div>
                <div class="col-md-6">
                  <label class="form-label">Strom Wohnung - Zählernummer</label>
                  <input class="form-control" name="meters[power_unit_number]" value="<?= $h((string)($meters['power_unit_number'] ?? '')) ?>">
                </div>
                <div class="col-md-6">
                  <label class="form-label">Strom Wohnung - Zählerstand</label>
                  <input class="form-control" name="meters[power_unit_reading]" value="<?= $h((string)($meters['power_unit_reading'] ?? '')) ?>">
                </div>
                <div class="col-md-6">
                  <label class="form-label">Strom Haus Allgemein - Zählernummer</label>
                  <input class="form-control" name="meters[power_house_number]" value="<?= $h((string)($meters['power_house_number'] ?? '')) ?>">
                </div>
                <div class="col-md-6">
                  <label class="form-label">Strom Haus Allgemein - Zählerstand</label>
                  <input class="form-control" name="meters[power_house_reading]" value="<?= $h((string)($meters['power_house_reading'] ?? '')) ?>">
                </div>
                
                <!-- Gaszähler -->
                <div class="col-12"><h6 class="mt-3">Gaszähler</h6></div>
                <div class="col-md-6">
                  <label class="form-label">Gas Wohnung - Zählernummer</label>
                  <input class="form-control" name="meters[gas_unit_number]" value="<?= $h((string)($meters['gas_unit_number'] ?? '')) ?>">
                </div>
                <div class="col-md-6">
                  <label class="form-label">Gas Wohnung - Zählerstand</label>
                  <input class="form-control" name="meters[gas_unit_reading]" value="<?= $h((string)($meters['gas_unit_reading'] ?? '')) ?>">
                </div>
                <div class="col-md-6">
                  <label class="form-label">Gas Haus Allgemein - Zählernummer</label>
                  <input class="form-control" name="meters[gas_house_number]" value="<?= $h((string)($meters['gas_house_number'] ?? '')) ?>">
                </div>
                <div class="col-md-6">
                  <label class="form-label">Gas Haus Allgemein - Zählerstand</label>
                  <input class="form-control" name="meters[gas_house_reading]" value="<?= $h((string)($meters['gas_house_reading'] ?? '')) ?>">
                </div>
                
                <!-- Wasserzähler -->
                <div class="col-12"><h6 class="mt-3">Wasserzähler</h6></div>
                <div class="col-md-6">
                  <label class="form-label">Kaltwasser Küche (blau) - Zählernummer</label>
                  <input class="form-control" name="meters[cold_kitchen_number]" value="<?= $h((string)($meters['cold_kitchen_number'] ?? '')) ?>">
                </div>
                <div class="col-md-6">
                  <label class="form-label">Kaltwasser Küche (blau) - Zählerstand</label>
                  <input class="form-control" name="meters[cold_kitchen_reading]" value="<?= $h((string)($meters['cold_kitchen_reading'] ?? '')) ?>">
                </div>
                <div class="col-md-6">
                  <label class="form-label">Warmwasser Küche (rot) - Zählernummer</label>
                  <input class="form-control" name="meters[hot_kitchen_number]" value="<?= $h((string)($meters['hot_kitchen_number'] ?? '')) ?>">
                </div>
                <div class="col-md-6">
                  <label class="form-label">Warmwasser Küche (rot) - Zählerstand</label>
                  <input class="form-control" name="meters[hot_kitchen_reading]" value="<?= $h((string)($meters['hot_kitchen_reading'] ?? '')) ?>">
                </div>
                <div class="col-md-6">
                  <label class="form-label">Kaltwasser Badezimmer (blau) - Zählernummer</label>
                  <input class="form-control" name="meters[cold_bathroom_number]" value="<?= $h((string)($meters['cold_bathroom_number'] ?? '')) ?>">
                </div>
                <div class="col-md-6">
                  <label class="form-label">Kaltwasser Badezimmer (blau) - Zählerstand</label>
                  <input class="form-control" name="meters[cold_bathroom_reading]" value="<?= $h((string)($meters['cold_bathroom_reading'] ?? '')) ?>">
                </div>
                <div class="col-md-6">
                  <label class="form-label">Warmwasser Badezimmer (rot) - Zählernummer</label>
                  <input class="form-control" name="meters[hot_bathroom_number]" value="<?= $h((string)($meters['hot_bathroom_number'] ?? '')) ?>">
                </div>
                <div class="col-md-6">
                  <label class="form-label">Warmwasser Badezimmer (rot) - Zählerstand</label>
                  <input class="form-control" name="meters[hot_bathroom_reading]" value="<?= $h((string)($meters['hot_bathroom_reading'] ?? '')) ?>">
                </div>
                <div class="col-md-6">
                  <label class="form-label">Wasserzähler Waschmaschine (blau) - Zählernummer</label>
                  <input class="form-control" name="meters[cold_washing_number]" value="<?= $h((string)($meters['cold_washing_number'] ?? '')) ?>">
                </div>
                <div class="col-md-6">
                  <label class="form-label">Wasserzähler Waschmaschine (blau) - Zählerstand</label>
                  <input class="form-control" name="meters[cold_washing_reading]" value="<?= $h((string)($meters['cold_washing_reading'] ?? '')) ?>">
                </div>
                
                <!-- Wärmemengenzähler -->
                <div class="col-12"><h6 class="mt-3">Wärmemengenzähler</h6></div>
                <div class="col-md-6">
                  <label class="form-label">WMZ 1 - Zählernummer</label>
                  <input class="form-control" name="meters[wmz1_number]" value="<?= $h((string)($meters['wmz1_number'] ?? '')) ?>">
                </div>
                <div class="col-md-6">
                  <label class="form-label">WMZ 1 - Zählerstand</label>
                  <input class="form-control" name="meters[wmz1_reading]" value="<?= $h((string)($meters['wmz1_reading'] ?? '')) ?>">
                </div>
                <div class="col-md-6">
                  <label class="form-label">WMZ 2 - Zählernummer</label>
                  <input class="form-control" name="meters[wmz2_number]" value="<?= $h((string)($meters['wmz2_number'] ?? '')) ?>">
                </div>
                <div class="col-md-6">
                  <label class="form-label">WMZ 2 - Zählerstand</label>
                  <input class="form-control" name="meters[wmz2_reading]" value="<?= $h((string)($meters['wmz2_reading'] ?? '')) ?>">
                </div>
              </div>
            </div>'''

content = re.sub(old_meters_section, new_meters_section, content, flags=re.DOTALL)

# Erweitere auch den Schlüssel & Meta Tab um die neuen Wizard-Felder
meta_section_pattern = r'(<div class="col-12"><label class="form-label">Neue Meldeadresse</label>.*?</div>)'
meta_section_replacement = r'''\1
                
                <!-- Zusätzliche Wizard-Felder -->
                <div class="col-12"><h6 class="mt-3">Zusätzliche Angaben</h6></div>
                <div class="col-md-6">
                  <div class="form-check">
                    <input class="form-check-input" type="checkbox" name="meta[deposit_refund]" value="1"<?= !empty($meta['deposit_refund']) ? ' checked' : '' ?>>
                    <label class="form-check-label">Kann die Mietkaution zurückbezahlt werden?</label>
                  </div>
                </div>
                <div class="col-12">
                  <label class="form-label">Anmerkungen an die Hausverwaltung</label>
                  <textarea class="form-control" name="meta[notes_management]" rows="3"><?= $h((string)($meta['notes_management'] ?? '')) ?></textarea>
                </div>
                <div class="col-12">
                  <label class="form-label">Anmerkungen an den Eigentümer</label>
                  <textarea class="form-control" name="meta[notes_owner]" rows="3"><?= $h((string)($meta['notes_owner'] ?? '')) ?></textarea>
                </div>'''

content = re.sub(meta_section_pattern, meta_section_replacement, content, flags=re.DOTALL)

# Datei schreiben
with open('backend/src/Controllers/ProtocolsController.php', 'w') as f:
    f.write(content)
PYTHON_EDITOR

echo "✅ ProtocolsController Editor erweitert"

# Syntax prüfen falls PHP verfügbar
if command -v php >/dev/null 2>&1; then
    echo "🔍 Prüfe PHP-Syntax..."
    if php -l backend/src/Controllers/ProtocolWizardController.php && php -l backend/src/Controllers/ProtocolsController.php; then
        echo "✅ PHP-Syntax ist korrekt!"
    else
        echo "❌ Syntax-Fehler! Stelle Backups wieder her..."
        cp backend/src/Controllers/ProtocolWizardController.php.backup_fields_$(date +%Y%m%d_%H%M%S) backend/src/Controllers/ProtocolWizardController.php
        cp backend/src/Controllers/ProtocolsController.php.backup_fields_$(date +%Y%m%d_%H%M%S) backend/src/Controllers/ProtocolsController.php
        exit 1
    fi
else
    echo "⚠️  PHP nicht verfügbar - überspringe Syntax-Prüfung"
fi

echo ""
echo "🎉 WIZARD UND EDITOR ERFOLGREICH ERWEITERT!"
echo "==========================================="
echo "✅ Wizard Schritt 4 - Neue Felder:"
echo "   ☑️  Checkbox: Kann die Mietkaution zurückbezahlt werden?"
echo "   📝 Textarea: Anmerkungen an die Hausverwaltung"
echo "   📝 Textarea: Anmerkungen an den Eigentümer"
echo ""
echo "✅ Editor - Erweiterte Zählerfelder:"
echo "   ⚡ Strom: Wohnung + Haus Allgemein (je Nummer & Stand)"
echo "   🔥 Gas: Wohnung + Haus Allgemein (je Nummer & Stand)"  
echo "   💧 Wasser: Küche, Badezimmer, Waschmaschine (je kalt/warm, Nummer & Stand)"
echo "   🌡️  WMZ: 2 Wärmemengenzähler (je Nummer & Stand)"
echo "   📋 Meta: Kautionsrückzahlung, Anmerkungen (Hausverwaltung, Eigentümer)"
echo ""
echo "🎯 Vollständige Feldliste im Editor:"
echo "   📋 Kopf: Art, Mieter, Eigentümer, Hausverwaltung, Bemerkungen"
echo "   🏠 Räume: Name, Zustand, Abnahme, Geruch (dynamisch)"
echo "   ⚡ Zähler: Alle Strom-, Gas-, Wasser- und WMZ-Felder"
echo "   🔑 Schlüssel & Meta: Schlüssel + alle Wizard-Felder"
echo ""
echo "🧪 Testen Sie:"
echo "👉 http://127.0.0.1:8080/protocols/wizard?step=4"
echo "   - Neue Felder sollten am Ende von Schritt 4 erscheinen"
echo "👉 http://127.0.0.1:8080/protocols/edit?id=XXX"
echo "   - Zähler-Tab sollte alle Felder haben"
echo "   - Schlüssel & Meta-Tab mit Zusatzfeldern"