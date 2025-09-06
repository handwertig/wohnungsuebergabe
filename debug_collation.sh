#!/bin/bash

# debug_collation.sh - Analysiert Kollationsprobleme in der Datenbank

echo "🔍 Kollations-Analyse"
echo "====================="
echo ""

# Prüfe ob Docker läuft
if ! docker compose ps >/dev/null 2>&1; then
    echo "❌ Docker Compose ist nicht verfügbar"
    exit 1
fi

echo "📊 Datenbank-Kollationen:"
echo "-------------------------"

# Zeige alle Tabellen mit ihren Kollationen
docker compose exec -T db mariadb -uroot -proot -e "
SELECT 
    table_name, 
    table_collation,
    engine
FROM information_schema.tables 
WHERE table_schema = 'app' 
ORDER BY table_name;
" app

echo ""
echo "🔍 Spalten-Kollationen für kritische Tabellen:"
echo "----------------------------------------------"

# Prüfe spezifische Spalten
for table in protocols protocol_versions protocol_pdfs; do
    echo ""
    echo "📋 Tabelle: $table"
    docker compose exec -T db mariadb -uroot -proot -e "
    SELECT 
        column_name, 
        data_type, 
        character_set_name, 
        collation_name 
    FROM information_schema.columns 
    WHERE table_schema = 'app' 
    AND table_name = '$table' 
    AND column_name LIKE '%protocol_id%'
    ORDER BY ordinal_position;
    " app 2>/dev/null || echo "   ❌ Tabelle '$table' existiert nicht"
done

echo ""
echo "🔍 Views und ihre Definition:"
echo "----------------------------"

docker compose exec -T db mariadb -uroot -proot -e "
SELECT 
    table_name, 
    view_definition 
FROM information_schema.views 
WHERE table_schema = 'app' 
AND table_name LIKE '%protocol%';
" app

echo ""
echo "🧪 Test-Abfrage für Kollationsfehler:"
echo "-------------------------------------"

# Versuche die problematische Abfrage
docker compose exec -T db mariadb -uroot -proot -e "
SELECT pv.protocol_id, pv.version_no, pv.created_at 
FROM protocol_versions pv 
WHERE pv.protocol_id = 'test-id' 
ORDER BY pv.protocol_id, pv.version_no DESC 
LIMIT 1;
" app 2>&1 || echo "   ❌ Abfrage fehlgeschlagen (erwartetes Verhalten)"

echo ""
echo "✅ Analyse abgeschlossen"
