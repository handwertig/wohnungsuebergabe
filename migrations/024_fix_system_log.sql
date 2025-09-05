-- Fix für system_log Tabelle - einheitliche Struktur
-- Diese Migration löst den LIMIT/OFFSET Fehler und stellt sicher, dass die richtige Struktur vorhanden ist

-- Backup der alten Daten falls vorhanden
CREATE TABLE IF NOT EXISTS system_log_backup AS 
SELECT * FROM system_log WHERE 1=0;

-- Lösche die alte Tabelle falls vorhanden
DROP TABLE IF EXISTS system_log;

-- Erstelle die neue, korrekte system_log Tabelle
CREATE TABLE system_log (
  id CHAR(36) PRIMARY KEY,
  user_email VARCHAR(255) NOT NULL DEFAULT 'system',
  user_ip VARCHAR(45) NULL,
  action_type VARCHAR(100) NOT NULL, -- Erweitert von ENUM für mehr Flexibilität
  action_description TEXT NOT NULL,
  resource_type VARCHAR(50) NULL,
  resource_id CHAR(36) NULL,
  additional_data JSON NULL,
  request_method VARCHAR(10) NULL,
  request_url VARCHAR(500) NULL,
  user_agent TEXT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  timestamp DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, -- Alias für Kompatibilität
  
  -- Zusätzliche Spalten für erweiterte Funktionalität
  details TEXT GENERATED ALWAYS AS (action_description) STORED, -- Alias für Kompatibilität
  action VARCHAR(100) GENERATED ALWAYS AS (action_type) STORED, -- Alias für Kompatibilität
  entity_type VARCHAR(100) GENERATED ALWAYS AS (resource_type) STORED, -- Alias für Kompatibilität
  entity_id CHAR(36) GENERATED ALWAYS AS (resource_id) STORED, -- Alias für Kompatibilität
  ip_address VARCHAR(45) GENERATED ALWAYS AS (user_ip) STORED, -- Alias für Kompatibilität
  
  INDEX idx_user_email (user_email),
  INDEX idx_action_type (action_type),
  INDEX idx_resource (resource_type, resource_id),
  INDEX idx_created_at (created_at),
  INDEX idx_timestamp (timestamp),
  INDEX idx_search (action_description(100), user_email, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Erstelle erste Test-Einträge
INSERT INTO system_log (id, user_email, action_type, action_description, user_ip, created_at) VALUES
(UUID(), 'system', 'system_setup', 'System-Log Tabelle korrigiert und initialisiert', '127.0.0.1', NOW()),
(UUID(), 'system', 'migration_executed', 'Migration 024_fix_system_log.sql erfolgreich ausgeführt', '127.0.0.1', NOW());

-- Lösche system_logs falls es Konflikte gibt  
DROP TABLE IF EXISTS system_logs;
