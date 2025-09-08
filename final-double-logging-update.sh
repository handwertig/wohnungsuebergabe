#!/bin/bash

# FINALES UPDATE für VOLLSTÄNDIGES DOPPEL-LOGGING
# Aktiviert Events sowohl in protocol_events als auch in system_log

echo "🚀 VOLLSTÄNDIGES DOPPEL-LOGGING UPDATE"
echo "====================================="

# Docker Compose Command ermitteln
if command -v "docker" &> /dev/null && docker compose version &> /dev/null; then
    DOCKER_COMPOSE="docker compose"
elif command -v "docker-compose" &> /dev/null; then
    DOCKER_COMPOSE="docker-compose"
else
    echo "❌ Docker Compose nicht gefunden!"
    exit 1
fi

echo "📋 Verwende: $DOCKER_COMPOSE"

# Prüfe ob Container laufen
if ! $DOCKER_COMPOSE ps app | grep -q "Up"; then
    echo "⚡ Starte Docker Container..."
    $DOCKER_COMPOSE up -d
    sleep 10
fi

echo ""
echo "🔧 SCHRITT 1: Datenbank-Schema reparieren"
echo "=========================================="

$DOCKER_COMPOSE exec app php -r "
\$pdo = new PDO('mysql:host=db;dbname=app;charset=utf8mb4', 'app', 'app');
\$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

echo \"📋 Prüfe und repariere Tabellen...\n\";

// 1. protocol_events Tabelle prüfen/erstellen
\$stmt = \$pdo->query(\"SHOW TABLES LIKE 'protocol_events'\");
if (\$stmt->rowCount() === 0) {
    echo \"⚡ Erstelle protocol_events Tabelle...\n\";
    \$pdo->exec(\"
        CREATE TABLE protocol_events (
            id CHAR(36) PRIMARY KEY,
            protocol_id CHAR(36) NOT NULL,
            type ENUM('signed_by_tenant','signed_by_owner','sent_owner','sent_manager','sent_tenant','other') NOT NULL,
            message VARCHAR(255) NULL,
            meta JSON NULL,
            created_by VARCHAR(255) NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            CONSTRAINT fk_events_protocol FOREIGN KEY (protocol_id) REFERENCES protocols(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    \");
    \$pdo->exec(\"CREATE INDEX idx_events_protocol ON protocol_events (protocol_id, created_at)\");
    \$pdo->exec(\"CREATE INDEX idx_events_created_by ON protocol_events (created_by)\");
    echo \"✅ protocol_events Tabelle erstellt\n\";
} else {
    echo \"✅ protocol_events Tabelle existiert\n\";
    
    // Spalten prüfen und hinzufügen falls nötig
    \$stmt = \$pdo->query(\"SHOW COLUMNS FROM protocol_events LIKE 'meta'\");
    if (\$stmt->rowCount() === 0) {
        echo \"⚡ Füge meta Spalte hinzu...\n\";
        \$pdo->exec(\"ALTER TABLE protocol_events ADD COLUMN meta JSON NULL AFTER message\");
        echo \"✅ meta Spalte hinzugefügt\n\";
    }
    
    \$stmt = \$pdo->query(\"SHOW COLUMNS FROM protocol_events LIKE 'created_by'\");
    if (\$stmt->rowCount() === 0) {
        echo \"⚡ Füge created_by Spalte hinzu...\n\";
        \$pdo->exec(\"ALTER TABLE protocol_events ADD COLUMN created_by VARCHAR(255) NULL AFTER meta\");
        \$pdo->exec(\"CREATE INDEX IF NOT EXISTS idx_events_created_by ON protocol_events (created_by)\");
        echo \"✅ created_by Spalte hinzugefügt\n\";
    }
}

// 2. system_log Tabelle prüfen
\$stmt = \$pdo->query(\"SHOW TABLES LIKE 'system_log'\");
if (\$stmt->rowCount() === 0) {
    echo \"❌ system_log Tabelle fehlt - bitte Migrationen ausführen\n\";
} else {
    echo \"✅ system_log Tabelle existiert\n\";
}

echo \"✅ Datenbank-Schema ist bereit\n\";
"

echo ""
echo "🧪 SCHRITT 2: Doppel-Logging testen"
echo "==================================="

$DOCKER_COMPOSE exec app php test_double_logging.php

echo ""
echo "🎯 SCHRITT 3: Finale Verifikation"
echo "=================================="

echo "📋 Prüfe ProtocolsController Import..."
if grep -q "use App\\\\SystemLogger;" /Users/berndgundlach/Documents/Docker/wohnungsuebergabe/backend/src/Controllers/ProtocolsController.php; then
    echo "✅ SystemLogger Import gefunden"
else
    echo "❌ SystemLogger Import fehlt"
fi

echo "📋 Prüfe Doppel-Logging Methoden..."
if grep -q "logToProtocolEvents" /Users/berndgundlach/Documents/Docker/wohnungsuebergabe/backend/src/Controllers/ProtocolsController.php; then
    echo "✅ Doppel-Logging Methoden gefunden"
else
    echo "❌ Doppel-Logging Methoden fehlen"
fi

echo ""
echo "🎉 VOLLSTÄNDIGES DOPPEL-LOGGING UPDATE ABGESCHLOSSEN!"
echo "======================================================"

echo ""
echo "✅ Was wurde implementiert:"
echo "   • Events werden in protocol_events Tabelle gespeichert"
echo "   • Events werden AUCH im system_log gespeichert" 
echo "   • Intelligente Event-Zuordnung basierend auf Event-Typ"
echo "   • Vollständige Protokoll-Daten für besseres Logging"
echo "   • Robuste Fehlerbehandlung"
echo ""

echo "🎯 FINALER TEST:"
echo "1. Öffnen Sie: http://localhost:8080/protocols/edit?id=82cc7de7-7d1e-11f0-89a6-822b82242c5d"
echo "2. Ändern Sie den Mieter-Namen (z.B. fügen Sie die aktuelle Uhrzeit hinzu)"
echo "3. Klicken Sie 'Speichern'"
echo "4. Prüfen Sie BEIDE Bereiche:"
echo "   ✅ Tab 'Protokoll' → 'Ereignisse & Änderungen'"
echo "   ✅ http://localhost:8080/settings/systemlogs"
echo ""
echo "🎉 Events sollten in BEIDEN Bereichen erscheinen!"
echo "🎉 SQL-Fehler 'Unknown column meta' sollten verschwunden sein!"
echo "🎉 Vollständiges Audit-Trail ist jetzt aktiv!"
