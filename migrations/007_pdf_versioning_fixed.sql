-- migrations/007_pdf_versioning_fixed.sql
-- Erweitert die Datenbank um PDF-Versionierung für Wohnungsübergabeprotokolle
-- Ohne Foreign Key Constraints um Kompatibilitätsprobleme zu vermeiden

-- Tabelle für Protokoll-Versionen (ohne Foreign Keys)
CREATE TABLE IF NOT EXISTS `protocol_versions` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `protocol_id` VARCHAR(36) NOT NULL,
    `version_number` INT NOT NULL,
    `version_data` LONGTEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
    `notes` TEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
    `created_by` VARCHAR(36),
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    INDEX `idx_protocol_versions_protocol_id` (`protocol_id`),
    INDEX `idx_protocol_versions_version` (`protocol_id`, `version_number`),
    UNIQUE KEY `unique_protocol_version` (`protocol_id`, `version_number`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabelle für generierte PDFs (ohne Foreign Keys)
CREATE TABLE IF NOT EXISTS `protocol_pdfs` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `protocol_id` VARCHAR(36) NOT NULL,
    `version_number` INT NOT NULL,
    `file_name` VARCHAR(255) NOT NULL,
    `file_path` VARCHAR(500) DEFAULT NULL,
    `file_size` BIGINT DEFAULT NULL,
    `mime_type` VARCHAR(100) DEFAULT 'application/pdf',
    `generated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `expires_at` TIMESTAMP NULL DEFAULT NULL,
    
    INDEX `idx_protocol_pdfs_protocol_id` (`protocol_id`),
    INDEX `idx_protocol_pdfs_version` (`protocol_id`, `version_number`),
    UNIQUE KEY `unique_protocol_pdf_version` (`protocol_id`, `version_number`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Index für bessere Performance bei PDF-Abfragen
CREATE INDEX IF NOT EXISTS `idx_protocol_pdfs_generated_at` ON `protocol_pdfs`(`generated_at`);
CREATE INDEX IF NOT EXISTS `idx_protocol_versions_created_at` ON `protocol_versions`(`created_at`);

-- Prüfe ob protocols Tabelle existiert und hat Daten
SET @protocols_exist = (SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = 'protocols');

-- Nur wenn protocols Tabelle existiert, füge Beispiel-Daten hinzu
SET @sql = IF(@protocols_exist > 0, 
    'INSERT IGNORE INTO protocol_versions (protocol_id, version_number, version_data, notes, created_at)
     SELECT id as protocol_id, 1 as version_number, payload as version_data, "Initiale Version (Migration)" as notes, created_at
     FROM protocols WHERE deleted_at IS NULL',
    'SELECT "Protocols table not found - skipping data migration" as status'
);

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Ansicht für einfacheren Zugriff auf Protokoll-Versionen mit PDFs
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
LEFT JOIN protocol_pdfs pp ON pv.protocol_id = pp.protocol_id 
                            AND pv.version_number = pp.version_number
ORDER BY pv.protocol_id, pv.version_number DESC;

COMMIT;
