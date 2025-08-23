# Changelog – Wohnungsübergabe

Alle nennenswerten Änderungen des Projekts sind hier dokumentiert.  
Format orientiert sich an [Keep a Changelog](https://keepachangelog.com/) und [Semantic Versioning](https://semver.org/).

---

## [Unreleased]
### Removed
- Alte Dashboard-Seite und Route (`/dashboard`)

---

## [0.7.0] – 2025-08-21
### Added
- Statistik-Seite `/stats` mit Fluktuation, Ø Mietdauer, Leerstand, Zähler-Analysen, Qualitätsscore
- Globaler Qualitätsscore als KPI
- Erweiterte Validierung im Wizard (Soft-Hinweise statt Blocker)
- UX: Tooltips für IBAN, Geruch, IST-Zustand
- Mobile Navigation (responsive/offcanvas)

### Changed
- Wizard: Nach Abschluss Redirect auf `/protocols/edit?id=…`
- Einheitliche Schreibweise der Protokollarten (Einzugsprotokoll, Auszugsprotokoll, Zwischenprotokoll)
- Flat-Theme: Farben, Buttons, Abgrenzungen angepasst

---

## [0.6.0] – 2025-08-18
### Added
- PDF-Export mit Dompdf
- Versand-Buttons an Eigentümer, HV, Mieter
- Versand- und Ereignislog in Protokoll-Detailseite
- Rechtstexte (Datenschutz, Entsorgung, Marketing) versioniert
- Notes.md für Entwicklernotizen

### Changed
- Wizard erweitert: Eigentümer & Hausverwaltung auswählbar
- Verbesserte Formular-Validierungen
- Design: Navigation reduziert auf Protokolle / Neues Protokoll / Einstellungen

---

## [0.5.0] – 2025-08-15
### Added
- Upload & Preview von Raum-Fotos
- Schlüsselverwaltung im Protokoll
- Soft-Delete & Audit-Logging
- Settings-Seite (SMTP, DocuSign, Rechtstexte)

---

## [0.4.0] – 2025-08-12
### Added
- Wizard (4 Schritte) Grundgerüst
- Speicherung von Drafts
- Erste Validierungen (Pflichtfelder)
- Navigation mit Protokolle, Neues Protokoll, Einstellungen

---

## [0.3.0] – 2025-08-10
### Added
- Benutzerverwaltung (Anlegen, Bearbeiten, Löschen)
- Passwort-Reset via Mailpit
- Rollen: Admin / Staff

---

## [0.2.0] – 2025-08-08
### Added
- Login / Logout
- Dashboard (Testseite, später entfernt)
- Authentifizierung mit Sessions

---

## [0.1.0] – 2025-08-06
### Added
- Projekt-Setup (Docker, PHP, MariaDB, Mailpit, Nginx, phpMyAdmin)
- Erste Migrationen (User, Owners, Managers, Units, Protocols)
- Health-Check `/health`
