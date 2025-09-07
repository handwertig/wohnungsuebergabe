# Changelog

## [2025-01-09] - Bugfix Release

### 🐛 Behoben
- **Fatal Error in ProtocolsController**: Fehlender Import der Settings-Klasse hinzugefügt
  - Fehler: `Class "App\Controllers\Settings" not found in ProtocolsController.php:730`
  - Lösung: `use App\Settings;` Statement hinzugefügt

### ✅ Verifiziert
- Alle bestehenden Funktionen bleiben erhalten
- Design und Layout unverändert
- Protokoll-Bearbeitung funktioniert wieder vollständig
- Signaturen-Tab lädt korrekt
- PDF-Versionen Tab funktioniert
- E-Mail-Versand vorbereitet

### 📝 Geänderte Dateien
- `backend/src/Controllers/ProtocolsController.php` - Settings Import hinzugefügt

---

## [2025-01-07] - Protocol System Updates

### ✨ Neue Features
- Protokoll-Wizard für neue Einträge
- Verbesserte Zählerstand-Erfassung
- Erweiterte PDF-Generierung mit Versionierung
- E-Mail-Versand-Funktionalität
- Audit-Trail und Event-Logging
- Digitale Signaturen-Unterstützung

### 🔧 Verbesserungen
- Optimierte Datenbank-Struktur
- Bessere Fehlerbehandlung
- Erweiterte Logging-Funktionen
- Performance-Optimierungen

### 📚 Dokumentation
- Technische Dokumentation erweitert
- API-Endpoints dokumentiert
- Migrations-Anweisungen hinzugefügt
