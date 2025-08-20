# Wohnungsübergabe – Web-App (PHP + Docker)

Flat UI (ohne Schatten/Radien), Dark‑Mode, versionierte Rechtstexte, PDF‑Export und SMTP‑Versand.
Protokolle per Wizard erfassen (Einzug/Auszug/Zwischen), versionieren, als PDF speichern und versenden.

## Highlights (v0.6+)
- **Flat Theme** (Primary `#222357`, Secondary `#e22278`), App‑Shell mit Sidebar & Topbar, **Dark‑Mode** (Toggle).
- **Wizard**: Schritt 1 Reihenfolge (Adresse → Kopf → Eigentümer → Hausverwaltung), Sticky‑Buttons.
- **Editor**: vollständige Tabs (Kopf/Räume/Zähler/Schlüssel/Meta), Eigentümer/HV, **Foto‑Uploads pro Raum** (Thumbnails), Pflichtvalidierung.
- **Rechtstexte** (Datenschutz/Entsorgung/Marketing) versioniert in Einstellungen.
- **PDF‑Export** (Dompdf), Speicherung unter `storage/pdfs/<protocol_id>/v<N>.pdf`.
- **SMTP‑Versand** an Eigentümer/Hausverwaltung/Mieter, Versandlog & Status‑Events.

## Schnellstart
```bash
docker compose up -d --build
docker compose exec app composer install
# Migrationen einspielen (in der Reihenfolge)
for f in migrations/001_init.sql migrations/002_schema.sql \
         migrations/003_audit_softdelete.sql migrations/004_protocols.sql \
         migrations/005_password_reset.sql migrations/006_protocol_wizard.sql \
         migrations/010_app_settings.sql migrations/011_protocols_owner.sql \
         migrations/012_protocol_drafts_owner.sql migrations/013_protocol_manager.sql \
         migrations/014_legal_texts.sql migrations/015_protocol_versions_pdf.sql \
         migrations/016_protocol_events.sql migrations/017_email_log.sql \
         migrations/018_protocol_files_thumb.sql migrations/019_protocol_versions_legal_snapshot.sql
do
  cat "$f" | docker compose exec -T db sh -lc 'mariadb -uroot -proot app'
done

### 3.2 NOTES für Release

```bash
cat > NOTES.md <<'MD'
# v0.7.0 – Flat UI, Dark‑Mode, Versand/Status, PDF & Rechtstexte

- UI auf **Flat** umgestellt (keine Schatten/Radien), Primary `#222357`, Secondary `#e22278`.
- **Dark‑Mode** via Topbar‑Toggle (Persistenz per localStorage).
- Wizard Schritt 1 mit korrekter Reihenfolge + Sticky‑Footer.
- Editor vollständig (Adresse, Eigentümer/HV, Räume, Zähler, Schlüssel, Bank, neue Meldeadresse, Einwilligungen, optional Dritte Person).
- Foto‑Uploads pro Raum (MIME/Größe geprüft) + **Thumbnails**.
- **Rechtstexte** versioniert (Datenschutz/Entsorgung/Marketing).
- **PDF‑Export** (Dompdf) je Version; Anzeige/Download via Route.
- **SMTP‑Versand** an Eigentümer/Hausverwaltung/Mieter inkl. Versandlog (email_log) und Status‑Events (protocol_events).
