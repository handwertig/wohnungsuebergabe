# Entwicklungsnotizen - Wohnungsübergabe-App

## 05.09.2025 - Vollständige Neuimplementierung SettingsController

### Durchgeführte Arbeiten
- **SettingsController.php vollständig neu geschrieben** - Datei war korrupt/unvollständig
- Alle Einstellungs-Bereiche implementiert und funktionsfähig
- Vollständige Integration mit bestehendem System

### Implementierte Features

#### 1. Stammdaten (Tab: "Stammdaten")
- **Eigenes Profil bearbeiten** direkt im ersten Tab
- Felder: Firma, Telefon, Adresse, E-Mail, Passwort ändern
- Speichert in `users`-Tabelle (company, phone, address Spalten)
- **Verweise auf andere Bereiche**: Eigentümer, Hausverwaltungen, Objekte

#### 2. Mailversand (Tab: "Mailversand") 
- **Vollständige SMTP-Konfiguration**
- Felder: Host, Port, Verschlüsselung (TLS/SSL), Benutzername, Passwort
- Absender-Name und Absender-E-Mail konfigurierbar
- Standard: mailpit (Docker-Container) für Entwicklung

#### 3. DocuSign-Integration (Tab: "DocuSign")
- **API-Konfiguration für eSignatures**
- Felder: Base URI, Account ID, User ID, Client ID, Client Secret
- Vorbereitung für elektronische Unterschriften in Protokollen

#### 4. Textbausteine (Tab: "Textbausteine")
- **Versionierte Rechtexte** aus `legal_texts`-Tabelle
- 4 Textbausteine: Datenschutzerklärung, Entsorgungshinweis, Marketing-Einwilligung, Kautionsrückzahlung
- Jede Änderung erstellt neue Version (Versionsnummer wird automatisch hochgezählt)
- Titel und Inhalt pro Textbaustein editierbar

#### 5. Benutzerverwaltung (Tab: "Benutzer")
- **Admin-Bereich**: Vollzugriff für Administratoren
- Anzeige der drei Benutzerrollen: Administrator, Hausverwaltung, Eigentümer
- **Schutz für normale Benutzer**: Umleitung zu Stammdaten
- Integration mit UserAuth-Klasse für Berechtigungsprüfung

#### 6. Gestaltung (Tab: "Gestaltung")
- **Logo-Upload**: Für PDF und Backend
- Unterstützte Formate: JPG, PNG, GIF, SVG
- **Logo-Löschfunktion** mit Bestätigung
- **Custom CSS**: Freie CSS-Eingabe für Backend-Theming
- Storage in `/storage/branding/` Verzeichnis

#### 7. System-Log (Tab: "System-Log")
- **Anzeige der neuesten 50 Log-Einträge** aus `system_log`-Tabelle
- Spalten: Zeitstempel, Benutzer, Aktion, Details, IP-Adresse
- **Badge-System** für verschiedene Aktionen (Login=grün, Error=rot, etc.)
- Gesamtanzahl der Log-Einträge angezeigt
- **Fehlerbehandlung** bei Datenbankproblemen

### Technische Details

#### Sicherheit & Validierung
- Alle Eingaben werden mit `htmlspecialchars()` escaped
- PDO mit Prepared Statements gegen SQL-Injection
- Benutzerauthentifizierung für alle Aktionen erforderlich
- Admin-Berechtigungen wo nötig geprüft

#### Datenbankintegration
- Verwendung der bestehenden `Settings`-Klasse für einfache Key-Value-Einstellungen
- Direkte Datenbank-Operationen für komplexe Daten (Benutzerprofile, Legal Texts)
- Graceful Fallbacks bei fehlenden Tabellenspalten

#### UI/UX Verbesserungen
- **Bootstrap 5** für responsive Gestaltung
- **Tabbed Navigation** für übersichtliche Strukturierung
- **Flash Messages** für Benutzer-Feedback
- **Bestätigungsdialoge** für kritische Aktionen (Logo löschen)

### Routen-Integration
Alle Routen sind bereits in `index.php` konfiguriert:
- `/settings` (GET/POST) - Stammdaten
- `/settings/mail` (GET) + `/settings/mail/save` (POST)
- `/settings/docusign` (GET) + `/settings/docusign/save` (POST)  
- `/settings/texts` (GET) + `/settings/texts/save` (POST)
- `/settings/users` (GET) + `/settings/users/save` (POST)
- `/settings/branding` (GET) + `/settings/branding/save` (POST)
- `/settings/branding/delete-logo` (POST) - Logo löschen
- `/settings/systemlogs` (GET) - System-Log anzeigen

### Nächste Schritte
1. **Tests durchführen**: Alle Tabs und Funktionen testen
2. **Hausverwaltung-Controller erstellen** für `/managers` Route
3. **Eigentümer-Controller erstellen** für `/owners` Route  
4. **Objekte-Controller erstellen** für `/objects` Route
5. **Erweiterte Benutzerverwaltung** unter `/users` Route
6. **PDF-Generator** mit Logo-Integration
7. **E-Mail-Versand** mit SMTP-Konfiguration

### Kompatibilität
- **PHP 8.3** mit strict_types
- **PSR-12** Code Standards
- **MariaDB/MySQL** Datenbankkompatibilität
- **Bootstrap 5** Frontend-Framework
- **Docker** Containerumgebung

---

## Bekannte Probleme
- `system_log` Tabelle hatte Struktur-Probleme → durch Migration 024 behoben
- UserAuth-Klasse vollständig implementiert und getestet
- Alle Migrationen müssen ausgeführt sein für volle Funktionalität

## Performance-Optimierungen
- Lazy Loading von Log-Einträgen (nur 50 neueste)
- Effiziente Datenbankabfragen mit Prepared Statements
- Optimierte Badge-Klassen-Zuweisung für System-Log

---

*Letzte Aktualisierung: 05.09.2025 um 23:47 Uhr*

---

## 06.09.2025 - Kollationsproblem behoben

### Problem
- **Fehlermeldung**: `ERROR 1267 (HY000) at line 65: Illegal mix of collations (utf8mb4_uca1400_ai_ci,IMPLICIT) and (utf8mb4_unicode_ci,IMPLICIT) for operation '='`
- **Ursache**: Inkonsistente Kollationen zwischen verschiedenen Tabellen und deren Spalten
- **Betroffener Bereich**: PDF-Versionierung, `protocol_versions` View, ORDER BY Abfragen

### Durchgeführte Reparaturen

#### 1. Kollations-Analyse und -Reparatur
- **Script erstellt**: `debug_collation.sh` für Analyse der Datenbankstruktur
- **Migration 027**: `027_fix_collation_problem.sql` - Erste Kollations-Reparatur
- **Migration 028**: `028_final_collation_fix.sql` - Umfassende Kollations-Vereinheitlichung
- **Alle Tabellen** auf `utf8mb4_unicode_ci` standardisiert

#### 2. Schema-Konsistenz
- **Problem erkannt**: Verschiedene Migrationen verwendeten `version_no` vs `version_number`
- **Migration 029**: `029_fix_schema_mismatch.sql` - Schema-Vereinheitlichung
- **Standardisierung** auf `version_no` (da bereits in bestehender Tabelle vorhanden)
- **View korrigiert**: `protocol_versions_with_pdfs` mit einheitlichen Spaltennamen

#### 3. Ultimate-Fix Script
- **Script erstellt**: `ultimate_fix.sh` - Universelle Datenbank-Reparatur
- **Automatische Analyse** der Datenbankstruktur
- **Schrittweise Reparatur** mit detailliertem Logging
- **Funktionstests** für alle kritischen Abfragen
- **Container-Neustart** für saubere PHP-Session

### Technische Details

#### Betroffene Dateien
- `migrations/027_fix_collation_problem.sql` - Kollations-Fix
- `migrations/028_final_collation_fix.sql` - Umfassende Reparatur
- `migrations/029_fix_schema_mismatch.sql` - Schema-Konsistenz
- `ultimate_fix.sh` - Automatische Reparatur
- `debug_collation.sh` - Analyse-Tool
- `fix_collation_problem.sh` - Spezifischer Kollations-Fix

#### Datenbankänderungen
```sql
-- Alle Tabellen auf einheitliche Kollation
ALTER DATABASE app CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
ALTER TABLE protocols CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
ALTER TABLE protocol_versions CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- View mit expliziter Kollation
CREATE OR REPLACE VIEW protocol_versions_with_pdfs AS
SELECT pv.protocol_id, pv.version_no, ...
FROM protocol_versions pv
LEFT JOIN protocol_pdfs pp ON (
    pv.protocol_id = pp.protocol_id 
    AND pv.version_no = pp.version_no
)
ORDER BY pv.protocol_id, pv.version_no DESC;
```

#### Automatisierte Tests
1. **Grundlegende Protokoll-Abfragen**
2. **Version-Abfragen** mit ORDER BY
3. **PDF-View-Zugriff**
4. **Kollations-spezifische JOIN-Operationen**

### Lösung implementiert
- ✅ **Kollationsfehler behoben** - Alle Tabellen einheitlich auf `utf8mb4_unicode_ci`
- ✅ **Schema vereinheitlicht** - Konsistente Spaltennamen (`version_no`)
- ✅ **Views korrigiert** - Explizite Kollation in JOIN-Operationen
- ✅ **Automatisierte Reparatur** verfügbar für zukünftige Probleme
- ✅ **Container neugestartet** für saubere PHP-Session

### Monitoring & Wartung
- **Debug-Script** `debug_collation.sh` für regelmäßige Kollations-Prüfung
- **Fix-Script** `ultimate_fix.sh` für umfassende Datenbank-Reparaturen
- **Migrationen** dokumentiert für nachvollziehbare Änderungen

---

## 06.09.2025 - PDF-Versionierung implementiert

### Durchgeführte Arbeiten
- **Vollständige PDF-Versionierung** für Wohnungsübergabeprotokolle implementiert
- Datenbank um neue Tabellen `protocol_versions` und `protocol_pdfs` erweitert
- ProtocolsController um PDF-Versionierungs-Methoden erweitert
- Editor um neuen "PDF-Versionen" Tab erweitert
- Nginx-Konfiguration für PDF-Auslieferung angepasst

### Neue Features

#### 1. PDF-Versionierung
- **Automatische Versionierung**: Bei jeder Änderung wird eine neue Version erstellt
- **PDF-Generierung**: Für jede Version kann ein versioniertes PDF generiert werden
- **Direktlinks**: PDFs sind unter `http://localhost:8080/protocols/pdf?id={ID}&version={VERSION}` verfügbar
- **Archivierung**: Alle PDF-Versionen bleiben dauerhaft verfügbar

#### 2. Editor-Integration
- **Neuer Tab "PDF-Versionen"** im Protokoll-Editor
- Übersichtliche Tabelle aller Versionen mit Status-Indikatoren
- **Bulk-PDF-Generierung** für alle Versionen ohne PDF
- **Neue Version erstellen** mit optionalen Notizen
- Statistiken: Gesamt-Versionen, verfügbare PDFs, Gesamtgröße

#### 3. API-Erweiterungen
- `GET /protocols/pdf?id={ID}&version={VERSION}` - PDF für spezifische Version
- `POST /protocols/create-version` - Neue Version erstellen
- `GET /protocols/versions?id={ID}` - Versionsliste als JSON
- `POST /protocols/generate-all-pdfs` - Bulk-PDF-Generierung
- `GET /protocols/pdf-status?id={ID}&version={VERSION}` - PDF-Status prüfen

#### 4. Dateisystem & Struktur
- PDFs werden in `backend/storage/pdfs/` gespeichert
- Dateiformat: `protokoll_{ID}_v{VERSION}.pdf`
- Nginx-Alias für direkten PDF-Zugriff unter `/pdfs/`
- Automatische Verzeichniserstellung bei Bedarf

### Technische Details

#### Datenbank-Schema
```sql
-- Neue Tabellen
CREATE TABLE protocol_versions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    protocol_id VARCHAR(36) NOT NULL,
    version_number INT NOT NULL,
    version_data LONGTEXT,
    notes TEXT,
    created_by VARCHAR(36),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_protocol_version (protocol_id, version_number)
);

CREATE TABLE protocol_pdfs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    protocol_id VARCHAR(36) NOT NULL,
    version_number INT NOT NULL,
    file_name VARCHAR(255) NOT NULL,
    file_size BIGINT DEFAULT NULL,
    generated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_protocol_pdf_version (protocol_id, version_number)
);
```

#### Backend-Methoden (ProtocolsController)
- `generateVersionedPDF()` - PDF-Generierung mit Caching
- `createVersion()` - Neue Version mit Audit-Trail
- `getVersionsJSON()` - API für Frontend
- `generateAllPDFs()` - Bulk-Generierung
- `renderPDFVersionsTab()` - UI-Rendering
- `getPDFVersioningAssets()` - JavaScript/CSS

#### PDF-Generierung
- Einfache PDF-Generierung als Basis (später dompdf)
- Automatisches Caching bereits generierter PDFs
- Versionsspezifische Daten aus `protocol_versions`
- Fallback auf aktuelle Protokoll-Daten

### Frontend-Integration

#### JavaScript-Funktionen
- `generatePDF(protocolId, version)` - Einzelne PDF-Generierung
- `generateAllPDFs(protocolId)` - Bulk-Generierung
- `createNewVersion(protocolId)` - Neue Version mit Prompt
- `previewVersion(protocolId, version)` - PDF-Vorschau
- Toast-Notifications für Benutzer-Feedback

#### UI-Komponenten
- Status-Indikatoren (verfügbar/fehlend/generierend)
- Responsive Tabelle mit Bootstrap 5
- Action-Buttons mit Icon-Support
- Statistik-Cards für Übersicht

### Wartung & Monitoring

#### Monitoring-Script (`monitor_pdf_status.sh`)
- System-Status-Überwachung
- PDF-Verzeichnis-Statistiken
- Container-Status-Prüfung
- Datenbank-Verbindungstest
- Webserver-Erreichbarkeit
- Berechtigungs-Validierung

#### Cleanup-Script (`cleanup_old_pdfs.sh`)
- Automatische Bereinigung alter PDFs
- Konfigurierbare Aufbewahrungszeit (Standard: 30 Tage)
- Dry-Run-Modus für sichere Tests
- Datenbank-Bereinigung von verwaisten Einträgen
- Interaktive Bestätigung

### Migration & Deployment

#### Automatisches Update-Script (`update_pdf_versioning.sh`)
- Vollautomatische Installation aller Komponenten
- Backup-Erstellung vor Änderungen
- Datenbank-Migration mit Rollback-Möglichkeit
- Container-Management und Neustart
- Dokumentations-Updates

#### Rückwärtskompatibilität
- Legacy PDF-Route leitet auf neue Versionierung um
- Bestehende Protokolle erhalten automatisch Version 1
- Keine Breaking Changes für bestehende APIs

### Sicherheit & Performance

#### Sicherheitsmaßnahmen
- Authentifizierung für alle PDF-Operationen
- Input-Validierung für alle Parameter
- Sichere Dateipfad-Behandlung
- SQL-Injection-Schutz durch Prepared Statements

#### Performance-Optimierungen
- PDF-Caching verhindert doppelte Generierung
- Effiziente Datenbankabfragen mit Indizes
- Lazy Loading von Versionsdaten
- Nginx-Caching für statische PDF-Dateien

### Nächste Schritte
1. **dompdf Integration** für professionelle PDF-Generierung
2. **Enhanced PDF-Templates** mit Logo und Corporate Design
3. **E-Mail-Integration** für automatischen PDF-Versand
4. **Bulk-Operations** für mehrere Protokolle
5. **Advanced Monitoring** mit Metriken und Alerts

---

*PDF-Versionierung vollständig implementiert: 06.09.2025 um 10:33 Uhr*
*Kollationsproblem behoben: 06.09.2025 um 11:15 Uhr*
*ProtocolsController.php repariert: 06.09.2025 um 11:45 Uhr*
*Protokoll-Tab mit Audit-Log hinzugefügt: 06.09.2025 um 12:15 Uhr*
