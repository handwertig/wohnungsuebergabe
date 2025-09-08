#!/bin/bash

# Wohnungsübergabe Docker Management Script
# Behebt Docker Compose Probleme und führt Datenbankreparaturen durch

echo "🏠 Wohnungsübergabe Docker Management"
echo "===================================="

# Prüfe welcher Docker Compose Befehl verfügbar ist
if command -v "docker" &> /dev/null; then
    if docker compose version &> /dev/null; then
        DOCKER_COMPOSE="docker compose"
        echo "✅ Docker Compose (Plugin) gefunden"
    elif command -v "docker-compose" &> /dev/null; then
        DOCKER_COMPOSE="docker-compose"
        echo "✅ Docker Compose (Standalone) gefunden"
    else
        echo "❌ Docker Compose nicht gefunden!"
        echo "Bitte installieren Sie Docker Compose:"
        echo "https://docs.docker.com/compose/install/"
        exit 1
    fi
else
    echo "❌ Docker nicht gefunden!"
    echo "Bitte installieren Sie Docker Desktop:"
    echo "https://docs.docker.com/desktop/"
    exit 1
fi

echo "📋 Verwende: $DOCKER_COMPOSE"
echo ""

# Funktion für Container-Status
check_containers() {
    echo "📊 Container Status:"
    $DOCKER_COMPOSE ps
    echo ""
}

# Funktion zum Starten der Services
start_services() {
    echo "🚀 Starte Services..."
    $DOCKER_COMPOSE up -d
    echo "⏳ Warte auf Datenbank..."
    sleep 10
    check_containers
}

# Funktion für Datenbankreparatur
repair_database() {
    echo "🔧 Repariere Datenbank..."
    
    # Prüfe ob App-Container läuft
    if ! $DOCKER_COMPOSE ps app | grep -q "Up"; then
        echo "❌ App-Container läuft nicht. Starte Services..."
        start_services
    fi
    
    echo "📦 Versuche erweiterte Reparatur mit Composer..."
    
    # Versuche erst die erweiterte Reparatur
    if $DOCKER_COMPOSE exec app composer install --no-dev --optimize-autoloader 2>/dev/null; then
        echo "✅ Composer-Abhängigkeiten installiert"
        
        if $DOCKER_COMPOSE exec app php fix_database_issues.php; then
            echo "✅ Erweiterte Datenbankreparatur erfolgreich"
            echo "🔍 Führe Verifikation durch..."
            $DOCKER_COMPOSE exec app php verify_repairs.php
            return 0
        else
            echo "⚠️ Erweiterte Reparatur fehlgeschlagen, verwende einfache Reparatur..."
        fi
    else
        echo "⚠️ Composer nicht verfügbar, verwende einfache Reparatur..."
    fi
    
    # Fallback: Einfache Reparatur ohne Abhängigkeiten
    echo "⚡ Führe einfache Datenbankreparatur durch..."
    $DOCKER_COMPOSE exec app php simple_repair.php
    
    if [ $? -eq 0 ]; then
        echo "✅ Einfache Datenbankreparatur erfolgreich"
        return 0
    else
        echo "❌ Datenbankreparatur fehlgeschlagen"
        echo ""
        echo "🔍 Debug-Informationen:"
        echo "Container Status:"
        $DOCKER_COMPOSE ps
        echo ""
        echo "App-Container Logs:"
        $DOCKER_COMPOSE logs --tail=20 app
        return 1
    fi
}

# Funktion für Logs
show_logs() {
    echo "📝 Application Logs:"
    $DOCKER_COMPOSE logs app web
}

# Hauptmenü
show_menu() {
    echo "Wählen Sie eine Aktion:"
    echo "1) Services starten"
    echo "2) Container Status prüfen"
    echo "3) Datenbank reparieren"
    echo "4) Logs anzeigen"
    echo "5) In App-Container einsteigen"
    echo "6) Services stoppen"
    echo "7) Alles neu starten"
    echo "0) Beenden"
    echo ""
    read -p "Ihre Wahl [0-7]: " choice
}

# Hauptlogik
case "${1:-menu}" in
    "start")
        start_services
        ;;
    "status")
        check_containers
        ;;
    "repair")
        repair_database
        ;;
    "logs")
        show_logs
        ;;
    "shell")
        echo "🐚 Öffne Shell im App-Container..."
        $DOCKER_COMPOSE exec app bash
        ;;
    "stop")
        echo "🛑 Stoppe Services..."
        $DOCKER_COMPOSE down
        ;;
    "restart")
        echo "🔄 Starte Services neu..."
        $DOCKER_COMPOSE down
        $DOCKER_COMPOSE up -d
        sleep 10
        check_containers
        ;;
    "menu"|"")
        while true; do
            show_menu
            case $choice in
                1)
                    start_services
                    ;;
                2)
                    check_containers
                    ;;
                3)
                    repair_database
                    ;;
                4)
                    show_logs
                    ;;
                5)
                    echo "🐚 Öffne Shell im App-Container..."
                    $DOCKER_COMPOSE exec app bash
                    ;;
                6)
                    echo "🛑 Stoppe Services..."
                    $DOCKER_COMPOSE down
                    break
                    ;;
                7)
                    echo "🔄 Starte Services neu..."
                    $DOCKER_COMPOSE down
                    $DOCKER_COMPOSE up -d
                    sleep 10
                    check_containers
                    ;;
                0)
                    echo "👋 Auf Wiedersehen!"
                    break
                    ;;
                *)
                    echo "❌ Ungültige Auswahl!"
                    ;;
            esac
            echo ""
        done
        ;;
    *)
        echo "Verwendung: $0 [start|status|repair|logs|shell|stop|restart|menu]"
        echo ""
        echo "Verfügbare Befehle:"
        echo "  start   - Services starten"
        echo "  status  - Container Status"
        echo "  repair  - Datenbank reparieren"
        echo "  logs    - Logs anzeigen"
        echo "  shell   - Shell im App-Container"
        echo "  stop    - Services stoppen"
        echo "  restart - Services neu starten"
        echo "  menu    - Interaktives Menü"
        exit 1
        ;;
esac
