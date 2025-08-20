# Wohnungsübergabe – Web-App (PHP + Docker)

Digitale Erfassung, Versionierung und Versand von Übergabeprotokollen (Einzug / Auszug / Zwischenprotokoll).  
Flat UI (ohne Schatten/Radien), Dark‑Mode, versionierte Rechtstexte, PDF‑Export pro Version und SMTP‑Versand.

## Inhalt
- [Features](#features)
- [Architektur & Stack](#architektur--stack)
- [Schnellstart](#schnellstart)
- [Konfiguration](#konfiguration)
- [Datenmodell](#datenmodell)
- [Pflichtenheft‑Abdeckung](#pflichtenheft-abdeckung)
- [Entwicklung & Migrationen](#entwicklung--migrationen)

## Features
- **Wizard** (4 Schritte): Adresse/WE → Kopf (Art/Mieter) → Räume (Zustand, WMZ, Fotos) → Zähler → Schlüssel & Meta (Bank/Adresse/Kontakt/Einwilligungen) → Review.
- **Eigentümer & Hausverwaltung**: in Schritt 1 wählbar bzw. Eigentümer inline anlegbar.
- **Versionierung**: jede Speicherung erzeugt `protocol_versions` vN (Snapshot der JSON‑Payload).
- **Rechtstexte** (Datenschutz, Entsorgung, Marketing) **versioniert** in den Einstellungen.
- **PDF** je Version (Dompdf), Ablage unter `storage/pdfs/<protocol_id>/v<N>.pdf`.
- **Versand** per SMTP (Eigentümer / HV / Mieter) mit Versandlog & Status‑Events.
- **UI**: Flat‑Theme (Primary `#222357`, Secondary `#e22278`), Sidebar + Topbar, **Dark‑Mode** (Toggle).

## Architektur & Stack
- **PHP 8.3 FPM**, **Nginx**, **MariaDB 11**, **phpMyAdmin**, **Mailpit** (SMTP)
- **Bootstrap 5.3** (pure, ohne Buildchain), **Poppins**‑Font
- Composer‑Libs: `vlucas/phpdotenv`, `phpmailer/phpmailer`, `dompdf/dompdf`
- Projektstruktur:
  - `backend/public` (Frontcontroller, Assets)
  - `backend/src` (Controller, Services, Helpers)
  - `migrations` (SQL‑Migrationen)
  - `backend/storage/uploads`, `backend/storage/pdfs`

## Schnellstart
```bash
docker compose up -d --build
docker compose exec app composer install
# ENV
cp backend/.env.example backend/.env
# Migrationen (in Reihenfolge einspielen)
for f in migrations/001_init.sql migrations/002_schema.sql          migrations/003_audit_softdelete.sql migrations/004_protocols.sql          migrations/005_password_reset.sql migrations/006_protocol_wizard.sql          migrations/010_app_settings.sql migrations/011_protocols_owner.sql          migrations/012_protocol_drafts_owner.sql migrations/013_protocol_manager.sql          migrations/014_legal_texts.sql migrations/015_protocol_versions_pdf.sql          migrations/016_protocol_events.sql migrations/017_email_log.sql          migrations/018_protocol_files_thumb.sql migrations/019_protocol_versions_legal_snapshot.sql
do
  cat "$f" | docker compose exec -T db sh -lc 'mariadb -uroot -proot app'
done
```

**URLs**
- App: http://localhost:8080  
- phpMyAdmin: http://localhost:8081  
- Mailpit: http://localhost:8025 (SMTP: `mailpit:1025`)

**Erstlogin**
```bash
HASH=$(docker compose exec -T app php -r 'echo password_hash("admin123", PASSWORD_BCRYPT);')
docker compose exec -T db sh -lc "mariadb -uroot -proot app -e \"DELETE FROM users WHERE email='admin@example.com'; INSERT INTO users (id,email,password_hash,role,created_at) VALUES (UUID(),'admin@example.com','$HASH','admin',NOW());\""
```

## Konfiguration
- **SMTP**: Einstellungen → SMTP (Host/Port/Sicherheit/User/Pass/From).
- **DocuSign**: Einstellungen → DocuSign (vorbereitet; Versand‑Stub vorhanden).
- **Rechtstexte**: Einstellungen → Rechtstexte (jede Änderung erzeugt neue Version).

## Datenmodell (Auszug)
- `objects` / `units`
- `protocols` (`owner_id`, `manager_id`, `payload` JSON)
- `protocol_versions` (`version_no`, `data`, `pdf_path`, `legal_snapshot`)
- `protocol_drafts` (Entwürfe, `owner_id`, `manager_id`, `data`, `step`)
- `protocol_files` (Uploads, `section`, `room_key`, `path`, `thumb_path`)
- `legal_texts` (name: datenschutz/entsorgung/marketing, `version`, `content`)
- `email_log`, `protocol_events`, `users`, `password_resets`

## Pflichtenheft‑Abdeckung
- Wohnung & Mieter, Räume (IST‑Zustand, Geruch, WMZ), **Bilder je Raum**, Zählerstände, Schlüssel, Hinweise/Versand, Bank/IBAN, **neue Meldeadresse**, Kontakt (E‑Mail, Telefon), **Einwilligungen** (Marketing/Entsorgung + **Datenschutz**) — **alles im Wizard & Editor** vorhanden.
- **Unterschriften**: Platzhalter + On‑Device‑Signaturen (Mieter/Eigentümer/optional Dritte Person); DocuSign‑Anbindung vorbereitet.

## Entwicklung & Migrationen
- Composer‐Abhängigkeiten: `docker compose exec app composer install`
- Logs: `docker compose logs -f app`, `web`, `db`
- Migrationen: siehe oben; neue SQLs im Ordner `migrations/`.
