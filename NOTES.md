# v0.5.0 – Wizard + Eigentümer/Hausverwaltung, Signaturen, DocuSign-Settings, UI-Polish

## Highlights
- **Wizard Schritt 1**: Eigentümer (Dropdown oder Inline-Neuanlage) & Hausverwaltung (Dropdown). Owner-Snapshot im Payload, `owner_id/manager_id` in Draft/Protokoll.
- **Signaturen (On-Device)**: Mieter/Eigentümer/Mitzeichner unterschreiben direkt per Touch/Mouse; PNG gespeichert; Status 'signed'. Signer-Verwaltung (Rolle, Reihenfolge, Pflicht).
- **DocuSign**: Sichtbare Einstellungen (Mode, Account-ID, Base-URL, Client-ID, Secret, Redirect-URI, Webhook-Secret), Versand-Button (Stub) mit Konfig-Prüfung.
- **Editor (Bestands-Protokolle)**: Tabs (Kopf/Räume/Zähler/Schlüssel+Meta), dynamisches Hinzufügen/Entfernen, HTML5-Validation, Tooltips, Thumbnails vorhandener Raum-Fotos.
- **Übersicht**: Accordion Objekt→Einheit→Versionen; Typ-Badges; WE-Label als „Einheit <Nr>“; CSV-Export & Filter.
- **Infra**: /uploads via Nginx alias; neue Indizes für zügige Listenabfragen.

## Migrationen
- `009_signers.sql` – protocol_signers / protocol_signatures
- `010_app_settings.sql` – app_settings (persistente Settings)
- `011_protocols_owner.sql` – owner_id in protocols
- `012_protocol_drafts_owner.sql` – owner_id in protocol_drafts
- `013_protocol_manager.sql` – manager_id in protocols/drafts

## Hinweise
- Protokoll-Art intern weiterhin `einzug|auszug|zwischen`; UI-Labels vereinheitlicht.
- DocuSign: Integration Key/Secret in Admin (Apps & Keys) anlegen, Redirect-URI setzen (z. B. /docusign/callback).
- Uploads liegen unter `backend/storage/uploads`; via `/uploads/...` ausgeliefert (keine PHP-Ausführung).
