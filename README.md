# Wohnungsübergabe – Web-App (PHP + Docker)

Digitale Erfassung, Versionierung und Versand von Übergabeprotokollen (Einzug/Auszug/Zwischen).  
Enthält einen Wizard (Schritt-für-Schritt), Bilder-Uploads pro Raum, Versionierung, Soft-Delete & Audit, Benutzerverwaltung, Passwort-Reset (Mailpit) sowie eine Einstellungsseite.

## Tech-Stack

- PHP 8.3 (FPM) + Nginx  
- MariaDB 11 + phpMyAdmin  
- Mailpit (SMTP-Testserver)  
- Docker Compose  
- Abhängigkeiten: `vlucas/phpdotenv`, `phpmailer/phpmailer`

## Schnellstart (lokal via Docker)

```bash
# Projekt clonen
git clone https://github.com/handwertig/wohnungsuebergabe.git
cd wohnungsuebergabe

# ENV anlegen
cp backend/.env.example backend/.env

# Stack starten
docker compose up --build -d

# Composer-Abhängigkeiten
docker compose exec app composer install
```

### Datenbank-Migrationen (Variante A – einzeln)

```bash
docker compose exec -T db sh -lc "mariadb -uroot -proot app < /dev/stdin" < migrations/001_init.sql
docker compose exec -T db sh -lc "mariadb -uroot -proot app < /dev/stdin" < migrations/002_schema.sql
docker compose exec -T db sh -lc "mariadb -uroot -proot app < /dev/stdin" < migrations/003_audit_softdelete.sql
docker compose exec -T db sh -lc "mariadb -uroot -proot app < /dev/stdin" < migrations/004_protocols.sql
docker compose exec -T db sh -lc "mariadb -uroot -proot app < /dev/stdin" < migrations/005_password_reset.sql
docker compose exec -T db sh -lc "mariadb -uroot -proot app < /dev/stdin" < migrations/006_protocol_wizard.sql
```

### Datenbank-Migrationen (Variante B – gesammelt)

```bash
docker compose exec -T db sh -lc 'mariadb -uroot -proot app' <<'SQL'
SOURCE migrations/001_init.sql;
SOURCE migrations/002_schema.sql;
SOURCE migrations/003_audit_softdelete.sql;
SOURCE migrations/004_protocols.sql;
SOURCE migrations/005_password_reset.sql;
SOURCE migrations/006_protocol_wizard.sql;
SQL
```

### URLs

- App: http://localhost:8080  
- phpMyAdmin: http://localhost:8081 (Host: `db`, User: `root`, Passwort: `root`)  
- Mailpit UI: http://localhost:8025 (SMTP: `mailpit:1025`)

### Admin anlegen

```bash
HASH=$(docker compose exec -T app php -r 'echo password_hash("admin123", PASSWORD_BCRYPT);')
docker compose exec -T db sh -lc "mariadb -uroot -proot app -e \"DELETE FROM users WHERE email='admin@example.com'; INSERT INTO users (id,email,password_hash,role,created_at) VALUES (UUID(),'admin@example.com','$HASH','admin',NOW());\""
```

**Login:** `admin@example.com` / `admin123` (bitte danach ändern)

## Navigation

- **Protokolle** – Übersicht aller Protokolle  
- **Neues Protokoll** – startet den Wizard  
- **Einstellungen** – Stammdaten (Eigentümer, Hausverwaltungen, Objekte, Wohneinheiten) & Benutzerverwaltung (nur Admin)

## Übergabeprotokoll-Wizard

**Schritt 1 – Adresse & Kopf**  
- Ort, PLZ, Straße, Hausnummer, WE-Bezeichnung, optional Etage  
- Protokolltyp (Einzug/Auszug/Zwischen), Mietername, Zeitstempel  
- Objekt und Wohneinheit werden automatisch angelegt bzw. wiederverwendet

**Schritt 2 – Räume**  
- Dynamisch Räume hinzufügen (Name, IST-Zustand, Geruch, Abnahme, Wärmemengenzähler Nr./Stand)  
- Fotos je Raum (JPG/PNG, max. 10 MB je Datei)  
- Uploads unter `backend/storage/uploads/<draft_id>/`

**Schritt 3 – Zählerstände**  
- Strom (Wohneinheit / Allgemein), Gas (Wohneinheit / Allgemein), Wasser (K/W Küche, K/W Bad, Waschmaschine)

**Schritt 4 – Schlüssel & Meta**  
- Schlüssel (Bezeichnung, Anzahl, Nummer)  
- Hinweise/Bemerkungen, Versand-Flags (Eigentümer / Verwaltung)

**Review & Abschluss**  
- Zusammenfassung → Speichern erzeugt Protokoll und Version v1  
- Uploads werden vom Entwurf auf das Protokoll umgehängt

## Datenmodell (Auszug)

- `objects` / `units` – Objektadresse & Wohneinheit  
- `protocols` – Protokoll-Kopf + `payload` (JSON)  
- `protocol_versions` – Snapshots v1, v2, …  
- `protocol_drafts` – Entwürfe für den Wizard  
- `protocol_files` – Uploads (Draft/Protocol, optional Raum-Referenz)  
- Soft-Delete: `deleted_at` in owners/managers/objects/units/protocols  
- Audit-Log: `audit_log` (create/update/soft_delete)  
- `users` (admin/staff), `password_resets`

## Sicherheit

- Session-Handling: `httponly`, `SameSite=Lax`  
- Passwörter: Bcrypt  
- Rollen: admin, staff (Benutzerverwaltung nur Admin)

## Nützliche Kommandos

```bash
# Logs
docker compose logs -f app
docker compose logs -f db
docker compose logs -f web

# Composer
docker compose exec app composer install
docker compose exec app composer dump-autoload

# DB Shell
docker compose exec -it db mariadb -uroot -proot app
```

## Smoke-Tests

1. Login mit `admin@example.com` / `admin123`  
2. Einstellungen öffnen (Stammdaten/Benutzer)  
3. Neues Protokoll über Wizard anlegen, Raum + Foto hinzufügen, abschließen  
4. Protokolle prüfen → Version v1 sichtbar  
5. Passwort-Reset über Mailpit testen

## Roadmap

- PDF-Export im CI-Design + SMTP-Versand  
- DocuSign-Integration (zwei Pflichtsignaturen + optionale Mitzeichner)  
- Suche/Filter/Paginierung in Protokollen + CSV-Export  
- Settings: SMTP, DocuSign, Rechtstexte (Versionierung), Branding  
- Wizard: UI-Verbesserungen und Feld-Validierungen
