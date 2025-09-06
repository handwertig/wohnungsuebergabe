-- migrations/027_fix_collation_problem.sql
-- Behebt das Kollationsproblem zwischen Tabellen

-- Zuerst die problematische View löschen
DROP VIEW IF EXISTS `protocol_versions_with_pdfs`;

-- Prüfen der aktuellen Kollationen
SELECT table_name, table_collation 
FROM information_schema.tables 
WHERE table_schema = DATABASE() 
AND table_name IN ('protocols', 'protocol_versions', 'protocol_pdfs');

-- Alle Tabellen auf einheitliche Kollation setzen
ALTER TABLE `protocols` CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
ALTER TABLE `protocol_versions` CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
ALTER TABLE `protocol_pdfs` CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- Spezifische Spalten anpassen, die Probleme verursachen könnten
ALTER TABLE `protocols` 
    MODIFY COLUMN `id` VARCHAR(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL;

ALTER TABLE `protocol_versions` 
    MODIFY COLUMN `protocol_id` VARCHAR(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL;

ALTER TABLE `protocol_pdfs` 
    MODIFY COLUMN `protocol_id` VARCHAR(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL;

-- View ohne Kollationsprobleme neu erstellen
CREATE OR REPLACE VIEW `protocol_versions_with_pdfs` AS
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
    CASE 
        WHEN pp.id IS NOT NULL THEN 1 
        ELSE 0 
    END as has_pdf,
    CONCAT(
        '/protocols/pdf?id=', 
        pv.protocol_id, 
        '&version=', 
        pv.version_number
    ) as pdf_url
FROM protocol_versions pv
LEFT JOIN protocol_pdfs pp ON (
    pv.protocol_id COLLATE utf8mb4_unicode_ci = pp.protocol_id COLLATE utf8mb4_unicode_ci
    AND pv.version_number = pp.version_number
)
ORDER BY pv.protocol_id, pv.version_number DESC;

COMMIT;
