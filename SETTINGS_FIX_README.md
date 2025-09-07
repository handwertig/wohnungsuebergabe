# Settings-Reparatur Skripte

Diese Skripte beheben Probleme mit der Settings-Speicherung in der Wohnungsübergabe-Anwendung.

## Problem
Settings wurden nicht korrekt in der Datenbank gespeichert und System-Logs wurden nicht geschrieben.

## Lösung
Die Skripte reparieren die Datenbank-Struktur und stellen sicher, dass alle Settings korrekt gespeichert werden.

## Verfügbare Skripte

### 1. `test_settings_quick.sh`
**Schnelltest** - Prüft ob Settings funktionieren
```bash
./test_settings_quick.sh
```

### 2. `verify_docker_settings.sh`
**Vollständige Verifizierung** - Umfassender Test aller Komponenten
```bash
./verify_docker_settings.sh
```

### 3. `docker_fix_settings.sh`
**Komplett-Reparatur** - Behebt alle bekannten Probleme
```bash
./docker_fix_settings.sh
```

## Verwendung

1. **Bei Problemen mit Settings:**
   ```bash
   ./docker_fix_settings.sh
   ```

2. **Zum Testen nach der Reparatur:**
   ```bash
   ./test_settings_quick.sh
   ```

3. **Für detaillierte Diagnose:**
   ```bash
   ./verify_docker_settings.sh
   ```

## Web-Interface Test

1. Öffnen Sie: http://localhost:8080/settings/mail
2. Ändern Sie einen Wert (z.B. SMTP-Host)
3. Klicken Sie auf "Speichern"
4. Laden Sie die Seite neu
5. Der Wert sollte gespeichert sein

## Voraussetzungen

- Docker Desktop muss laufen
- Container müssen gestartet sein:
  ```bash
  docker compose up -d
  ```

## Behobene Probleme

✅ Settings-Tabelle mit korrekter Struktur
✅ System_log-Tabelle funktioniert
✅ Settings-Klasse mit Fehlerbehandlung
✅ SystemLogger protokolliert alle Änderungen
✅ Docker-Befehle für Mac angepasst
✅ Alle Test-Dateien bereinigt

## Support

Bei weiteren Problemen prüfen Sie:
- Docker Desktop läuft
- Container sind gestartet
- Port 8080 ist nicht blockiert
