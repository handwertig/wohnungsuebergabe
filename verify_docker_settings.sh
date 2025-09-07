#!/bin/bash

# Verifikations-Skript für Settings-Funktionalität (Docker für Mac)

echo "======================================"
echo "SETTINGS-FUNKTIONALITÄT VERIFIZIERUNG"  
echo "======================================"
echo ""

# Container-Namen ermitteln
APP_CONTAINER="wohnungsuebergabe-app-1"
DB_CONTAINER="wohnungsuebergabe-db-1"

# Alternative Container-Namen falls anders benannt
if ! docker ps | grep -q "$APP_CONTAINER"; then
    APP_CONTAINER=$(docker ps --format "table {{.Names}}" | grep app | head -1)
fi

if ! docker ps | grep -q "$DB_CONTAINER"; then
    DB_CONTAINER=$(docker ps --format "table {{.Names}}" | grep db | head -1)
fi

if [ -z "$APP_CONTAINER" ] || [ -z "$DB_CONTAINER" ]; then
    echo "❌ Docker-Container nicht gefunden!"
    echo "Bitte stellen Sie sicher, dass Docker läuft und die Container gestartet sind."
    echo ""
    echo "Versuche mit 'docker compose up -d' die Container zu starten..."
    exit 1
fi

echo "Verwende Container:"
echo "  App: $APP_CONTAINER"
echo "  DB:  $DB_CONTAINER"
echo ""

# Test 1: Datenbank-Struktur prüfen
echo "1. Prüfe Datenbank-Struktur..."
docker exec $DB_CONTAINER mysql -u root -proot wohnungsuebergabe -e "
    SELECT 'Settings-Tabelle:' as Info, COUNT(*) as Anzahl FROM settings;
    SELECT 'System-Log:' as Info, COUNT(*) as Anzahl FROM system_log;
" 2>/dev/null

if [ $? -ne 0 ]; then
    echo "⚠ Warnung: Konnte Datenbank nicht prüfen"
fi
echo ""

# Test 2: Settings-Funktionalität testen
echo "2. Teste Settings-Funktionalität..."
docker exec $APP_CONTAINER php -r "
require_once '/var/www/html/vendor/autoload.php';
use App\Settings;

\$testKey = 'verify_test_' . time();
\$testValue = 'Verifizierung um ' . date('H:i:s');

echo \"Test: Speichere '\$testKey' = '\$testValue'\n\";
\$result = Settings::set(\$testKey, \$testValue);
echo \"Speichern: \" . (\$result ? \"✓ Erfolgreich\n\" : \"✗ Fehlgeschlagen\n\");

Settings::clearCache();
\$retrieved = Settings::get(\$testKey);
echo \"Abrufen: \" . (\$retrieved === \$testValue ? \"✓ Wert korrekt\n\" : \"✗ Wert falsch\n\");
echo \"Gespeicherter Wert: '\$retrieved'\n\";
"

if [ $? -ne 0 ]; then
    echo "⚠ Warnung: Settings-Test möglicherweise fehlgeschlagen"
fi
echo ""

# Test 3: Web-Interface Link
echo "3. Web-Interface Test..."
echo "   Öffnen Sie im Browser: http://localhost:8080/settings/mail"
echo "   1. Ändern Sie einen Wert (z.B. SMTP-Host)"
echo "   2. Klicken Sie auf 'Speichern'"
echo "   3. Laden Sie die Seite neu"
echo "   4. Der Wert sollte gespeichert sein"
echo ""

# Test 4: System-Log prüfen
echo "4. Prüfe System-Log (letzte 3 Einträge)..."
docker exec $DB_CONTAINER mysql -u root -proot wohnungsuebergabe -e "
    SELECT action_type, LEFT(action_description, 50) as Beschreibung, created_at 
    FROM system_log 
    ORDER BY created_at DESC 
    LIMIT 3;
" 2>/dev/null

if [ $? -ne 0 ]; then
    echo "⚠ Warnung: System-Log konnte nicht geprüft werden"
fi
echo ""

# Test 5: Aktueller Settings-Status
echo "5. Aktuelle Mail-Settings..."
docker exec $APP_CONTAINER php -r "
require_once '/var/www/html/vendor/autoload.php';
use App\Settings;

echo \"smtp_host = \" . Settings::get('smtp_host', 'nicht gesetzt') . \"\n\";
echo \"smtp_port = \" . Settings::get('smtp_port', 'nicht gesetzt') . \"\n\";
echo \"smtp_from_name = \" . Settings::get('smtp_from_name', 'nicht gesetzt') . \"\n\";
echo \"smtp_from_email = \" . Settings::get('smtp_from_email', 'nicht gesetzt') . \"\n\";
" 2>/dev/null

echo ""
echo "======================================"
echo "✅ VERIFIZIERUNG ABGESCHLOSSEN"
echo "======================================"
echo ""
echo "Wenn alle Tests erfolgreich waren,"
echo "funktioniert die Settings-Speicherung korrekt!"
echo ""
echo "Bei Problemen führen Sie aus:"
echo "  ./docker_fix_settings.sh"
echo ""
