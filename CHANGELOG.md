# Changelog

Alle wichtigen Änderungen an diesem Projekt werden in dieser Datei dokumentiert.

Das Format basiert auf [Keep a Changelog](https://keepachangelog.com/de/1.0.0/),
und dieses Projekt folgt [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [2.0.5] - 2025-09-07

### 🔧 Behobene Fehler

#### Kritische Fixes
- **Type "Zwischenprotokoll":** Datenbank-Spalte von VARCHAR(10) auf VARCHAR(50) erweitert
- **Event-Logging:** protocol_events Tabelle wird jetzt korrekt befüllt
- **Docker-Befehle:** Container-Namen korrigiert (app statt web)
- **UUID-Generator:** UuidHelper Klasse für sichere ID-Generierung implementiert

### ✨ Neue Features
- **EventLogger Klasse:** Zentrales Event-Logging System
- **UuidHelper Klasse:** UUID v4 Generator nach RFC 4122
- **Fix-Scripts:** Automatische Reparatur-Tools für alle Probleme
- **Verbesserte Dokumentation:** Vollständige Docker-Befehlsreferenz

### 📦 Neue Dateien
- `/backend/src/UuidHelper.php`
- `/backend/src/EventLogger.php`
- `/backend/fix_events.php`
- `/backend/add_test_events.php`
- `/COMPLETE_FIX.sh`
- `/fix_events_now.sh`
- `/quick.sh`

### 🛠 Technische Änderungen
- Type-Spalte in protocols Tabelle erweitert
- protocol_events Tabelle mit optimierten Indizes
- Fallback-Mechanismen für UUID-Generierung
- Robustere Fehlerbehandlung im ProtocolsController

## [2.0.4] - 2025-09-07

### 🔧 Behobene Fehler
- Protokoll-Speicherung funktioniert wieder
- Routing-Fehler zu working_save.php behoben
- System-Logging für Protokoll-Änderungen aktiviert

### ✨ Neue Features
- Vollständige ProtocolsController::save() Implementation
- Transaktionale Sicherheit bei Updates
- Automatische Protokoll-Versionierung
- Event-Tracking für alle Änderungen

### 📊 Datenbank
- protocol_events Tabelle für Ereignis-Tracking
- audit_log Tabelle für Änderungsverfolgung
- email_log Tabelle für Versand-Historie
- Optimierte Indizes

## [2.0.3] - 2025-09-07

### 🔧 Behobene Fehler
- Settings-Speicherung repariert
- System-Log funktioniert zuverlässig
- Datenbank-Schema korrigiert

### ✨ Verbesserungen
- Settings mit Transaktionssicherheit
- Automatisches Logging aller Änderungen
- UTF8MB4 Kollation vereinheitlicht
- Robuste Fehlerbehandlung

## [2.0.2] - 2025-09-07

### 🔧 Behobene Fehler
- Settings werden korrekt gespeichert
- SMTP-Einstellungen persistieren
- DocuSign-Konfiguration funktioniert
- Textbausteine-Versionierung repariert

### ✨ Neue Features
- Verbesserte Settings-Klasse
- System-Logger mit Fallback-Ebenen
- Automatische Tabellen-Erstellung
- Debug-Methoden für Diagnose

## [2.0.1] - 2025-09-06

### 🔧 Behobene Fehler
- Kollationsproblem in MariaDB behoben
- Schema-Inkonsistenz in protocol_versions
- PDF-Versionierung VIEW repariert
- JOIN-Operationen mit Kollations-Behandlung

### 🛠 Wartungstools
- ultimate_fix.sh für Datenbank-Reparatur
- debug_collation.sh für Analyse
- Migrationen 027-029 für Fixes

## [2.0.0] - 2025-01-20

### 🎨 Major Release - Komplettes Redesign
- **BREAKING:** Neues UI mit AdminKit Theme
- Minimale Border-Radius (4-8px)
- Subtiles Schatten-System
- Responsive Design
- Konsistentes Farbschema

### ✨ Neue Features
- Breadcrumb-Navigation
- System-Audit-Log
- Erweiterte Benutzerverwaltung
- Role-Based Access Control
- Demo-Daten Generator

### 🛠 Technische Verbesserungen
- PSR-4 Autoloading
- MVC-Architektur
- Type-Hints überall
- Prepared Statements
- XSS-Schutz

## [1.9.0] - 2025-01-15

### 🔐 Sicherheit
- Verbessertes Session-Management
- CSRF-Protection
- Bcrypt Password-Hashing
- XSS-Vulnerabilities behoben

## [1.8.0] - 2025-01-10

### 📋 Protokoll-Management
- Digitale Übergabeprotokolle
- PDF-Generierung mit TCPDF
- Protokoll-Templates
- Form-Validierung

## [1.7.0] - 2025-01-05

### 🎨 Branding
- Logo-Upload
- Custom CSS
- Textbausteine konfigurierbar
- Konsistentes Branding

## [1.6.0] - 2024-12-30

### 🐳 Docker
- Docker-Containerisierung
- Environment-basierte Konfiguration
- Datenbank-Migration System
- Deployment-Automatisierung

## [1.5.0] - 2024-12-25

### 🏗 Architektur
- MVC Framework
- Router mit Clean URLs
- PSR-4 Autoloader
- Code-Organisation

## [1.0.0] - 2024-12-01

### 🚀 Initial Release
- Basis-System für Wohnungsübergaben
- Benutzer-Authentifizierung
- CRUD-Operationen
- E-Mail-System
- PDF-Export

---

## Legende

- 🔧 **Behobene Fehler** - Bugfixes und Korrekturen
- ✨ **Neue Features** - Neue Funktionalitäten
- 🛠 **Technische Änderungen** - Interne Verbesserungen
- 📊 **Datenbank** - Schema-Änderungen
- 🎨 **Design** - UI/UX Updates
- 🔐 **Sicherheit** - Security Updates
- 📦 **Dependencies** - Paket-Updates
- 🚀 **Release** - Major Releases
- 💥 **Breaking Changes** - Inkompatible Änderungen

---

**Aktuelle Version:** 2.0.5  
**Status:** Production Ready  
**Datum:** 07.09.2025
