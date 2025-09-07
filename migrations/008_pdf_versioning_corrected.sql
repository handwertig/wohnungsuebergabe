-- migrations/008_pdf_versioning_corrected.sql
-- Korrigiert die PDF-Versionierung für Wohnungsübergabeprotokolle
-- Erweitert die bestehende protocol_versions Tabelle um PDF-Funktionen

-- Erweitere die bestehende protocol_versions Tabelle um PDF-Felder
ALTER TABLE `protocol_versions` 
  ADD COLUMN IF NOT EXISTS `pdf_path` VARCHAR(500) NULL DEFAULT NULL COMMENT 'Pfad zur generierten PDF-Datei',
  ADD COLUMN IF NOT EXISTS `signed_pdf_path` VARCHAR(500) NULL DEFAULT NULL COMMENT 'Pfad zur signierten PDF-Datei',
  ADD COLUMN IF NOT EXISTS `signed_at` DATETIME NULL DEFAULT NULL COMMENT 'Zeitpunkt der Signierung';

-- Index für bessere Performance bei PDF-Abfragen
CREATE INDEX IF NOT EXISTS `idx_protocol_versions_pdf` ON `protocol_versions`(`protocol_id`, `version_no`);
CREATE INDEX IF NOT EXISTS `idx_protocol_versions_signed` ON `protocol_versions`(`signed_at`);

-- Tabelle für separate PDF-Verwaltung (falls benötigt)
CREATE TABLE IF NOT EXISTS `protocol_pdfs` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `protocol_id` CHAR(36) NOT NULL,
    `version_no` INT NOT NULL,
    `file_name` VARCHAR(255) NOT NULL,
    `file_path` VARCHAR(500) DEFAULT NULL,
    `file_size` BIGINT DEFAULT NULL,
    `mime_type` VARCHAR(100) DEFAULT 'application/pdf',
    `generated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `expires_at` TIMESTAMP NULL DEFAULT NULL,
    
    INDEX `idx_protocol_pdfs_protocol_id` (`protocol_id`),
    INDEX `idx_protocol_pdfs_version` (`protocol_id`, `version_no`),
    UNIQUE KEY `unique_protocol_pdf_version` (`protocol_id`, `version_no`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Event-Log Tabelle für Protokoll-Ereignisse
CREATE TABLE IF NOT EXISTS `protocol_events` (
    `id` CHAR(36) PRIMARY KEY,
    `protocol_id` CHAR(36) NOT NULL,
    `type` VARCHAR(50) NOT NULL COMMENT 'Event-Typ: created, updated, signed, sent, etc.',
    `message` TEXT NULL COMMENT 'Beschreibung des Ereignisses',
    `metadata` JSON NULL COMMENT 'Zusätzliche Event-Daten',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    
    INDEX `idx_protocol_events_protocol_id` (`protocol_id`),
    INDEX `idx_protocol_events_type` (`type`),
    INDEX `idx_protocol_events_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Mail-Log Tabelle für E-Mail-Versand
CREATE TABLE IF NOT EXISTS `protocol_mails` (
    `id` CHAR(36) PRIMARY KEY,
    `protocol_id` CHAR(36) NOT NULL,
    `version_no` INT NOT NULL,
    `recipient_type` ENUM('owner', 'management', 'tenant', 'other') NOT NULL,
    `to_email` VARCHAR(255) NOT NULL,
    `subject` VARCHAR(255) NOT NULL,
    `status` ENUM('pending', 'sent', 'failed', 'bounced') NOT NULL DEFAULT 'pending',
    `error_message` TEXT NULL,
    `sent_at` DATETIME NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    
    INDEX `idx_protocol_mails_protocol_id` (`protocol_id`),
    INDEX `idx_protocol_mails_status` (`status`),
    INDEX `idx_protocol_mails_sent_at` (`sent_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Ansicht für einfacheren Zugriff auf Protokoll-Versionen mit PDFs
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
LEFT JOIN protocol_pdfs pp ON pv.protocol_id = pp.protocol_id 
                            AND pv.version_no = pp.version_no
ORDER BY pv.protocol_id, pv.version_no DESC;

COMMIT;
