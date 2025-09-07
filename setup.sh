#!/bin/bash

# Wohnungsübergabe-System Setup Script
# Version 2.0.5

echo ""
echo "======================================"
echo "🏠 Wohnungsübergabe-System Setup"
echo "======================================"
echo ""

# Check Docker
if ! command -v docker &> /dev/null; then
    echo "❌ Docker ist nicht installiert!"
    echo "Bitte installieren Sie Docker Desktop von: https://www.docker.com/products/docker-desktop"
    exit 1
fi

# Check Docker Compose
if ! docker compose version &> /dev/null; then
    echo "❌ Docker Compose v2 ist nicht installiert!"
    exit 1
fi

echo "✅ Docker und Docker Compose gefunden"
echo ""

# Create necessary directories
echo "📁 Erstelle notwendige Verzeichnisse..."
mkdir -p backend/storage/pdfs
mkdir -p backend/storage/uploads
mkdir -p backend/storage/temp
mkdir -p backend/storage/branding
mkdir -p backend/logs
chmod -R 755 backend/storage
chmod -R 755 backend/logs

# Start Docker containers
echo ""
echo "🐳 Starte Docker Container..."
docker compose up -d

# Wait for containers
echo ""
echo "⏳ Warte auf Container-Start (30 Sekunden)..."
sleep 30

# Check container status
echo ""
echo "📊 Container-Status:"
docker compose ps

# Run database migrations
echo ""
echo "🗄️ Führe Datenbank-Migrationen aus..."
docker compose exec app php /var/www/html/backend/migrate.php 2>/dev/null || echo "Migrationen werden beim ersten Start automatisch ausgeführt"

# Fix any potential issues
echo ""
echo "🔧 Führe initiale Konfiguration aus..."
if [ -f "backend/fix_all_issues.php" ]; then
    docker compose exec app php /var/www/html/fix_all_issues.php
else
    echo "Keine zusätzliche Konfiguration erforderlich"
fi

echo ""
echo "======================================"
echo "✅ SETUP ABGESCHLOSSEN!"
echo "======================================"
echo ""
echo "Die Anwendung ist jetzt verfügbar unter:"
echo ""
echo "🌐 Frontend:   http://localhost:8080"
echo "📊 phpMyAdmin: http://localhost:8081"
echo "📧 MailPit:    http://localhost:8025"
echo ""
echo "Standard-Login:"
echo "Benutzer: admin@example.com"
echo "Passwort: admin123"
echo ""
echo "Datenbank-Zugang (phpMyAdmin):"
echo "Benutzer: root"
echo "Passwort: root"
echo ""
echo "Nächste Schritte:"
echo "1. Öffnen Sie http://localhost:8080"
echo "2. Melden Sie sich mit den Standard-Zugangsdaten an"
echo "3. Ändern Sie das Admin-Passwort unter Einstellungen"
echo ""
echo "Bei Problemen führen Sie aus:"
echo "docker compose logs -f"
echo ""
