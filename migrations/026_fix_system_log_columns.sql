-- 026_fix_system_log_columns.sql - Behebt Spalten-Probleme in system_log

-- Zuerst prüfen wir die aktuelle Struktur und reparieren sie

-- Temporäre Tabelle für Backup
DROP TABLE IF EXISTS system_log_temp_backup;
CREATE TABLE system_log_temp_backup AS SELECT * FROM system_log WHERE 1=1;

-- Alte Tabelle löschen
DROP TABLE IF EXISTS system_log;

-- Neue, korrekte system_log Tabelle erstellen
CREATE TABLE system_log (
  id CHAR(36) PRIMARY KEY DEFAULT (UUID()),
  user_email VARCHAR(255) NOT NULL DEFAULT 'system',
  user_ip VARCHAR(45) NULL,
  action_type VARCHAR(100) NOT NULL,
  action_description TEXT NOT NULL,
  resource_type VARCHAR(50) NULL,
  resource_id CHAR(36) NULL,
  additional_data JSON NULL,
  request_method VARCHAR(10) NULL,
  request_url VARCHAR(500) NULL,
  user_agent TEXT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  
  INDEX idx_user_email (user_email),
  INDEX idx_action_type (action_type),
  INDEX idx_resource (resource_type, resource_id),
  INDEX idx_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Daten aus Backup zurückspielen (falls vorhanden)
INSERT IGNORE INTO system_log (id, user_email, user_ip, action_type, action_description, resource_type, resource_id, created_at)
SELECT 
    COALESCE(id, UUID()) as id,
    COALESCE(user_email, 'system') as user_email,
    user_ip,
    COALESCE(action_type, action, 'unknown') as action_type,
    COALESCE(action_description, details, 'No description') as action_description,
    COALESCE(resource_type, entity_type) as resource_type,
    COALESCE(resource_id, entity_id) as resource_id,
    COALESCE(created_at, timestamp, NOW()) as created_at
FROM system_log_temp_backup
WHERE 1=1;

-- Test-Einträge hinzufügen
INSERT INTO system_log (id, user_email, action_type, action_description, user_ip, created_at) VALUES
(UUID(), 'system', 'system_migration', 'Migration 026: System-Log Tabelle repariert', '127.0.0.1', NOW()),
(UUID(), 'admin@example.com', 'system_access', 'System-Log erfolgreich initialisiert', '127.0.0.1', NOW());

-- Backup-Tabelle entfernen
DROP TABLE IF EXISTS system_log_temp_backup;

-- Alte system_logs Tabelle entfernen falls vorhanden
DROP TABLE IF EXISTS system_logs;
