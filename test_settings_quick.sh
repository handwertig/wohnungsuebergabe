#!/bin/bash

# Quick-Test für Settings-Funktionalität

echo "==========================="
echo "SETTINGS QUICK-TEST"
echo "==========================="
echo ""

# Container finden
APP=$(docker ps --format "table {{.Names}}" | grep app | head -1)
DB=$(docker ps --format "table {{.Names}}" | grep db | head -1)

if [ -z "$APP" ] || [ -z "$DB" ]; then
    echo "❌ Docker-Container nicht gefunden!"
    echo "Starten Sie Docker Desktop und führen Sie aus:"
    echo "  cd /Users/berndgundlach/Documents/Docker/wohnungsuebergabe"
    echo "  docker compose up -d"
    exit 1
fi

# Test
echo "Test Settings-Speicherung..."
docker exec $APP php -r "
require_once '/var/www/html/vendor/autoload.php';
use App\Settings;
\$test = 'test_' . time();
\$val = 'OK ' . date('H:i:s');
Settings::set(\$test, \$val);
Settings::clearCache();
\$get = Settings::get(\$test);
echo \$get === \$val ? \"✅ Settings funktionieren!\n\" : \"❌ Settings fehlerhaft!\n\";
echo \"Gespeichert: \$val\n\";
echo \"Abgerufen:   \$get\n\";
"

echo ""
echo "Aktuelle SMTP-Einstellungen:"
docker exec $DB mysql -u root -proot wohnungsuebergabe -e "
SELECT \`key\`, \`value\` FROM settings WHERE \`key\` LIKE 'smtp_%' LIMIT 5;
" 2>/dev/null

echo ""
echo "Test abgeschlossen!"
echo "Öffnen Sie: http://localhost:8080/settings/mail"
