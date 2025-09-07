# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [2.0.4] - 2025-09-07

### 🔧 Kritische Fehlerbehebung - Protokoll-Speicherung
- **BEHOBEN:** Protokoll-Änderungen werden jetzt korrekt gespeichert
- **BEHOBEN:** Routing-Fehler zu nicht-existierender working_save.php
- **BEHOBEN:** System-Logging für Protokoll-Änderungen funktioniert
- **BEHOBEN:** Event-Tracking für Protokolle aktiviert

### 🛠️ Technische Verbesserungen
- ProtocolsController::save() Methode vollständig implementiert
- Transaktionale Sicherheit bei Protokoll-Updates
- Umfassendes Error-Handling mit Rollback
- Automatische Versionierung von Protokoll-Änderungen

### 🗄️ Datenbank-Optimierungen
- protocol_events Tabelle für Ereignis-Tracking
- audit_log Tabelle für Änderungsverfolgung
- email_log Tabelle für Versand-Protokolle
- Optimierte Indizes für bessere Performance

### 🔧 Neue Diagnose-Tools
- debug_protocol_save.sh - Umfassende Protokoll-Diagnose
- fix_protocol_save.sh - Automatische Reparatur
- final_test_protocol.sh - Funktionstest

## [2.0.3] - 2025-09-07

### 🔧 Finale Lösung - Settings & Logging
- **BEHOBEN:** Settings-Speicherung funktioniert jetzt zuverlässig
- **BEHOBEN:** Alle Änderungen werden korrekt im System-Log protokolliert
- **BEHOBEN:** Datenbank-Schema vollständig korrigiert
- **GETESTET:** Umfassende Tests im Docker-Container durchgeführt
- **VERIFIZIERT:** Web-Interface speichert alle Einstellungen korrekt

### 🛠️ Technische Details
- Settings-Tabelle mit DEFAULT CURRENT_TIMESTAMP für updated_at
- System_log-Tabelle mit korrekten Spalten und Indizes
- Konsistente UTF8MB4 Kollation überall
- Robuste Fehlerbehandlung in allen Komponenten

## [2.0.2] - 2025-09-07

### 🔧 Kritische Fehlerbehebung - Settings & Logging
- **BEHOBEN:** Settings werden jetzt korrekt in der Datenbank gespeichert
- **BEHOBEN:** System-Log schreibt wieder alle Änderungen mit
- **BEHOBEN:** Mail-Einstellungen (SMTP) werden korrekt persistiert
- **BEHOBEN:** DocuSign-Konfiguration wird richtig gespeichert
- **BEHOBEN:** Textbausteine-Versionierung funktioniert wieder

### 🛠️ Verbesserte Settings-Klasse
- **NEU:** Automatisches Logging aller Settings-Änderungen
- **NEU:** Transaktionssicherheit bei setMany() Operationen
- **NEU:** Debug-Methode für Settings-Diagnose
- **VERBESSERT:** Fehlerbehandlung mit detaillierten Error-Logs
- **VERBESSERT:** Cache-Management für bessere Performance

### 📊 System-Logger Verbesserungen
- **NEU:** Mehrere Fallback-Ebenen für robustes Logging
- **NEU:** Automatische Tabellen-Erstellung wenn fehlend
- **VERBESSERT:** Kompatibilität mit verschiedenen DB-Strukturen
- **VERBESSERT:** Performance bei hohem Log-Aufkommen

### 🗄️ Datenbank-Stabilität
- **NEU:** Automatische Reparatur-Skripte für Settings/Logs
- **NEU:** Migrations für fehlende Tabellen-Spalten
- **VERBESSERT:** Konsistente UTF8MB4 Kollation
- **VERBESSERT:** Indizes für bessere Query-Performance

## [2.0.1] - 2025-09-06

### 🔧 Kritische Fehlerbehebung
- **BEHOBEN:** Kollationsproblem in MariaDB (`utf8mb4_uca1400_ai_ci` vs `utf8mb4_unicode_ci`)
- **BEHOBEN:** Schema-Inkonsistenz in `protocol_versions` Tabelle (`version_no` vs `version_number`)
- **BEHOBEN:** PDF-Versionierung VIEW `protocol_versions_with_pdfs` funktioniert wieder
- **BEHOBEN:** JOIN-Operationen mit expliziter Kollations-Behandlung

### 🛠️ Wartungstools
- **NEU:** `ultimate_fix.sh` - Automatische Datenbank-Reparatur
- **NEU:** `debug_collation.sh` - Kollations-Analyse-Tool
- **NEU:** `fix_collation_problem.sh` - Spezifische Kollations-Reparatur
- **NEU:** Migrationen 027-029 für Kollations- und Schema-Fixes

### 📚 Dokumentation
- **NEU:** `TROUBLESHOOTING_COLLATION.md` - Umfassende Fehlerbehebung
- **ERWEITERT:** `Notes.md` mit detaillierter Problemanalyse
- **VERBESSERT:** Schritt-für-Schritt Anleitungen für Datenbankprobleme

### ⚡ Performance & Stabilität
- **OPTIMIERT:** Alle Tabellen einheitlich auf `utf8mb4_unicode_ci`
- **VERBESSERT:** Container-Management mit automatischen Neustarts
- **STABILISIERT:** Error-Handling mit besseren Fallback-Mechanismen

## [2.0.0] - 2025-01-20

### 🎨 Major Design Overhaul
- **BREAKING:** Completely redesigned UI with AdminKit theme
- **NEW:** Ultra-minimal border-radius (4-8px) for modern look
- **NEW:** Subtle shadow system with 60-90% reduced intensity
- **NEW:** Responsive design optimized for mobile devices
- **IMPROVED:** Consistent color scheme across all components

### 🧭 Navigation & UX
- **NEW:** Breadcrumb navigation for /objects, /owners, /managers
- **NEW:** Professional terminal-style System-Log interface
- **IMPROVED:** Compact table layouts with better information density
- **IMPROVED:** Streamlined Settings pages without "bloated" icons
- **FIXED:** Consistent button styling across all management pages

### 🔧 System Administration
- **NEW:** Comprehensive System Audit Log with real-time monitoring
- **NEW:** Advanced filtering and search capabilities
- **NEW:** Auto-generated demo data for immediate testing
- **NEW:** Live monitoring indicators and status badges
- **IMPROVED:** 50 entries per page for better performance

### 👥 User Management
- **NEW:** Enhanced user role management (Admin/Hausverwaltung/Eigentümer)
- **NEW:** User assignment system for managers and owners
- **NEW:** Role-based access control with granular permissions
- **IMPROVED:** Streamlined user creation workflow

### 🏗️ Technical Infrastructure
- **NEW:** PSR-4 compliant autoloading
- **NEW:** Improved MVC architecture with better separation
- **NEW:** Enhanced error handling and logging
- **NEW:** SQL query optimization with proper indexing
- **IMPROVED:** Database schema with audit trail support

### 🎯 Code Quality
- **NEW:** Consistent `esc()` method across all controllers
- **NEW:** Type hints and strict typing throughout
- **IMPROVED:** Standardized HTML escaping and security
- **FIXED:** Multiple syntax errors and code inconsistencies

## [1.9.0] - 2025-01-15

### 🔐 Security & Authentication
- **NEW:** Enhanced session management
- **NEW:** Improved CSRF protection
- **IMPROVED:** Password hashing with bcrypt
- **FIXED:** XSS vulnerabilities in form inputs

### 📧 Email System
- **NEW:** SMTP configuration interface
- **NEW:** Email template system
- **IMPROVED:** Error handling for failed email delivery
- **FIXED:** Email encoding issues with German characters

## [1.8.0] - 2025-01-10

### 📋 Protocol Management
- **NEW:** Digital handover protocol creation
- **NEW:** PDF generation with TCPDF
- **NEW:** Protocol templates and customization
- **IMPROVED:** Form validation and error handling

### 🏢 Data Management
- **NEW:** Object management (properties/units)
- **NEW:** Owner management with company support
- **NEW:** Property manager administration
- **IMPROVED:** Database relationships and constraints

## [1.7.0] - 2025-01-05

### 🎨 Branding & Customization
- **NEW:** Logo upload functionality
- **NEW:** Custom CSS injection for theming
- **NEW:** Configurable text blocks (legal texts)
- **IMPROVED:** Brand consistency across generated documents

### ⚙️ Settings Management
- **NEW:** Centralized settings interface
- **NEW:** DocuSign integration configuration
- **NEW:** SMTP settings management
- **IMPROVED:** Settings validation and error handling

## [1.6.0] - 2024-12-30

### 🔧 System Configuration
- **NEW:** Docker containerization
- **NEW:** Environment-based configuration
- **NEW:** Database migration system
- **IMPROVED:** Deployment automation

### 📊 Database & Performance
- **NEW:** Optimized database schema
- **NEW:** Query performance improvements
- **NEW:** Connection pooling support
- **IMPROVED:** Memory usage optimization

## [1.5.0] - 2024-12-25

### 🏗️ Core Architecture
- **NEW:** MVC framework implementation
- **NEW:** Router with clean URLs
- **NEW:** Autoloader for PSR-4 compliance
- **IMPROVED:** Code organization and structure

### 🔍 Search & Filtering
- **NEW:** Global search functionality
- **NEW:** Advanced filtering options
- **NEW:** Pagination system
- **IMPROVED:** Query performance and indexing

## [1.4.0] - 2024-12-20

### 📱 Responsive Design
- **NEW:** Mobile-optimized interface
- **NEW:** Touch-friendly interactions
- **NEW:** Responsive tables and forms
- **IMPROVED:** Cross-browser compatibility

### 🎯 User Experience
- **NEW:** Improved form validation
- **NEW:** Real-time feedback
- **NEW:** Progress indicators
- **IMPROVED:** Error messaging and help text

## [1.3.0] - 2024-12-15

### 📄 Document Generation
- **NEW:** PDF template engine
- **NEW:** Customizable document layouts
- **NEW:** Digital signature preparation
- **IMPROVED:** Document quality and formatting

### 🔄 Workflow Management
- **NEW:** Protocol status tracking
- **NEW:** Approval workflows
- **NEW:** Notification system
- **IMPROVED:** Process automation

## [1.2.0] - 2024-12-10

### 🎨 UI/UX Improvements
- **NEW:** Bootstrap 5 integration
- **NEW:** Modern form components
- **NEW:** Improved typography
- **IMPROVED:** Visual hierarchy and spacing

### 🔧 Backend Enhancements
- **NEW:** Input sanitization
- **NEW:** Prepared statements for security
- **NEW:** Session timeout handling
- **IMPROVED:** Error logging and debugging

## [1.1.0] - 2024-12-05

### 🏠 Property Management
- **NEW:** Multi-property support
- **NEW:** Property categorization
- **NEW:** Location-based organization
- **IMPROVED:** Data import/export functionality

### 👤 Contact Management
- **NEW:** Contact relationship mapping
- **NEW:** Communication history
- **NEW:** Contact preferences
- **IMPROVED:** Search and filtering capabilities

## [1.0.0] - 2024-12-01

### 🚀 Initial Release
- **NEW:** Basic protocol creation system
- **NEW:** User authentication and authorization
- **NEW:** Database schema and core models
- **NEW:** Basic CRUD operations for all entities
- **NEW:** Initial web interface with Bootstrap
- **NEW:** Email notification system
- **NEW:** PDF export functionality

---

## Development Notes

### Version Numbering
- **MAJOR:** Breaking changes or significant feature additions
- **MINOR:** New features, backwards compatible
- **PATCH:** Bug fixes and small improvements

### Release Schedule
- **Major releases:** Quarterly
- **Minor releases:** Monthly
- **Patch releases:** As needed for critical fixes

### Upgrade Path
- Always backup database before upgrading
- Run migration scripts for schema changes
- Clear cache after updates
- Test all critical functions after deployment

### Deprecated Features
- **v1.x legacy forms:** Will be removed in v3.0.0
- **Old email templates:** Replaced with new system in v2.0.0
- **Basic authentication:** Enhanced security in v2.0.0

### Breaking Changes in v2.0.0
- **Database schema:** New audit_log tables required
- **CSS classes:** AdminKit theme requires new class names
- **Config format:** Environment variables now required
- **PHP version:** Minimum PHP 8.1 required
