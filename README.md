# Wohnungsübergabe – Digitale Protokollverwaltung

## Beschreibung
Die **Wohnungsübergabe-App** ist eine webbasierte Lösung zur strukturierten Erfassung, Verwaltung und Archivierung von Wohnungsübergabeprotokollen.  
Entwickelt für Projektentwickler, Hausverwaltungen und Eigentümer, ersetzt sie papierbasierte Prozesse durch ein sicheres, versioniertes, PDF- und mailfähiges System.

---

## Features
- Such- & Filterfunktion in der Protokoll-Übersicht (Volltext, Art, Zeitraum)
- Gruppierte Darstellung: Haus → Einheit → Protokolle (Akkordeon)
- DocuSign-Platzhalter im Wizard (Schritt 4) + Dritte Person
- **Protokoll-Wizard** für Einzugs-, Auszugs- und Zwischenprotokolle (Schritt-für-Schritt)
- **Objekt-/Wohnungsverwaltung**: Adresse, Wohneinheit, Versionierung
- **Mieterangaben** inkl. Bankverbindung, neue Meldeadresse, Kontaktdaten
- **Raumbezogene Dokumentation**: Zustand, Geruch, Fotos, Wärmemengen-/Zählerstände
- **Schlüsselverwaltung** (Wohnung, Haus, Keller, Briefkasten, Garage, Sonstige)
- **Rechtstexte**: versioniert, aus Backend pflegbar (Datenschutz, Einverständnisse)
- **Signaturen via DocuSign** (Mieter, Eigentümer, optional dritte Person)
- **PDF-Export** inkl. Logo, Design und Rechtstexten
- **Versand per E-Mail** an Eigentümer, Verwaltung, Mieter (mit Versandlog)
- **Statistiken**: Fluktuation, Mietdauer, Leerstand, Vollständigkeit, saisonale Spitzen
- **Audit-Log** & Soft-Delete für Nachvollziehbarkeit
- **Benutzerverwaltung** (Staff anlegen, Rollen, Passwort-Reset via Mailpit)
- **Branding / Personalisierung**:
  - Logo hochladen (Backend & PDF)
  - Eigenes CSS hinterlegbar
- **Navigation (aufgeräumt)**:
  - Protokolle
  - Neues Protokoll
  - Statistiken
  - Einstellungen (Tabs)

---

## Preview
![Alt-Text](https://github.com/handwertig/wohnungsuebergabe/blob/main/docs/images/01%20login.png)
![Alt-Text](https://github.com/handwertig/wohnungsuebergabe/blob/main/docs/images/02%20uebersicht.png)
![Alt-Text](https://github.com/handwertig/wohnungsuebergabe/blob/main/docs/images/03%20wizard.png)
![Alt-Text](https://github.com/handwertig/wohnungsuebergabe/blob/main/docs/images/04%20statistik.png)
![Alt-Text](https://github.com/handwertig/wohnungsuebergabe/blob/main/docs/images/05%20einstellungen.png)
![Alt-Text](https://github.com/handwertig/wohnungsuebergabe/blob/main/docs/images/06%20texte.png)

---

## Technologie-Stack
- **PHP 8.3 (FPM)**  
- **MySQL/MariaDB**  
- **Bootstrap 5 / Poppins-Font**  
- **Docker Compose (nginx + php-fpm + mariadb + phpmyadmin + mailpit)**  

---

## QuickStart

## Releases & Downloads
Offizielle Releases (Tags, ZIP-Downloads) findest du unter: GitHub → **Releases**.


\`\`\`bash
# Repo klonen
git clone https://github.com/handwertig/wohnungsuebergabe.git
cd wohnungsuebergabe

# Container starten
docker compose up -d --build

# Migrationen einspielen
cat migrations/*.sql | docker compose exec -T db mariadb -uroot -proot app

# App erreichbar unter
http://localhost:8080
\`\`\`

Standard-Login:  
- User: \`admin@example.com\`  
- Passwort: \`admin123\`

---

## Entwicklung
- Code-Stil: PSR-12, PHP strict_types
- Git: alle Änderungen bitte mit Commit-Messages (\`feat:\`, \`fix:\`, \`chore:\`)
- Docker: Services sind unter \`docker-compose.yml\` definiert
- Notes/Changelog: siehe \`Notes.md\`, \`CHANGELOG.md\`

---

## Lizenz
Proprietär – Handwertig GmbH
