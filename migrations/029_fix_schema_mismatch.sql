-- migrations/029_fix_schema_mismatch.sql
-- Behebt Schema-Inkonsistenzen zwischen verschiedenen Migrationen

-- Prüfe welche Spalten existieren
SELECT COLUMN_NAME, DATA_TYPE, COLLATION_NAME 
FROM INFORMATION_SCHEMA.COLUMNS 
WHERE TABLE_SCHEMA = DATABASE() 
AND TABLE_NAME = 'protocol_versions'
ORDER BY ORDINAL_POSITION;

-- Standardisiere auf version_no (da es bereits existiert)
-- Falls version_number existiert, umbenennen zu version_no
SET @column_exists = (
    SELECT COUNT(*) 
    FROM INFORMATION_SCHEMA.COLUMNS 
    WHERE TABLE_SCHEMA = DATABASE() 
    AND TABLE_NAME = 'protocol_versions' 
    AND COLUMN_NAME = 'version_number'
);

SET @sql = IF(@column_exists > 0,
    'ALTER TABLE protocol_versions CHANGE COLUMN version_number version_no INT NOT NULL',
    'SELECT "Column version_number does not exist" as status'
);

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Stelle sicher, dass version_no existiert
SET @column_exists = (
    SELECT COUNT(*) 
    FROM INFORMATION_SCHEMA.COLUMNS 
    WHERE TABLE_SCHEMA = DATABASE() 
    AND TABLE_NAME = 'protocol_versions' 
    AND COLUMN_NAME = 'version_no'
);

SET @sql = IF(@column_exists = 0,
    'ALTER TABLE protocol_versions ADD COLUMN version_no INT NOT NULL DEFAULT 1',
    'SELECT "Column version_no already exists" as status'
);

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Korrigiere die View mit den richtigen Spaltennamen
DROP VIEW IF EXISTS `protocol_versions_with_pdfs`;

CREATE OR REPLACE VIEW `protocol_versions_with_pdfs` AS
SELECT 
    pv.protocol_id,
    pv.version_no,
    pv.data as version_data,
    pv.created_by,
    pv.created_at,
    pv.pdf_path,
    pv.signed_pdf_path,
    pv.signed_at,
    pp.file_name as pdf_file_name,
    pp.file_size as pdf_file_size,
    pp.generated_at as pdf_generated_at,
    CASE 
        WHEN pv.pdf_path IS NOT NULL OR pp.id IS NOT NULL THEN 1 
        ELSE 0 
    END as has_pdf,
    CASE 
        WHEN pv.signed_pdf_path IS NOT NULL THEN 1 
        ELSE 0 
    END as is_signed,
    CONCAT(
        '/protocols/pdf?protocol_id=', 
        pv.protocol_id, 
        '&version=', 
        pv.version_no
    ) as pdf_url
FROM protocol_versions pv
LEFT JOIN protocol_pdfs pp ON (
    pv.protocol_id = pp.protocol_id 
    AND pv.version_no = pp.version_no
)
ORDER BY pv.protocol_id, pv.version_no DESC;

-- Teste die View
SELECT 'View-Test', COUNT(*) as anzahl_versionen FROM protocol_versions_with_pdfs LIMIT 1;

COMMIT;
