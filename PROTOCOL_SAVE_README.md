# Protokoll-Speicherung Dokumentation

## Problem
Protokoll-Änderungen wurden nicht in der Datenbank gespeichert und nicht im System-Log protokolliert.

## Ursache
Die Route `/protocols/save` war fehlerhaft auf eine nicht-existierende Datei `working_save.php` umgeleitet, anstatt die korrekte Controller-Methode `ProtocolsController::save()` aufzurufen.

## Lösung

### 1. Routing korrigiert
Die Route in `public/index.php` zeigt jetzt korrekt auf:
```php
case '/protocols/save':
    Auth::requireAuth();
    (new ProtocolsController())->save();
    break;
```

### 2. ProtocolsController::save() implementiert
Die save() Methode enthält:
- Transaktionale Sicherheit
- Umfassendes Error-Handling
- Automatisches Event-Logging
- System-Logging Integration
- Flash-Messages für Benutzer-Feedback

### 3. Datenbank-Struktur optimiert
Folgende Tabellen wurden erstellt/optimiert:
- `protocols` - Haupttabelle mit optimierten Indizes
- `protocol_events` - Ereignis-Tracking
- `audit_log` - Änderungsverfolgung
- `email_log` - E-Mail-Versand-Protokolle
- `system_log` - Allgemeines System-Logging

## Verwendung

### Protokoll bearbeiten
1. Öffnen Sie die Protokoll-Übersicht: http://localhost:8080/protocols
2. Klicken Sie auf das Bearbeiten-Symbol bei einem Protokoll
3. Nehmen Sie Ihre Änderungen vor
4. Klicken Sie auf "Speichern"
5. Die Änderungen werden gespeichert und Sie sehen eine Erfolgsmeldung

### Logging prüfen
- System-Log: http://localhost:8080/settings/systemlogs
- Protokoll-Events: Im Tab "Protokoll" bei der Protokoll-Bearbeitung

## Diagnose-Tools

### debug_protocol_save.sh
Umfassende Diagnose der Protokoll-Speicherung:
```bash
./debug_protocol_save.sh
```

### fix_protocol_save.sh
Automatische Reparatur bei Problemen:
```bash
./fix_protocol_save.sh
```

### final_test_protocol.sh
Schneller Funktionstest:
```bash
./final_test_protocol.sh
```

## Technische Details

### Speicher-Prozess
1. Formular-Daten werden an `/protocols/save` gesendet
2. ProtocolsController::save() wird aufgerufen
3. Transaktion wird gestartet
4. Protokoll wird in DB aktualisiert
5. Events werden protokolliert
6. System-Log wird geschrieben
7. Transaktion wird committed
8. Flash-Message wird gesetzt
9. Redirect zur Bearbeitungsseite

### Fehlerbehandlung
- Bei Fehlern: Automatischer Rollback der Transaktion
- Error-Logging in system_log
- Benutzerfreundliche Fehlermeldung
- Kein Datenverlust

## Test-Protokoll
Für Tests steht ein Standard-Protokoll zur Verfügung:
- ID: `82cc7de7-7d1e-11f0-89a6-822b82242c5d`
- URL: http://localhost:8080/protocols/edit?id=82cc7de7-7d1e-11f0-89a6-822b82242c5d

## Fehlerbehebung

### Problem: Änderungen werden nicht gespeichert
1. Führen Sie `./fix_protocol_save.sh` aus
2. Prüfen Sie die Docker-Container: `docker ps`
3. Prüfen Sie die Logs: `docker logs wohnungsuebergabe-app-1`

### Problem: Keine Logs werden geschrieben
1. Prüfen Sie ob system_log Tabelle existiert
2. Führen Sie `./fix_protocol_save.sh` aus
3. Prüfen Sie Datenbankverbindung

### Problem: "Method not allowed" Fehler
1. Stellen Sie sicher, dass das Formular method="POST" verwendet
2. Prüfen Sie die Route in index.php
3. Cache leeren: `docker exec wohnungsuebergabe-app-1 rm -rf /var/www/html/storage/cache/*`

## Support
Bei weiteren Problemen:
1. Prüfen Sie die CHANGELOG.md für aktuelle Änderungen
2. Führen Sie die Diagnose-Tools aus
3. Prüfen Sie die System-Logs
