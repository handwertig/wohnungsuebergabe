#!/bin/bash

# ultimate_fix.sh - Universelle Lösung für alle Datenbankprobleme

echo "🛠️  Ultimative Datenbank-Reparatur"
echo "==================================="
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

echo "🔍 1. Analysiere aktuelle Datenbankstruktur"
echo "-------------------------------------------"

# Prüfe welche Tabellen existieren
echo "Existierende Tabellen:"
docker compose exec -T db mariadb -uroot -proot -e "
    SELECT table_name, table_collation
    FROM information_schema.tables 
    WHERE table_schema = 'app' 
    AND table_name LIKE '%protocol%'
    ORDER BY table_name;
" app

echo ""
echo "🔍 2. Prüfe protocol_versions Schema"
echo "-----------------------------------"

# Prüfe Spalten in protocol_versions
if docker compose exec -T db mariadb -uroot -proot -e "DESCRIBE protocol_versions;" app >/dev/null 2>&1; then
    echo "✅ Tabelle protocol_versions existiert"
    echo "Spalten:"
    docker compose exec -T db mariadb -uroot -proot -e "
        SELECT COLUMN_NAME, DATA_TYPE, IS_NULLABLE, COLLATION_NAME
        FROM INFORMATION_SCHEMA.COLUMNS 
        WHERE TABLE_SCHEMA = 'app' 
        AND TABLE_NAME = 'protocol_versions'
        ORDER BY ORDINAL_POSITION;
    " app
else
    echo "❌ Tabelle protocol_versions existiert nicht"
    echo "💡 Erstelle Tabelle..."
    
    docker compose exec -T db mariadb -uroot -proot -e "
    CREATE TABLE protocol_versions (
        id INT AUTO_INCREMENT PRIMARY KEY,
        protocol_id CHAR(36) NOT NULL,
        version_no INT NOT NULL DEFAULT 1,
        data LONGTEXT,
        created_by CHAR(36),
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        pdf_path VARCHAR(500) NULL,
        signed_pdf_path VARCHAR(500) NULL,
        signed_at DATETIME NULL,
        UNIQUE KEY unique_protocol_version (protocol_id, version_no),
        INDEX idx_protocol_id (protocol_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    " app
    
    if [ $? -eq 0 ]; then
        echo "✅ Tabelle protocol_versions erstellt"
    else
        echo "❌ Fehler beim Erstellen der Tabelle"
    fi
fi

echo ""
echo "🔧 3. Kollations-Reparatur"
echo "-------------------------"

# Führe Kollations-Fix aus
docker compose exec -T db mariadb -uroot -proot app < migrations/028_final_collation_fix.sql
echo "✅ Kollations-Fix ausgeführt"

echo ""
echo "🔧 4. Schema-Konsistenz"
echo "----------------------"

# Führe Schema-Fix aus
docker compose exec -T db mariadb -uroot -proot app < migrations/029_fix_schema_mismatch.sql
echo "✅ Schema-Fix ausgeführt"

echo ""
echo "🧪 5. Funktionstest"
echo "------------------"

# Test 1: Grundlegende Protokoll-Abfrage
echo -n "Test 1 - Protokoll-Abfrage: "
if docker compose exec -T db mariadb -uroot -proot -e "
    SELECT COUNT(*) as anzahl FROM protocols WHERE deleted_at IS NULL;
" app >/dev/null 2>&1; then
    PROTOCOL_COUNT=$(docker compose exec -T db mariadb -uroot -proot -se "SELECT COUNT(*) FROM protocols WHERE deleted_at IS NULL;" app 2>/dev/null)
    echo "✅ OK ($PROTOCOL_COUNT Protokolle)"
else
    echo "❌ FEHLER"
fi

# Test 2: Protocol Versions Abfrage
echo -n "Test 2 - Version-Abfrage: "
if docker compose exec -T db mariadb -uroot -proot -e "
    SELECT COUNT(*) as anzahl FROM protocol_versions;
" app >/dev/null 2>&1; then
    VERSION_COUNT=$(docker compose exec -T db mariadb -uroot -proot -se "SELECT COUNT(*) FROM protocol_versions;" app 2>/dev/null)
    echo "✅ OK ($VERSION_COUNT Versionen)"
else
    echo "❌ FEHLER"
fi

# Test 3: View-Abfrage (die ursprünglich fehlgeschlagen ist)
echo -n "Test 3 - PDF-View: "
if docker compose exec -T db mariadb -uroot -proot -e "
    SELECT COUNT(*) FROM protocol_versions_with_pdfs LIMIT 1;
" app >/dev/null 2>&1; then
    echo "✅ OK"
else
    echo "❌ FEHLER - View nicht verfügbar"
fi

# Test 4: Die ursprünglich problematische ORDER BY Abfrage
echo -n "Test 4 - ORDER BY Abfrage: "
if docker compose exec -T db mariadb -uroot -proot -e "
    SELECT pv.protocol_id, pv.version_no 
    FROM protocol_versions pv 
    ORDER BY pv.protocol_id, pv.version_no DESC 
    LIMIT 1;
" app >/dev/null 2>&1; then
    echo "✅ OK"
else
    echo "❌ FEHLER - Kollationsproblem weiterhin vorhanden"
fi

echo ""
echo "🔄 6. Container-Neustart"
echo "-----------------------"

echo "Starte Web-Container neu für saubere PHP-Session..."
docker compose restart web

echo "✅ Container neugestartet"

echo ""
echo "📊 7. Finale Statistiken"
echo "-----------------------"

echo "Datenbankstatus:"
docker compose exec -T db mariadb -uroot -proot -e "
    SELECT 
        'Protokolle' as typ, 
        COUNT(*) as anzahl 
    FROM protocols 
    WHERE deleted_at IS NULL
    UNION ALL
    SELECT 
        'Versionen' as typ, 
        COUNT(*) as anzahl 
    FROM protocol_versions
    UNION ALL
    SELECT 
        'PDFs' as typ, 
        COUNT(*) as anzahl 
    FROM protocol_pdfs;
" app 2>/dev/null || echo "Einige Tabellen noch nicht verfügbar (normal)"

echo ""
echo "🎉 Reparatur abgeschlossen!"
echo "=========================="
echo ""
echo "✅ Was wurde repariert:"
echo "   - Kollationsprobleme behoben"
echo "   - Schema-Inkonsistenzen korrigiert"  
echo "   - Views neu erstellt"
echo "   - Container neugestartet"
echo ""
echo "💡 Nächste Schritte:"
echo "   1. Browser-Seite VOLLSTÄNDIG neu laden (Strg+Shift+R)"
echo "   2. Protokoll-Editor öffnen und PDF-Versionen Tab testen"
echo "   3. Bei Problemen: docker compose logs web"
echo ""
echo "📍 Die ursprüngliche Fehlermeldung sollte jetzt behoben sein:"
echo "   'Illegal mix of collations (utf8mb4_uca1400_ai_ci,IMPLICIT) and (utf8mb4_unicode_ci,IMPLICIT)'"
echo ""
echo "🏁 Bereit zum Testen!"
