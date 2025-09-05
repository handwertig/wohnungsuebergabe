# Wohnungsübergabe – Entwicklernotizen

Dieses Dokument dient als Übersicht für Entwickler, Architekturentscheidungen und offene ToDos.

---

## Architekturentscheidungen

- **Backend:** PHP 8.3 mit PDO (MariaDB), ohne Framework → leichtgewichtig, transparent.
- **Frontend:** Bootstrap 5, Flat-Theme, Dark-/Lightmode via Toggle.
- **Routing:** Einfaches Switch-Case in `public/index.php`.
- **DB:** MariaDB 11, Tabellen versioniert (Soft-Delete via `deleted_at`, Audit via `protocol_events`).
- **PDF:** Dompdf (lokal), DocuSign-Signaturintegration (remote).
- **Uploads:** Raum-Fotos im Verzeichnis `backend/storage/uploads`, Nginx-Alias `/uploads/`.
- **Versand:** SMTP konfigurierbar via `.env`, lokal Mailpit.

---

## Aktuelle Features (Stand v0.7+)

- Auth (Login, Logout, Passwort-Reset, Benutzerverwaltung)
- Protokoll-Wizard (4 Schritte)
- Protokoll-Editor mit Versionierung
- Soft-Delete + Audit
- Uploads (Raum-Fotos)
- PDF-Export + Mail-Versand
- Rechtstexte (Datenschutz, Einwilligungen) versioniert
- Statistikseite (`/stats`) mit:
  - Fluktuation Haus & Wohnung
  - Ø Mietdauer
  - Ø Leerstand
  - Saisonale Spitzen
  - Zähler-Analysen
  - Qualitätsscore

---

## Fixes (v0.7.2) - 05.09.2025

- **PDF-Anzeige repariert**: Controller `pdf()` Methode vollständig implementiert mit PdfService-Integration
- **Historie im Editor wiederhergestellt**: `/protocols/edit` zeigt jetzt Events und E-Mail-Versandlog unter dem Formular
- **Mail-Versand funktioniert**: `send()` Methode mit PHPMailer, SMTP-Settings und Logging in `email_log` + `protocol_events`
- **Routing korrigiert**: `/protocols/edit` zeigt jetzt die richtige `edit()` Methode mit Historie-Anzeige

## Erledigte Aufgaben (2025-09-05)

✅ **PDF-System repariert**
   - Verbesserte Fehlerbehandlung und Debugging in PDF-Controller
   - Robuste Verzeichnis-Erstellung im PdfService
   - Cache-Header für PDF-Ausgabe hinzugefügt
   - Detailliertes Error-Logging implementiert

✅ **Historie & Versand-Tab hinzugefügt**
   - Neuer Tab "Historie & Versand" im Protokoll-Editor
   - Dreispaltige Anzeige: Versionen, Ereignisse, E-Mail-Versand
   - Event-Logging beim Speichern von Versionen verbessert
   - Schnellversand-Buttons direkt verfügbar

---

## Offene ToDos / Backlog

1. **PDF-Hardening**
   - Logo + Corporate Design finalisieren
   - Signaturfelder schöner platzieren
   - Mehrsprachigkeit prüfen (DE/EN)

2. **DocuSign**
   - Vollintegration mit Callback (Webhook)
   - Status in DB speichern
   - PDF-Austausch automatisieren

3. **UX-Feinschliff**
   - Mobile Navigation optimieren
   - Tooltips konsistenter einsetzen
   - Autocomplete für Eigentümer / Hausverwaltungen

4. **Validierung**
   - Clientseitige Checks (IBAN, E-Mail, Pflichtfelder)
   - Soft-Warnings (fehlende Zählerstände, Räume ohne Fotos)

5. **Statistiken**
   - Ø Mietdauer pro Haus verbessern (Tage exakter berechnen)
   - Globale Leerstandsquote fertigstellen
   - Weitere KPIs (Durchlaufzeit Wizard → Save)

6. **Security**
   - CSRF-Token pro Formular
   - Session-Härtung (Secure, HttpOnly, SameSite)
   - Logging von fehlgeschlagenen Logins

---

## Git-Workflow

- Feature-Branches nutzen: `feature/...`
- Commits klein & sprechend
- Vor Push: `git fetch && git rebase origin/main`
- Konflikte lösen → `git rebase --continue`
- Push → `git push origin main`

---

## Release-Plan (v0.8 → v1.0)

- v0.8: PDF-/UX-Hardening, Notes.md/Readme stabil
- v0.9: DocuSign produktiv, Rechtstexte final
- v1.0: Rollout produktiv bei Handwertig GmbH
