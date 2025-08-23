# Wohnungsübergabe-Software

## Beschreibung

Die **Wohnungsübergabe-Software** ist ein webbasiertes System zur Durchführung,
Dokumentation und Archivierung von Wohnungsübergaben (Einzug, Auszug, Zwischenprotokoll).
Sie ersetzt die bisherigen Papierprotokolle durch digitale Formulare, automatisiert
die PDF-Erstellung, ermöglicht Versand per E-Mail (SMTP/Mailpit), bindet DocuSign
für digitale Unterschriften ein und stellt umfassende Statistiken zur Verfügung.

Zielgruppe sind Projektentwickler, Eigentümer, Hausverwaltungen und Vermieter, die
Übergaben rechtssicher, transparent und effizient dokumentieren möchten.

## Features

- **Benutzerverwaltung** (Login, Rollen, Passwort-Reset via Mailpit)
- **Übergabeprotokoll-Wizard (4 Schritte)**
  - Schritt 1: Adresse, Wohneinheit, Eigentümer, Hausverwaltung, Kopf
  - Schritt 2: Räume inkl. IST-Zustand, Geruch, Wärmezähler, Fotos
  - Schritt 3: Zählerstände (Strom, Wasser, Gas, Allgemein)
  - Schritt 4: Schlüssel, Bankdaten, Kontaktdaten, Einwilligungen (DSGVO, Marketing, Entsorgung)
- **Upload & Fotos** (Raum-Fotos, Preview im Editor)
- **DocuSign-Integration** (Mieter, Eigentümer, optionale dritte Person)
- **PDF-Export** mit Musterlogo, Rechtstexten, Versionsnummerierung
- **E-Mail-Versand** der PDFs an Eigentümer / Hausverwaltung / Mieter
- **Audit & Soft-Delete** (Änderungen & Löschungen protokolliert)
- **Statistiken**
  - Fluktuation nach Haus und Wohnung
  - Quote und absolute Zahlen
  - Einzugs-/Auszugs-/Zwischenprotokolle
  - Ø Mietdauer, Ø Leerstand, globale Leerstandsquote
  - Saisonale Spitzen
  - Zähler-Analysen (Δ Verbrauch Einzug/Auszug)

## Technologie-Stack

- **Backend:** PHP 8.3 (FPM), PDO, Composer  
- **Frontend:** Bootstrap 5, eigenes Theme (Dark-/Lightmode, Flat-Design, #222357 / #e22278)  
- **DB:** MariaDB 11 (MySQL-kompatibel)  
- **Mail:** Mailpit (lokal) / SMTP  
- **PDF:** Dompdf  
- **Signatur:** DocuSign (API-Integration)  
- **Docker-Setup:** Nginx, PHP-FPM, MariaDB, phpMyAdmin, Mailpit  
- **Entwicklung:** GitHub-Repo, Notes.md für Entwicklerhinweise

## Installation & Setup

### Voraussetzungen
- Docker & Docker Compose
- Git
- (optional) PHP CLI

### Schritte

    # Repository klonen
    git clone https://github.com/handwertig/wohnungsuebergabe.git
    cd wohnungsuebergabe

    # Container starten
    docker compose up -d --build

    # Abhängigkeiten installieren (falls Composer-Dateien vorhanden)
    docker compose exec app bash -lc 'if [ -f composer.json ]; then composer install --no-interaction; fi'

    # Migrationen einspielen
    cat migrations/*.sql | docker compose exec -T db sh -lc 'mariadb -uroot -proot app'

    # Admin-User anlegen (Einzeiler)
    docker compose exec app php -r "require 'vendor/autoload.php'; \$pdo=new PDO('mysql:host=db;dbname=app;charset=utf8mb4','app','app'); \$hash=password_hash('admin123', PASSWORD_BCRYPT); \$pdo->exec(\"INSERT INTO users (id,email,password_hash,role,created_at) VALUES (UUID(),'admin@example.com','\$hash','admin',NOW())\");"

Danach ist die Anwendung erreichbar:

- **App:** http://localhost:8080  
- **phpMyAdmin:** http://localhost:8081  
- **Mailpit:** http://localhost:8025  

## QuickStart für Anwender

1. **Login**  
   Gehe auf `http://localhost:8080/login` und melde dich mit E-Mail/Passwort an.

2. **Neues Protokoll anlegen**  
   Klicke auf **„Neues Protokoll“** und fülle den Assistenten aus:  
   - **Schritt 1:** Adresse, WE, Eigentümer, Hausverwaltung, Art (Einzug/Auszug/Zwischen)  
   - **Schritt 2:** Räume (IST-Zustand, Geruch, WMZ), Fotos  
   - **Schritt 3:** Zählerstände (WE + Allgemein)  
   - **Schritt 4:** Schlüssel, Bank/Kaution, Kontakt, Einwilligungen (DSGVO)

3. **Bearbeiten & Versionieren**  
   Nach Abschluss wirst du direkt zu **/protocols/edit?id=…** geleitet.  
   Änderungen erzeugen neue Versionen; Fotos werden pro Raum als Thumbnails gezeigt.

4. **PDF & Versand**  
   Am Ende der Detailseite: **PDF ansehen** oder **per E-Mail** an Eigentümer / HV / Mieter senden.  
   Versand wird im Log protokolliert; signierte DocuSign-PDFs werden bevorzugt verwendet.

5. **Statistiken**  
   Unter **„Statistik“** (`/stats`) stehen Auswertungen bereit:  
   - Fluktuation Haus & Wohnung (sortiert, mit Quote)  
   - Ø Mietdauer & Ø Leerstand je Haus + globale Leerstandsquote  
   - Einzüge vs. Auszüge nach Monat (Saisonalität)  
   - Zähler-Δ (Mittelwerte) für Strom/Gas/Wasser  

## Git-Workflow

- Änderungen mit sinnvollen Commits dokumentieren  
- Vor dem Push: `git fetch && git rebase origin/main`  
- Bei Konflikten: auflösen → `git rebase --continue`  
- **Notes.md** im Repo für technische Entscheidungen/ToDos nutzen

## Lizenz

Proprietär, © Handwertig GmbH
