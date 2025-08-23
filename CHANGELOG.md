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
