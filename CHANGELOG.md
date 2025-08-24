# Changelog

## [0.9.0] – 2025-08-22
### Added
- Branding-Tab: Logo-Upload für PDF & Backend
- Custom-CSS in Einstellungen → Branding
- Navigation aufgeräumt: Protokolle, Neues Protokoll, Statistiken, Einstellungen
- Settings in Tabs: Stammdaten, Mail, DocuSign, Textbausteine, Benutzer, Branding

### Changed
- `View.php`: Refactor für stabiles Logo-Rendering und Custom-CSS
- Einheitliches Design (Bootstrap 5, Flat-Theme, Poppins-Font)

### Removed
- Farb-Injektion (Primär/Sekundär-Farben) aus Branding → zurück auf Theme-Defaults

### Fixed
- Logo-Anzeige über der Navigation (Fallback auf `public/images/logo.svg`)
- Duplicate `use`-Imports in `public/index.php`
- Diverse Parse-Errors in SettingsController und Routing

## [0.10.0] – 2025-08-24
### Added
- **Protokoll-Übersicht**: Suche & Filter (Volltext, Art, Zeitraum) + gruppierte Darstellung **Haus → Einheit → Protokolle**.
- **Badges** für Protokoll-Art: *Einzugsprotokoll* (grün), *Auszugsprotokoll* (rot), *Zwischenprotokoll* (gelb).
- **Wizard Schritt 4**: Dritte Person (optional) + **DocuSign‑Platzhalter**; vollständige Einbindung der Rechtstexte (*Datenschutz, Entsorgung, E‑Mail‑Marketing, Kautionshinweis*).
- **Editor (/protocols/edit)**: Kopf/Adresse/WE werden aus Payload **vorbefüllt**; Tabs **Räume/Zähler/Schlüssel & Meta** wiederhergestellt; **PDF ansehen** / **PDF per E‑Mail** / **Status- & Mail-Log** wieder aktiv.
- **PDF**: Logo (aus Einstellungen oder Fallback), Überschrift (*Einzugsprotokoll/Auszugsprotokoll/Zwischenabnahme*), Unterzeile „Ohne Anerkennung einer rechtlichen Präjudiz.“; vollständige Rechtstexte inkl. Kautionshinweis; Fußzeile mit Datum, **Seite X von Y**, **Protokoll-ID**.

### Changed
- **Wizard**: Räume in Schritt 2 mit clientseitigem **+ Raum / Entfernen** und Foto‑Uploads je Raum; Vorschlagslisten (Räume/Schlüssel) per `presets.js`.
- **/settings/texts**: WYSIWYG‑Editor (TinyMCE) und **vierter** Textbaustein „Hinweis zur Kautionsrückzahlung“; Haftungshinweis eingeblendet.

### Fixed
- Fehlende Methoden `pdf()`/`send()` in `ProtocolsController` (PDF‑Ausgabe / Mail‑Versand funktionieren wieder; bei Aufruf aus Editor **Bootstrap‑Flash** statt Roh‑JSON).
- Profil‑Speichern auf `/settings/users` (optional `updated_at`‑Spalte erzeugt bzw. Update ohne diese Spalte).
- Stabilisierung von Routing & Views; entfernte fragile Inline‑Patches durch konsistente Controller‑Implementierungen.

## [0.10.1] – 2025-08-24
### Changed
- **Migration 022**: `legal_texts.name` auf `VARCHAR(64)` (utf8mb4) angehoben; Index `(name, version)` hergestellt.
  Ermöglicht neue Textbausteine wie `kaution_hinweis` ohne „Data truncated for column 'name'“.
