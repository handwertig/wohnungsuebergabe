-- migrations/028_final_collation_fix.sql
-- Finale Lösung für alle Kollationsprobleme

-- 1. Backup der aktuellen View-Definition
CREATE TABLE IF NOT EXISTS migration_backup_028 (
    id INT AUTO_INCREMENT PRIMARY KEY,
    object_type VARCHAR(50),
    object_name VARCHAR(100),
    definition LONGTEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Views löschen die Probleme verursachen könnten
DROP VIEW IF EXISTS `protocol_versions_with_pdfs`;

-- 2. Datenbank-weite Kollation korrigieren
ALTER DATABASE `app` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- 3. Alle relevanten Tabellen einheitlich konvertieren
-- protocols Tabelle
ALTER TABLE `protocols` CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- protocol_versions Tabelle (falls sie existiert)
-- Prüfen ob die Tabelle existiert und korrigieren
SET @table_exists = (SELECT COUNT(*) FROM information_schema.tables 
                    WHERE table_schema = DATABASE() AND table_name = 'protocol_versions');

SET @sql = IF(@table_exists > 0, 
    'ALTER TABLE `protocol_versions` CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci',
    'SELECT "Table protocol_versions does not exist - skipping" as status'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- protocol_pdfs Tabelle (falls sie existiert)
SET @table_exists = (SELECT COUNT(*) FROM information_schema.tables 
                    WHERE table_schema = DATABASE() AND table_name = 'protocol_pdfs');

SET @sql = IF(@table_exists > 0, 
    'ALTER TABLE `protocol_pdfs` CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci',
    'SELECT "Table protocol_pdfs does not exist - skipping" as status'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- 4. Wichtige ID-Spalten explizit korrigieren
ALTER TABLE `protocols` 
    MODIFY COLUMN `id` VARCHAR(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL;

-- 5. Falls protocol_versions existiert, ID-Spalten korrigieren
SET @table_exists = (SELECT COUNT(*) FROM information_schema.tables 
                    WHERE table_schema = DATABASE() AND table_name = 'protocol_versions');

SET @sql = IF(@table_exists > 0, 
    'ALTER TABLE `protocol_versions` 
     MODIFY COLUMN `protocol_id` VARCHAR(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL',
    'SELECT "Skipping protocol_versions column modification" as status'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- 6. Falls protocol_pdfs existiert, ID-Spalten korrigieren
SET @table_exists = (SELECT COUNT(*) FROM information_schema.tables 
                    WHERE table_schema = DATABASE() AND table_name = 'protocol_pdfs');

SET @sql = IF(@table_exists > 0, 
    'ALTER TABLE `protocol_pdfs` 
     MODIFY COLUMN `protocol_id` VARCHAR(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL',
    'SELECT "Skipping protocol_pdfs column modification" as status'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- 7. Alle anderen wichtigen Tabellen auch korrigieren
ALTER TABLE `users` CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
ALTER TABLE `objects` CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
ALTER TABLE `units` CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- 8. Foreign Key Spalten in Protokollen
ALTER TABLE `protocols` 
    MODIFY COLUMN `unit_id` VARCHAR(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
    MODIFY COLUMN `created_by` VARCHAR(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- 9. View nur erstellen, wenn beide Tabellen existieren
SET @both_tables_exist = (
    SELECT COUNT(*) FROM information_schema.tables 
    WHERE table_schema = DATABASE() 
    AND table_name IN ('protocol_versions', 'protocol_pdfs')
) = 2;

SET @sql = IF(@both_tables_exist, 
    'CREATE OR REPLACE VIEW `protocol_versions_with_pdfs` AS
     SELECT 
         pv.protocol_id,
         pv.version_number,
         pv.version_data,
         pv.notes,
         pv.created_by,
         pv.created_at,
         pp.file_name as pdf_file_name,
         pp.file_size as pdf_file_size,
         pp.generated_at as pdf_generated_at,
         CASE WHEN pp.id IS NOT NULL THEN 1 ELSE 0 END as has_pdf,
         CONCAT(\'/protocols/pdf?id=\', pv.protocol_id, \'&version=\', pv.version_number) as pdf_url
     FROM protocol_versions pv
     LEFT JOIN protocol_pdfs pp ON (
         pv.protocol_id = pp.protocol_id
         AND pv.version_number = pp.version_number
     )
     ORDER BY pv.protocol_id, pv.version_number DESC',
    'SELECT "Not creating view - tables missing" as status'
);

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- 10. Prüfe die Reparatur
SELECT 
    'Kollations-Status nach Reparatur:' as info,
    table_name, 
    table_collation
FROM information_schema.tables 
WHERE table_schema = DATABASE() 
AND table_name IN ('protocols', 'protocol_versions', 'protocol_pdfs', 'users', 'objects', 'units')
ORDER BY table_name;

COMMIT;
