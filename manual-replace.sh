#!/bin/bash

echo "🔧 Korrigiere Stammdaten-Seite (manuelle Ersetzung)"
echo "==================================================="

# Backup
cp backend/src/Controllers/SettingsController.php backend/src/Controllers/SettingsController.php.backup_manual_$(date +%Y%m%d_%H%M%S)

echo "📝 Ersetze general() und generalSave() Methoden..."

# Erstelle eine vollständig neue SettingsController.php
# Dabei lesen wir die alte Datei bis zur general() Methode, 
# fügen unsere neue Methode ein, und überspringen die alte bis zur nächsten Methode

cat > /tmp/build_new_controller.sh << 'BUILDER_EOF'
#!/bin/bash

# Lies die originale Datei und baue eine neue auf
output_file="backend/src/Controllers/SettingsController.php.new"
original_file="backend/src/Controllers/SettingsController.php"

# Starte mit leerem Output
> "$output_file"

# Flag um zu tracken wo wir sind
in_general_method=false
in_general_save_method=false
brace_count=0

while IFS= read -r line; do
    # Prüfe ob wir am Anfang der general() Methode sind
    if [[ "$line" =~ "public function general" ]]; then
        # Schreibe unsere neue general() Methode
        cat >> "$output_file" << 'NEW_GENERAL_EOF'
    /* ---------- Stammdaten (Links zu Objekten, Eigentümer, Hausverwaltungen) ---------- */
    public function general(): void {
        Auth::requireAuth();
        
        $body = $this->tabs('general');
        $body .= '<div class="card"><div class="card-body"><h1 class="h6 mb-3">Stammdaten</h1>';
        $body .= '<p class="text-muted">Hier finden Sie die Verwaltung aller Stammdaten Ihrer Immobilien und Kontakte.</p>';
        $body .= '</div></div>';
        
        // Links zu den verschiedenen Stammdaten-Bereichen
        $body .= '<div class="row g-3 mt-3">';
        
        // Objekte
        $body .= '<div class="col-md-4"><div class="card"><div class="card-body">';
        $body .= '<div class="h6 text-primary"><i class="fas fa-building me-2"></i>Objekte</div>';
        $body .= '<p class="text-muted small mb-2">Immobilienobjekte und Wohneinheiten verwalten.</p>';
        $body .= '<a class="btn btn-outline-primary" href="/objects">Objekte öffnen</a>';
        $body .= '</div></div></div>';
        
        // Eigentümer
        $body .= '<div class="col-md-4"><div class="card"><div class="card-body">';
        $body .= '<div class="h6 text-success"><i class="fas fa-user-tie me-2"></i>Eigentümer</div>';
        $body .= '<p class="text-muted small mb-2">Eigentümer anlegen und bearbeiten.</p>';
        $body .= '<a class="btn btn-outline-success" href="/owners">Eigentümer öffnen</a>';
        $body .= '</div></div></div>';
        
        // Hausverwaltungen
        $body .= '<div class="col-md-4"><div class="card"><div class="card-body">';
        $body .= '<div class="h6 text-warning"><i class="fas fa-briefcase me-2"></i>Hausverwaltungen</div>';
        $body .= '<p class="text-muted small mb-2">Hausverwaltungen anlegen und bearbeiten.</p>';
        $body .= '<a class="btn btn-outline-warning" href="/managers">Hausverwaltungen öffnen</a>';
        $body .= '</div></div></div>';
        
        $body .= '</div>';
        
        View::render('Einstellungen – Stammdaten', $body);
    }

    public function generalSave(): void {
        Auth::requireAuth();
        // Stammdaten haben keine eigenen Felder mehr zum Speichern
        \App\Flash::add('info', 'Stammdaten werden über die jeweiligen Bereiche verwaltet.');
        header('Location: /settings');
    }
NEW_GENERAL_EOF
        
        in_general_method=true
        brace_count=0
        continue
    fi
    
    # Wenn wir in der general() Methode sind, zähle geschweifte Klammern
    if $in_general_method; then
        # Zähle öffnende Klammern
        open_braces=$(echo "$line" | tr -cd '{' | wc -c)
        # Zähle schließende Klammern  
        close_braces=$(echo "$line" | tr -cd '}' | wc -c)
        
        brace_count=$((brace_count + open_braces - close_braces))
        
        # Wenn wir bei generalSave() angekommen sind, setze Flag
        if [[ "$line" =~ "public function generalSave" ]]; then
            in_general_save_method=true
            brace_count=0
        fi
        
        # Wenn wir bei 0 Klammern sind und generalSave verarbeitet haben, sind wir fertig
        if $in_general_save_method && [[ $brace_count -le 0 ]] && [[ "$line" =~ "}" ]]; then
            in_general_method=false
            in_general_save_method=false
            continue
        fi
        
        # Überspringe alle Zeilen in den alten Methoden
        continue
    fi
    
    # Alle anderen Zeilen normal ausgeben
    echo "$line" >> "$output_file"
    
done < "$original_file"
BUILDER_EOF

chmod +x /tmp/build_new_controller.sh
/tmp/build_new_controller.sh

# Prüfe ob die neue Datei erstellt wurde
if [ -f "backend/src/Controllers/SettingsController.php.new" ]; then
    # Ersetze die originale Datei
    mv backend/src/Controllers/SettingsController.php.new backend/src/Controllers/SettingsController.php
    echo "✅ Neue SettingsController.php erstellt"
else
    echo "❌ Fehler beim Erstellen der neuen Datei"
    exit 1
fi

# Aufräumen
rm /tmp/build_new_controller.sh

# Syntax-Check falls PHP verfügbar
if command -v php >/dev/null 2>&1; then
    echo "🔍 Prüfe PHP-Syntax..."
    if php -l backend/src/Controllers/SettingsController.php; then
        echo "✅ PHP-Syntax ist korrekt!"
    else
        echo "❌ Syntax-Fehler! Stelle Backup wieder her..."
        mv backend/src/Controllers/SettingsController.php.backup_manual_$(date +%Y%m%d_%H%M%S) backend/src/Controllers/SettingsController.php
        exit 1
    fi
else
    echo "⚠️  PHP nicht verfügbar - überspringe Syntax-Prüfung"
fi

echo ""
echo "🎉 STAMMDATEN ERFOLGREICH KORRIGIERT!"
echo "====================================="
echo "✅ Änderungen:"
echo "   - ❌ Entfernt: Doppelte Firmenangaben aus Stammdaten"
echo "   - ➕ Hinzugefügt: Link zu Objekten (/objects)"
echo "   - ✅ Beibehalten: Links zu Eigentümer (/owners) und Hausverwaltungen (/managers)"
echo "   - 🎨 Icons und farbige Buttons für bessere UX"
echo ""
echo "📍 Neue Struktur:"
echo "   /settings (Stammdaten):"
echo "   ├── 🏢 Objekte → /objects"
echo "   ├── 👔 Eigentümer → /owners"  
echo "   └── 💼 Hausverwaltungen → /managers"
echo ""
echo "   /settings/users (Persönliche Daten):"
echo "   ├── Firma, Telefon, Adresse"
echo "   └── E-Mail, Passwort"
echo ""
echo "🧪 Testen Sie:"
echo "👉 http://127.0.0.1:8080/settings"
echo "👉 http://127.0.0.1:8080/settings/users"