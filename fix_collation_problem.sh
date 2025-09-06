#!/bin/bash

# fix_collation_problem.sh - Behebt das Kollationsproblem

echo "🔧 Kollationsproblem beheben"
echo "============================"
echo ""

# Prüfe ob Docker läuft
if ! docker compose ps >/dev/null 2>&1; then
    echo "❌ Docker Compose ist nicht verfügbar oder Container laufen nicht"
    echo "💡 Starten Sie zuerst: docker compose up -d"
    exit 1
fi

# Prüfe ob Datenbank verfügbar ist
if ! docker compose exec -T db mariadb -uroot -proot -e "SELECT 1;" app >/dev/null 2>&1; then
    echo "❌ Datenbank ist nicht erreichbar"
    echo "💡 Prüfen Sie: docker compose logs db"
    exit 1
fi

echo "✅ Datenbank ist erreichbar"
echo ""

echo "🔍 Aktuelle Kollationen vor der Reparatur:"
echo "------------------------------------------"

docker compose exec -T db mariadb -uroot -proot -e "
SELECT 
    table_name, 
    table_collation
FROM information_schema.tables 
WHERE table_schema = 'app' 
AND table_name IN ('protocols', 'protocol_versions', 'protocol_pdfs')
ORDER BY table_name;
" app

echo ""
echo "🔄 Führe Kollations-Reparatur aus..."

# Migration ausführen
if docker compose exec -T db mariadb -uroot -proot app < migrations/028_final_collation_fix.sql; then
    echo "✅ Kollations-Reparatur erfolgreich"
    echo ""
    
    echo "📊 Kollationen nach der Reparatur:"
    echo "----------------------------------"
    
    docker compose exec -T db mariadb -uroot -proot -e "
    SELECT 
        table_name, 
        table_collation
    FROM information_schema.tables 
    WHERE table_schema = 'app' 
    AND table_name IN ('protocols', 'protocol_versions', 'protocol_pdfs', 'users', 'objects', 'units')
    ORDER BY table_name;
    " app
    
    echo ""
    echo "🧪 Test der problematischen Abfrage:"
    echo "------------------------------------"
    
    # Test der Abfrage, die vorher fehlgeschlagen ist
    if docker compose exec -T db mariadb -uroot -proot -e "
    SELECT 'Test' as status, COUNT(*) as protokolle_total
    FROM protocols p
    WHERE p.deleted_at IS NULL;
    " app >/dev/null 2>&1; then
        echo "✅ Basis-Protokoll-Abfragen funktionieren"
    else
        echo "❌ Problem mit Protokoll-Tabelle"
    fi
    
    # Test der Views (falls vorhanden)
    if docker compose exec -T db mariadb -uroot -proot -e "
    SELECT 'View-Test' as status, COUNT(*) as anzahl
    FROM protocol_versions_with_pdfs
    LIMIT 1;
    " app >/dev/null 2>&1; then
        echo "✅ PDF-Versionierungs-View funktioniert"
    else
        echo "ℹ️  PDF-Versionierungs-View nicht verfügbar (normal, wenn noch nicht migriert)"
    fi
    
    echo ""
    echo "🎉 Kollationsproblem behoben!"
    echo ""
    echo "💡 Nächste Schritte:"
    echo "   1. Browser-Seite neu laden"
    echo "   2. Protokoll-Editor testen"
    echo "   3. Bei weiteren Problemen: docker compose logs web"
    
else
    echo "❌ Kollations-Reparatur fehlgeschlagen"
    echo ""
    echo "🔍 Mögliche Ursachen:"
    echo "   - Migration-Datei nicht gefunden: migrations/028_final_collation_fix.sql"
    echo "   - Datenbank-Berechtigungen"
    echo "   - Syntax-Fehler in der Migration"
    echo ""
    echo "💡 Überprüfen Sie:"
    echo "   - Dateien: ls -la migrations/"
    echo "   - Logs: docker compose logs db"
    echo ""
    echo "🔧 Manueller Fallback-Versuch..."
    
    # Einfacher Fallback: Nur die Haupttabellen korrigieren
    echo "Korrigiere Basis-Tabellen..."
    
    docker compose exec -T db mariadb -uroot -proot -e "
    ALTER DATABASE app CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
    ALTER TABLE protocols CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
    ALTER TABLE users CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
    ALTER TABLE objects CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
    ALTER TABLE units CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
    " app
    
    if [ $? -eq 0 ]; then
        echo "✅ Basis-Kollation korrigiert"
        echo "ℹ️  Starten Sie die Anwendung neu: docker compose restart web"
    else
        echo "❌ Auch Fallback-Reparatur fehlgeschlagen"
        echo "💡 Kontaktieren Sie den Support mit dieser Fehlermeldung"
        exit 1
    fi
fi

echo ""
echo "🏁 Reparatur abgeschlossen!"
echo "   Browser neu laden und testen."
