#!/bin/bash

# Verifikations-Skript für Settings-Funktionalität

echo "======================================"
echo "SETTINGS-FUNKTIONALITÄT VERIFIZIERUNG"  
echo "======================================"
echo ""

# Test 1: Datenbank-Struktur prüfen
echo "1. Prüfe Datenbank-Struktur..."
docker-compose exec db mysql -u root -proot wohnungsuebergabe -e "
    SELECT 'Settings-Tabelle:' as Info, COUNT(*) as Anzahl FROM settings;
    SELECT 'System-Log:' as Info, COUNT(*) as Anzahl FROM system_log;
" 2>/dev/null

echo ""

# Test 2: Settings-Funktionalität testen
echo "2. Teste Settings-Funktionalität..."
docker-compose exec app php -r "
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
echo "4. Prüfe System-Log..."
docker-compose exec db mysql -u root -proot wohnungsuebergabe -e "
    SELECT action_type, LEFT(action_description, 50) as Beschreibung, created_at 
    FROM system_log 
    ORDER BY created_at DESC 
    LIMIT 3;
" 2>/dev/null

echo ""
echo "======================================"
echo "✅ VERIFIZIERUNG ABGESCHLOSSEN"
echo "======================================"
echo ""
echo "Wenn alle Tests erfolgreich waren,"
echo "funktioniert die Settings-Speicherung korrekt!"
echo ""
