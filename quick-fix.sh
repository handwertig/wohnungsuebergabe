#!/bin/bash

# QUICK FIX für das meta-Spalten Problem
# Führt sofortige Reparatur der protocol_events Tabelle durch

echo "🔧 QUICK FIX: protocol_events meta Spalte"
echo "========================================"

# Prüfe Docker Compose Befehl
if command -v "docker" &> /dev/null && docker compose version &> /dev/null; then
    DOCKER_COMPOSE="docker compose"
elif command -v "docker-compose" &> /dev/null; then
    DOCKER_COMPOSE="docker-compose"
else
    echo "❌ Docker Compose nicht gefunden!"
    exit 1
fi

echo "📋 Verwende: $DOCKER_COMPOSE"

# Quick Fix ausführen
echo "⚡ Führe Quick Fix aus..."
$DOCKER_COMPOSE exec app php -r "
\$pdo = new PDO('mysql:host=db;dbname=app;charset=utf8mb4', 'app', 'app');
\$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

echo \"🔍 Prüfe protocol_events Tabelle...\n\";

// Meta Spalte hinzufügen falls sie fehlt
\$stmt = \$pdo->query(\"SHOW COLUMNS FROM protocol_events LIKE 'meta'\");
if (\$stmt->rowCount() === 0) {
    echo \"⚡ Füge meta Spalte hinzu...\n\";
    \$pdo->exec(\"ALTER TABLE protocol_events ADD COLUMN meta JSON NULL AFTER message\");
    echo \"✅ meta Spalte hinzugefügt\n\";
} else {
    echo \"✅ meta Spalte bereits vorhanden\n\";
}

// Created_by Spalte prüfen
\$stmt = \$pdo->query(\"SHOW COLUMNS FROM protocol_events LIKE 'created_by'\");
if (\$stmt->rowCount() === 0) {
    echo \"⚡ Füge created_by Spalte hinzu...\n\";
    \$pdo->exec(\"ALTER TABLE protocol_events ADD COLUMN created_by VARCHAR(255) NULL AFTER meta\");
    echo \"✅ created_by Spalte hinzugefügt\n\";
} else {
    echo \"✅ created_by Spalte bereits vorhanden\n\";
}

echo \"🎉 QUICK FIX ABGESCHLOSSEN!\n\";
echo \"✅ Alle Events sollten jetzt korrekt angezeigt werden\n\";
"

if [ $? -eq 0 ]; then
    echo ""
    echo "🧪 Teste das Doppel-Logging System..."
    $DOCKER_COMPOSE exec app php test_double_logging.php
    
    echo ""
    echo "🎯 TESTEN SIE JETZT:"
    echo "1. Öffnen Sie: http://localhost:8080/protocols/edit?id=82cc7de7-7d1e-11f0-89a6-822b82242c5d"
    echo "2. Machen Sie eine Änderung (z.B. Mieter-Name)"
    echo "3. Klicken Sie 'Speichern'"
    echo "4. Prüfen Sie BEIDE Bereiche:"
    echo "   ✅ Tab 'Protokoll' → 'Ereignisse & Änderungen' (protocol_events)"
    echo "   ✅ http://localhost:8080/settings/systemlogs (system_log)"
    echo ""
    echo "🎉 Events sollten in BEIDEN Bereichen erscheinen!"
    echo "✅ Das Doppel-Logging ist jetzt vollständig aktiv!"
else
    echo "❌ Quick Fix fehlgeschlagen!"
    exit 1
fi
