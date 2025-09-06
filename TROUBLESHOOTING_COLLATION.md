# Fehlerbehebung - Kollationsproblem

## Problem
```
ERROR 1267 (HY000) at line 65: Illegal mix of collations (utf8mb4_uca1400_ai_ci,IMPLICIT) and (utf8mb4_unicode_ci,IMPLICIT) for operation '='
```

## Schnelle Lösung

### 1. Automatische Reparatur (Empfohlen)
```bash
# Im Projektverzeichnis ausführen
./ultimate_fix.sh
```

### 2. Manuelle Reparatur
```bash
# Kollations-Analyse
./debug_collation.sh

# Spezifische Kollations-Reparatur
./fix_collation_problem.sh

# Container-Neustart
docker compose restart web
```

### 3. Manuelle Datenbank-Kommandos
```sql
-- Datenbank-Kollation setzen
ALTER DATABASE app CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- Haupttabellen korrigieren
ALTER TABLE protocols CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
ALTER TABLE protocol_versions CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
ALTER TABLE protocol_pdfs CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- Views neu erstellen
DROP VIEW IF EXISTS protocol_versions_with_pdfs;
-- (View-Definition siehe Migration 029)
```

## Migrationen ausführen
```bash
# Spezifische Migrationen
docker compose exec -T db mariadb -uroot -proot app < migrations/028_final_collation_fix.sql
docker compose exec -T db mariadb -uroot -proot app < migrations/029_fix_schema_mismatch.sql
```

## Prüfung nach Reparatur
```bash
# Kollationen prüfen
docker compose exec -T db mariadb -uroot -proot -e "
SELECT table_name, table_collation 
FROM information_schema.tables 
WHERE table_schema = 'app' 
ORDER BY table_name;" app

# Test-Abfrage
docker compose exec -T db mariadb -uroot -proot -e "
SELECT COUNT(*) FROM protocol_versions_with_pdfs;" app
```

## Ursachen
1. **Inkonsistente Migrationen** - Verschiedene Kollationen in unterschiedlichen Migrations-Dateien
2. **MariaDB-Update** - Neue Standard-Kollation `utf8mb4_uca1400_ai_ci` vs. alte `utf8mb4_unicode_ci`
3. **Schema-Inkonsistenz** - Verschiedene Spaltennamen (`version_no` vs `version_number`)

## Vorbeugung
- Immer `utf8mb4_unicode_ci` in neuen Migrationen verwenden
- Schema-Konsistenz prüfen vor neuen Migrationen
- Regelmäßige Kollations-Analyse mit `debug_collation.sh`
