# v0.6.0 – Wizard/Editor Feinschliff, Rechtstexte & PDF, Versand/Status

## Neu
- Wizard Schritt 1: Reihenfolge fix, Eigentümer (Dropdown + Inline) & Hausverwaltung, Labels (Einzugs-/Auszugs-/Zwischenprotokoll).
- Editor: Adress-/Kontakt-/Bank-/Einwilligungs-Felder ergänzt, Raum-Foto-Uploads inkl. Thumbnails je Raum.
- Rechtstexte: versionierbar (Datenschutz, Entsorgung, Marketing) in Einstellungen.
- PDF-Export (Dompdf): CI-nahes Template, Speicherung je Version, Anzeige/Download.
- Versand: PDF per SMTP an Eigentümer/Hausverwaltung/Mieter, Versandlog (email_log) + Status-Events (protocol_events).
- UX: Übersichtsaccordion zeigt „Einheit <Nr>“, Typ-Badges, Login leitet auf /protocols.

## Migrationen
- 011/012/013: owner_id/manager_id für protocols & drafts
- 014: legal_texts (versioniert)
- 015: pdf_path in protocol_versions
- 016: protocol_events
- 017: email_log

## Hinweise
- SMTP in Einstellungen pflegen (Host/Port/Sicherheit/User/Pass/From).
- Rechtstexte anpassen (neue Versionen bei Änderungen).
- PDF unter storage/pdfs/<protocol_id>/vN.pdf; Uploads unter storage/uploads.
