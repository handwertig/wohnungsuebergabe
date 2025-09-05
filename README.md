# Wohnungsübergabe-System

Ein modernes, benutzerfreundliches System zur Verwaltung von Wohnungsübergaben mit PDF-Generierung, E-Mail-Integration und umfassendem Audit-System.

## Features

### Kernfunktionalitäten
- **Digitale Übergabeprotokolle** - Strukturierte Erfassung aller Übergabedaten
- **E-Mail-Integration** - Automatischer Versand von Protokollen und Dokumenten
- **PDF-Generierung** - Professionelle PDF-Dokumente mit Branding
- **Responsive Design** - Optimiert für Desktop, Tablet und Mobile
- **Benutzer- & Rechteverwaltung** - Granulare Zugriffskontrollen

### Administration
- **Multi-Tenant-Architektur** - Trennung von Eigentümern und Hausverwaltungen
- **System-Audit-Log** - Vollständige Nachverfolgung aller Aktivitäten
- **Konfigurierbare Einstellungen** - SMTP, DocuSign, Textbausteine
- **Anpassbares Branding** - Logo-Upload und Custom CSS

### Datenmanagement
- **Objektverwaltung** - Immobilien und Wohneinheiten organisieren
- **Kontaktverwaltung** - Eigentümer und Hausverwaltungen verwalten
- **Protokoll-Templates** - Vordefinierte Checklisten und Formulare
- **Erweiterte Suche** - Volltextsuche über alle Datensätze

## Technische Details

### Tech-Stack
- **Backend:** PHP 8.1+ mit OOP-Architektur
- **Frontend:** Bootstrap 5.3 + AdminKit Theme
- **Datenbank:** MySQL/MariaDB
- **PDF-Engine:** TCPDF für Dokumentgenerierung
- **E-Mail:** PHPMailer mit SMTP-Support

### Architektur
- **MVC-Pattern** mit sauberer Controller-Struktur
- **Repository-Pattern** für Datenzugriff
- **Service-Layer** für Geschäftslogik
- **PSR-4 Autoloading** für moderne PHP-Standards

### Sicherheit
- **Session-Management** mit sichere Cookie-Handling
- **SQL-Injection-Schutz** durch Prepared Statements
- **XSS-Protection** mit htmlspecialchars-Escaping
- **Input-Validation** auf Server- und Client-Seite

## Installation

### Voraussetzungen
- Docker & Docker Compose
- PHP 8.1+ (für lokale Entwicklung)
- MySQL/MariaDB 8.0+

### Quick Start
```bash
# Repository klonen
git clone <repository-url>
cd wohnungsuebergabe

# Docker-Umgebung starten
docker-compose up -d

# Initial-Setup ausführen
docker-compose exec app php bin/setup.php

# Anwendung ist verfügbar unter: http://localhost:8080
```

### Manuelle Installation
```bash
# Abhängigkeiten installieren
composer install

# Datenbank konfigurieren
cp backend/config/database.example.php backend/config/database.php
# Datenbank-Credentials eintragen

# Migrations ausführen
php bin/migrate.php

# Webserver konfigurieren (Apache/Nginx)
# Document Root: backend/public/
```

## Konfiguration

### Umgebungsvariablen
```env
# Datenbank
DB_HOST=localhost
DB_NAME=wohnungsuebergabe
DB_USER=admin
DB_PASS=password

# E-Mail (SMTP)
SMTP_HOST=smtp.gmail.com
SMTP_PORT=587
SMTP_USER=your-email@gmail.com
SMTP_PASS=your-app-password

# DocuSign (optional)
DOCUSIGN_CLIENT_ID=your-client-id
DOCUSIGN_CLIENT_SECRET=your-client-secret
DOCUSIGN_BASE_URI=https://demo.docusign.net
```

### Web-Interface Konfiguration
1. **Admin-Account erstellen:** `/setup` besuchen
2. **SMTP konfigurieren:** Einstellungen → E-Mail
3. **Branding anpassen:** Einstellungen → Design
4. **Textbausteine definieren:** Einstellungen → Textbausteine

## Benutzerrollen

### Administrator
- Vollzugriff auf alle Funktionen
- Benutzerverwaltung und Systemkonfiguration
- Alle Objekte, Eigentümer und Hausverwaltungen

### Hausverwaltung
- Zugriff nur auf zugewiesene Verwaltungen
- Kann Protokolle für verwaltete Objekte erstellen
- Kontakt zu zugeordneten Eigentümern

### Eigentümer
- Nur Lesezugriff auf eigene Objekte
- Kann Protokolle einsehen und herunterladen
- Erhält automatische E-Mail-Benachrichtigungen

## Verwendung

### Neues Übergabeprotokoll erstellen
1. **Objekt auswählen:** aus der Objektliste
2. **Protokolltyp wählen:** Einzug/Auszug/Besichtigung
3. **Daten erfassen:** strukturiert über Web-Interface
4. **PDF generieren:** automatische Erstellung
5. **E-Mail versenden:** an alle Beteiligten

### Verwaltungsaufgaben
- **Stammdaten pflegen:** Objekte, Eigentümer, Hausverwaltungen
- **Benutzer verwalten:** Rechte zuweisen, Passwörter zurücksetzen
- **System überwachen:** Audit-Log und Aktivitäten einsehen

## Design-System

### AdminKit Theme
- **Ultra-minimale border-radius** (4-8px)
- **Subtile Schatten** für moderne Ästhetik
- **Responsive Grid** für alle Bildschirmgrößen
- **Accessibility-optimiert** mit ARIA-Labels

### Farbschema
- **Primary:** #3b82f6 (Blue 500)
- **Success:** #10b981 (Emerald 500)
- **Warning:** #f59e0b (Amber 500)
- **Danger:** #ef4444 (Red 500)

## API-Dokumentation

### REST-Endpoints
```http
GET    /api/protocols          # Alle Protokolle abrufen
POST   /api/protocols          # Neues Protokoll erstellen
GET    /api/protocols/{id}     # Protokoll-Details
PUT    /api/protocols/{id}     # Protokoll bearbeiten
DELETE /api/protocols/{id}     # Protokoll löschen

GET    /api/objects            # Objekte verwalten
GET    /api/owners             # Eigentümer verwalten
GET    /api/managers           # Hausverwaltungen verwalten
```

### Authentifizierung
```http
POST /api/auth/login
{
  "email": "user@example.com",
  "password": "secure-password"
}

# Response
{
  "token": "jwt-token",
  "user": {
    "id": "uuid",
    "email": "user@example.com",
    "role": "admin"
  }
}
```

## Troubleshooting

### Häufige Probleme

**PDF-Generierung schlägt fehl:**
```bash
# Berechtigungen prüfen
chmod 755 backend/storage/pdfs/
chown www-data:www-data backend/storage/

# TCPDF-Logs prüfen
tail -f backend/logs/pdf.log
```

**E-Mail-Versand funktioniert nicht:**
```bash
# SMTP-Verbindung testen
php bin/test-smtp.php

# Mail-Queue prüfen
php bin/mail-queue.php --status
```

**System-Log wird nicht befüllt:**
```bash
# SystemLogger initialisieren
php bin/init-system-log.php

# Log-Berechtigungen prüfen
chmod 644 backend/logs/system.log
```

### Performance-Optimierung
```bash
# OPcache aktivieren (php.ini)
opcache.enable=1
opcache.memory_consumption=128
opcache.max_accelerated_files=4000

# MySQL-Optimierung
innodb_buffer_pool_size=1G
query_cache_size=64M
```

## Contributing

### Development Setup
```bash
# Development-Container starten
docker-compose -f docker-compose.dev.yml up -d

# Code-Quality-Tools
composer run-script phpstan    # Static Analysis
composer run-script phpcs     # Code Standards
composer run-script phpunit   # Unit Tests
```

### Code-Guidelines
- **PSR-12** Code-Style befolgen
- **Type-Hints** für alle Parameter verwenden
- **DocBlocks** für alle public Methods
- **Unit-Tests** für neue Features schreiben

## Lizenz

Dieses Projekt steht unter der MIT-Lizenz. Siehe `LICENSE` für Details.

## Support

- **Documentation:** `/docs` Verzeichnis
- **Issues:** GitHub Issues verwenden
- **E-Mail:** kontakt@handwertig.com

---

**Version:** 2.0.0  
**Last Updated:** September 2025  
**Maintainer:** Handwertig DevOps
