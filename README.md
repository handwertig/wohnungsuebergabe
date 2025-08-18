# Wohnungsübergabe – Start-Setup (PHP + Docker + MySQL)

Webbasierte Übergabedatenbank (Einzug/Auszug/Zwischenprotokoll) gemäß Pflichtenheft.

## Stack
- PHP 8.3 (FPM) + Nginx
- MySQL 8.0 + phpMyAdmin
- Mailhog (Entwicklungs-Mailserver)
- wkhtmltopdf (PDF-Erzeugung, später)
- Docker Compose
- GitHub Actions (CI)

## Schnellstart (Dev)
```bash
cp backend/.env.example backend/.env
docker compose up --build -d
# phpMyAdmin: http://localhost:8081 (user: root, pass: root)
# App:        http://localhost:8080
# Mailhog UI: http://localhost:8025
```
Login-Seed (nach erstem Start wird automatisch ein Admin angelegt):
- E-Mail: admin@example.com
- Passwort: admin123 (bitte ändern)

## Ordnerstruktur
```
backend/           PHP-App
  public/          Webroot (index.php)
  src/             App-Code (Controller, DB, Routing)
  config/          Settings, Bootstrap
docker/
  nginx/           Nginx vhost
  php/             PHP Dockerfile
docs/              Pflichtenheft, Architektur-Notizen
.github/workflows  CI-Workflows
migrations/        SQL-Migrationen
seed/              Beispielvorgänge (Platzhalter)
```

## Nützliche Befehle
```bash
# Logs
docker compose logs -f app

# Composer inside container
docker compose exec app composer install

# DB Shell
docker compose exec db mysql -uroot -proot app
```
>>>>>>> 26fe6ef (Initial commit: Docker + PHP + MariaDB + Mailpit + CI setup)
