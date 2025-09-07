# 🏠 Wohnungsübergabe Protokoll-Wizard

Ein professionelles Web-basiertes System zur digitalen Erstellung und Verwaltung von Wohnungsübergabeprotokollen für Vermieter, Hausverwaltungen und Immobilienunternehmen.

## 🎯 Übersicht

Das Wohnungsübergabe Protokoll-System digitalisiert den kompletten Prozess der Wohnungsübergabe:

- ✅ **Digitale Protokoll-Erstellung** mit intuitivem Wizard
- ✅ **Einzugs-, Auszugs- und Zwischenprotokolle** 
- ✅ **Responsive Design** für Desktop, Tablet und Mobile
- ✅ **PDF-Generierung** für offizielle Dokumente
- ✅ **Versionierung** und Änderungshistorie
- ✅ **Multi-Objekt-Verwaltung** für Portfolio-Management
- ✅ **Sichere Authentifizierung** und Rechteverwaltung

## 🚀 Quick Start

### Voraussetzungen

- Docker & Docker Compose
- Mindestens 4GB RAM
- Freie Ports: 8080 (Web), 3307 (DB), 8081 (phpMyAdmin), 8025 (Mail)

### Installation

```bash
# Repository klonen
git clone <repository-url>
cd wohnungsuebergabe

# Services starten
docker-compose up -d

# Web-Interface öffnen
open http://localhost:8080
```

### Erste Schritte

1. **Admin-Account erstellen**: Öffnen Sie http://localhost:8080/register
2. **Objekt anlegen**: Gehen Sie zu "Stammdaten" → "Objekte"
3. **Einheiten definieren**: Fügen Sie Wohneinheiten hinzu
4. **Erstes Protokoll**: Nutzen Sie den Wizard unter "Protokolle" → "Neues Protokoll"

## 🏗️ Architektur

### Tech Stack

- **Backend**: PHP 8.2 mit nativer MVC-Architektur
- **Frontend**: Responsive HTML5 mit Bootstrap 5 und AdminKit
- **Datenbank**: MariaDB 11.4 mit JSON-Unterstützung
- **PDF-Engine**: DomPDF für professionelle Dokumente
- **E-Mail**: PHPMailer mit Mailpit für Development
- **Container**: Docker mit Multi-Service-Setup

### Service-Architektur

```
┌─────────────────┐  ┌─────────────────┐  ┌─────────────────┐
│   Nginx         │  │   PHP-FPM       │  │   MariaDB       │
│   Web Server    │→ │   Application   │→ │   Database      │
│   Port 8080     │  │   Backend       │  │   Port 3307     │
└─────────────────┘  └─────────────────┘  └─────────────────┘
        │                      │                      │
        ▼                      ▼                      ▼
┌─────────────────┐  ┌─────────────────┐  ┌─────────────────┐
│   phpMyAdmin    │  │   Mailpit       │  │   Volumes       │
│   Port 8081     │  │   Port 8025     │  │   Persistence   │
└─────────────────┘  └─────────────────┘  └─────────────────┘
```

## 📋 Features

### 🧙‍♂️ Protokoll-Wizard

- **Step-by-Step Guidance**: Geführte Protokoll-Erstellung
- **Alle Protokolltypen**: Einzug, Auszug, Zwischenprotokoll
- **Responsive Interface**: Funktioniert auf allen Geräten
- **Auto-Save**: Automatische Zwischenspeicherung
- **Validation**: Eingabevalidierung und Fehlerbehandlung

### 📊 Objekt- & Einheitenverwaltung

- **Portfolio-Management**: Mehrere Objekte und Einheiten
- **Hierarchische Structure**: Objekte → Einheiten → Protokolle
- **Flexible Konfiguration**: Anpassbare Raum- und Zählertypen
- **Bulk-Operations**: Massenoperationen für Effizienz

### 📄 Protokoll-Management

- **Vollständige CRUD-Operationen**: Erstellen, Lesen, Aktualisieren, Löschen
- **Rich Editor**: Umfangreiche Bearbeitungsmöglichkeiten
- **Tab-basierte UI**: Übersichtliche Kategorisierung
- **Änderungshistorie**: Vollständige Audit-Trails
- **Status-Management**: Workflow-Unterstützung

### 🔒 Sicherheit & Compliance

- **Authentifizierung**: Sicheres Login-System
- **CSRF-Protection**: Schutz vor Cross-Site-Request-Forgery
- **Input-Sanitization**: Umfassende Eingabebereinigung
- **SQL-Injection-Schutz**: Prepared Statements
- **Data Encryption**: Sichere Datenübertragung

## 🗂️ Projekt-Struktur

```
wohnungsuebergabe/
├── 📁 backend/                    # PHP-Backend
│   ├── 📁 src/                    # Source Code
│   │   ├── 📁 Controllers/        # MVC Controller
│   │   ├── 📁 Config/             # Konfiguration
│   │   └── 📄 *.php              # Core Classes
│   ├── 📁 public/                 # Web Root
│   └── 📄 composer.json          # Dependencies
├── 📁 docker/                     # Docker Configuration
│   ├── 📁 nginx/                 # Nginx Config
│   └── 📁 php/                   # PHP Dockerfile
├── 📁 migrations/                 # Database Migrations
├── 📁 docs/                      # Documentation
├── 📄 docker-compose.yml         # Service Definition
└── 📄 README.md                  # This File
```

## 🔧 Konfiguration

### Umgebungsvariablen

```bash
# Database
DB_HOST=db
DB_NAME=app
DB_USER=app  
DB_PASS=app

# Application
APP_ENV=dev
APP_DEBUG=true
APP_URL=http://localhost:8080

# Mail (Development)
MAIL_HOST=mailpit
MAIL_PORT=1025
```

### Custom Configuration

- **Settings**: `/backend/src/Config/Settings.php`
- **Database**: `/backend/config/database.php` 
- **Routes**: `/backend/public/index.php`

## 🧪 Development

### Local Development

```bash
# Services starten
docker-compose up -d

# Logs verfolgen
docker-compose logs -f

# Container betreten
docker-compose exec app bash

# Datenbank-Zugriff
docker-compose exec db mysql -u app -p app
```

### Code Style & Quality

```bash
# PHP Code Standards
docker-compose exec app vendor/bin/phpcs src/

# Code Linting
docker-compose exec app composer lint

# Tests (falls verfügbar)
docker-compose exec app vendor/bin/phpunit
```

### Debugging

- **Application Logs**: `docker-compose logs app`
- **Database Access**: http://localhost:8081 (phpMyAdmin)
- **E-Mail Testing**: http://localhost:8025 (Mailpit)
- **Error Logs**: `/backend/logs/`

## 📊 Database Schema

### Core Tables

- **`objects`**: Immobilienobjekte (Häuser/Gebäude)
- **`units`**: Wohneinheiten (Wohnungen)
- **`protocols`**: Übergabeprotokolle
- **`protocol_versions`**: Versionierung
- **`users`**: Benutzer und Authentifizierung
- **`owners`**: Eigentümer
- **`managers`**: Hausverwaltungen

### Audit & Logging

- **`system_logs`**: System-Events
- **`protocol_events`**: Protokoll-Änderungen
- **`email_logs`**: E-Mail-Versand

## 🔄 API Endpoints

### Protocol Management

```
GET    /protocols              # Protokoll-Übersicht
GET    /protocols/edit?id=X    # Protokoll bearbeiten
POST   /protocols/save         # Protokoll speichern
DELETE /protocols/delete       # Protokoll löschen
GET    /protocols/pdf?id=X     # PDF generieren
```

### Wizard

```
GET    /protocols/wizard/start    # Wizard starten
POST   /protocols/wizard/step/X  # Wizard-Schritte
POST   /protocols/wizard/finish  # Wizard abschließen
```

### Administration

```
GET    /settings/objects      # Objekt-Verwaltung
GET    /settings/users        # Benutzer-Verwaltung
GET    /settings/systemlogs   # System-Logs
```

## 🚀 Deployment

### Production Setup

1. **Environment anpassen**:
   ```bash
   cp .env.example .env
   # .env für Production konfigurieren
   ```

2. **SSL/HTTPS konfigurieren**:
   ```bash
   # Nginx SSL-Konfiguration anpassen
   # docker/nginx/default.conf
   ```

3. **Production Services**:
   ```bash
   docker-compose -f docker-compose.prod.yml up -d
   ```

### Backup Strategy

```bash
# Database Backup
docker-compose exec db mysqldump -u app -p app > backup.sql

# File Backup
tar -czf files-backup.tar.gz backend/storage/

# Automated Backup
# Cron-Job für regelmäßige Backups einrichten
```

## 🐛 Troubleshooting

### Häufige Probleme

**Services starten nicht:**
```bash
docker-compose down
docker-compose up -d --force-recreate
```

**Datenbank-Verbindung fehlschlägt:**
```bash
docker-compose logs db
# Warten bis "ready for connections"
```

**Berechtigungsprobleme:**
```bash
sudo chown -R $USER:$USER backend/storage/
chmod -R 755 backend/storage/
```

**Port bereits belegt:**
```bash
# Ports in docker-compose.yml anpassen
# oder andere Services stoppen
lsof -i :8080
```

### Debug-Modus

```bash
# Ausführliche Logs aktivieren
export APP_DEBUG=true
docker-compose up -d

# PHP-Fehler anzeigen
docker-compose exec app tail -f /var/log/php/error.log
```

## 🤝 Contributing

### Development Workflow

1. **Fork** das Repository
2. **Branch** für Feature erstellen: `git checkout -b feature/amazing-feature`
3. **Commit** Änderungen: `git commit -m 'Add amazing feature'`
4. **Push** zum Branch: `git push origin feature/amazing-feature`
5. **Pull Request** erstellen

### Code Standards

- PSR-4 Autoloading
- PSR-12 Code Style
- Comprehensive Comments
- Security-First Approach
- Mobile-First Design

## 📄 Lizenz

Dieses Projekt ist unter der MIT-Lizenz lizenziert. Siehe [LICENSE](LICENSE) für Details.

## 📞 Support

- **Issues**: GitHub Issues für Bugs und Feature-Requests
- **Dokumentation**: `/docs/` Verzeichnis
- **Wiki**: GitHub Wiki für erweiterte Dokumentation

## 🏆 Features & Status

| Feature | Status | Version |
|---------|--------|---------|
| 🏠 Objekt-Verwaltung | ✅ Stable | 1.0 |
| 🧙‍♂️ Protokoll-Wizard | ✅ Stable | 1.0 |
| 📄 PDF-Export | ✅ Stable | 1.0 |
| 🔐 Authentifizierung | ✅ Stable | 1.0 |
| 📱 Mobile Support | ✅ Stable | 1.0 |
| 🔍 Audit-Logging | ✅ Stable | 1.0 |
| ✍️ Digital Signatures | ✅ Stable | 2.0 |
| 📧 E-Mail Integration | ✅ Stable | 2.0 |
| 🌐 Multi-Language | 📋 Backlog | 2.0 |

---

**Entwickelt mit ❤️ für professionelle Immobilienverwaltung**

