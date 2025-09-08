# 🔧 DATENBANKFEHLER BEHOBEN

## Problem gelöst:
**Fatal error: Unknown column 'signer_name' in 'SELECT'**

## Was war das Problem?
Ich hatte falsche Annahmen über die Datenbankstruktur gemacht und SQL-Abfragen mit nicht existierenden Spalten erstellt.

## Korrekturen durchgeführt:

### 1. ✅ Signaturen Tab repariert
**Problem:** Spalte `signer_name` existiert nicht in `protocol_signatures`  
**Lösung:** Korrekter JOIN zu `protocol_signers` Tabelle
```sql
-- VORHER (falsch):
SELECT signer_name, signer_role, signer_type FROM protocol_signatures

-- NACHHER (korrekt):
SELECT ps.*, psi.name as signer_name, psi.role as signer_role 
FROM protocol_signatures ps
JOIN protocol_signers psi ON ps.signer_id = psi.id
```

### 2. ✅ PDF-Versionen Tab repariert  
**Problem:** Spalte `version_no` heißt `version_number`, `file_size` existiert nicht
**Lösung:** Korrekte Spaltennamen und dynamische Größenberechnung
```sql
-- VORHER (falsch):
SELECT version_no, file_size FROM protocol_versions

-- NACHHER (korrekt):  
SELECT version_number, pdf_path, signed_pdf_path FROM protocol_versions
-- file_size wird dynamisch mit filesize() berechnet
```

### 3. ✅ Protokoll-Log Tab repariert
**Problem:** Spalten `user_id`, `details` existieren nicht  
**Lösung:** Korrekte Spalten `message`, `meta` und richtige Event-Typen
```sql
-- VORHER (falsch):
SELECT user_id, details FROM protocol_events

-- NACHHER (korrekt):
SELECT message, meta FROM protocol_events
```

### 4. ✅ Event-Typen korrigiert
**Problem:** Falsche Event-Namen verwendet  
**Lösung:** Korrekte ENUMs aus Datenbank-Schema:
- `signed_by_tenant` statt `signature_added`
- `signed_by_owner` statt `created`  
- `sent_owner`, `sent_manager`, `sent_tenant` beibehalten
- `other` für sonstige Ereignisse

## 🧪 Jetzt testen:

1. **Container starten:**
```bash
cd /Users/berndgundlach/Documents/Docker/wohnungsuebergabe
docker-compose up -d
```

2. **Protokoll öffnen:**
http://localhost:8080/protocols/edit?id=82cc7de7-7d1e-11f0-89a6-822b82242c5d

3. **Alle Tabs prüfen:**
- ✅ Unterschriften Tab → Sollte korrekte Daten oder "Noch keine Unterschriften" zeigen
- ✅ PDF-Versionen Tab → Sollte "Noch keine PDF-Versionen vorhanden" oder vorhandene PDFs zeigen  
- ✅ Protokoll Tab → Sollte Events und E-Mail-Logs anzeigen

## 🎯 Erwartetes Ergebnis:
**Keine PHP-Fehler mehr!** Alle Tabs sollten sich problemlos öffnen lassen und funktionale Inhalte anzeigen.

---
*Datenbankfehler behoben am: 8. September 2025*  
*Alle SQL-Abfragen sind jetzt kompatibel mit der tatsächlichen Datenbankstruktur.*
