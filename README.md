# Wohnungsübergabe-System

Ein modernes, benutzerfreundliches System zur Verwaltung von Wohnungsübergaben mit PDF-Generierung, E-Mail-Integration und umfassendem Audit-System.

## 🚀 Version 2.0.5 - Production Ready

### ✨ Highlights dieser Version
- Vollständige Unterstützung für "Zwischenprotokoll"
- Event-Logging System funktioniert zuverlässig
- Docker-Befehle korrigiert und dokumentiert
- UUID v4 Generator für sichere IDs
- Robuste Fehlerbehandlung implementiert

## 📋 Features

### Kernfunktionen
- **Digitale Übergabeprotokolle**
  - Einzugsprotokoll
  - Auszugsprotokoll
  - Zwischenprotokoll
- **PDF-Generierung** mit Versionierung
- **E-Mail-Integration** mit SMTP-Support
- **Event-Tracking** für vollständige Historie
- **Responsive Design** für alle Geräte
- **Benutzer- und Rechteverwaltung**

### Administration
- Multi-Tenant-Architektur
- System-Audit-Log
- Protocol-Events Tracking
- Konfigurierbare Einstellungen
- Anpassbares Branding

### Datenmanagement
- Objektverwaltung (Immobilien/Wohneinheiten)
- Kontaktverwaltung (Eigentümer/Hausverwaltungen)
- Protokoll-Templates
- Erweiterte Suchfunktionen

## 🛠 Technische Details

### Tech-Stack
- **Backend:** PHP 8.1+ (OOP)
- **Frontend:** Bootstrap 5.3 + AdminKit Theme
- **Datenbank:** MariaDB 11.4
- **Container:** Docker & Docker Compose
- **PDF:** TCPDF/Dompdf
- **E-Mail:** PHPMailer

### Architektur
- MVC-Pattern
- Repository-Pattern
- Service-Layer
- PSR-4 Autoloading
- UUID v4 für IDs

## 📦 Installation

### Voraussetzungen
- Docker & Docker Compose (v2.x)
- Git
- 4GB RAM
- 10GB Speicherplatz

### Quick Start

```bash
# 1. Repository klonen
git clone <repository-url>
cd wohnungsuebergabe

# 2. Setup ausführen (startet Docker und konfiguriert alles)
chmod +x setup.sh
./setup.sh

# Alternative: Manueller Start
docker compose up -d
```

### Zugriff
- **Frontend:** http://localhost:8080
- **phpMyAdmin:** http://localhost:8081 (root/root)
- **MailPit:** http://localhost:8025

## 🐳 Docker-Befehle

### Wichtig: Neue Syntax ab v2.0.5

```bash
# RICHTIG (neu):
docker compose exec app php ...      # PHP-Container
docker compose exec db mariadb ...   # Datenbank
docker compose up -d                 # Starten
docker compose down                  # Stoppen

# FALSCH (alt):
docker-compose exec web php ...      # Funktioniert nicht!
```

### Container-Übersicht
| Container | Service | Port | Beschreibung |
|-----------|---------|------|--------------|
| app | PHP-FPM | 9000 | PHP Application Server |
| web | Nginx | 8080 | Webserver |
| db | MariaDB | 3307 | Datenbank |
| phpmyadmin | phpMyAdmin | 8081 | Datenbank-GUI |
| mailpit | MailPit | 8025/1025 | E-Mail Testing |

## 🔧 Konfiguration

### Datenbank
- **Host:** db (intern) / localhost:3307 (extern)
- **Datenbank:** app
- **Benutzer:** root
- **Passwort:** root

### E-Mail Testing
- **SMTP-Host:** mailpit
- **SMTP-Port:** 1025
- **Web-Interface:** http://localhost:8025

## 📖 Verwendung

### Neues Protokoll erstellen
1. Login unter http://localhost:8080/login
2. Menü → Protokolle
3. "Neues Protokoll" klicken
4. Typ wählen (Einzug/Auszug/Zwischenprotokoll)
5. Daten erfassen und speichern
6. PDF generieren (Tab "PDF-Versionen")
7. Events prüfen (Tab "Protokoll")

### Event-Tracking
Alle Aktionen werden automatisch protokolliert:
- Protokoll erstellt/bearbeitet
- Type geändert
- PDF generiert
- E-Mail versendet

## 🚨 Fehlerbehebung

### Problem: "Data truncated for column 'type'"
```bash
docker compose exec app php quick_fix.php
```

### Problem: Keine Events werden angezeigt
```bash
docker compose exec app php /var/www/html/fix_events.php
```

### Problem: Docker-Befehle funktionieren nicht
Verwenden Sie die neue Syntax mit Leerzeichen:
```bash
docker compose ...   # Richtig
docker-compose ...   # Falsch
```

### Problem: Container starten nicht
```bash
docker compose down -v
docker compose build --no-cache
docker compose up -d
```

## 🧪 Testing

### Funktionstest
```bash
./final_test_v2.0.5.sh
```

### Manuelle Tests
1. Login testen
2. Protokoll erstellen
3. Type "Zwischenprotokoll" setzen
4. Events prüfen
5. PDF generieren

## 💾 Wartung

### Backup
```bash
# Datenbank
docker compose exec db mariadb-dump -uroot -proot app > backup_$(date +%Y%m%d).sql

# Dateien
tar -czf backup_files_$(date +%Y%m%d).tar.gz backend/storage/
```

### Updates
```bash
git pull origin main
docker compose exec app composer update
docker compose restart
```

## 📝 Lizenz

MIT License - siehe LICENSE Datei

## 🤝 Support

- **Dokumentation:** `/docs` Verzeichnis
- **E-Mail:** kontakt@handwertig.com
- **phpMyAdmin:** http://localhost:8081

---

**Version:** 2.0.5  
**Status:** Production Ready  
**Datum:** 07.09.2025  
**Maintainer:** Handwertig DevOps
