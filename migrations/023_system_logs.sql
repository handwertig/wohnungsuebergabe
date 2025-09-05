-- System-weites Logging für alle Benutzeraktionen
CREATE TABLE IF NOT EXISTS system_logs (
  id CHAR(36) PRIMARY KEY,
  timestamp DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  user_email VARCHAR(255) NOT NULL,
  action VARCHAR(255) NOT NULL,
  entity_type VARCHAR(100) NULL COMMENT 'z.B. protocol, user, settings, owner, manager',
  entity_id CHAR(36) NULL COMMENT 'ID des betroffenen Objekts',
  details TEXT NULL COMMENT 'Zusätzliche Details zur Aktion',
  ip_address VARCHAR(45) NULL,
  user_agent TEXT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_timestamp (timestamp),
  INDEX idx_user (user_email),
  INDEX idx_action (action),
  INDEX idx_entity (entity_type, entity_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='System-weites Logging aller Benutzeraktionen';

-- Beispiel-Einträge für Tests
INSERT INTO system_logs (id, user_email, action, entity_type, entity_id, details, ip_address) VALUES
(UUID(), 'admin@example.com', 'system_setup', 'system', NULL, 'System-Log Tabelle erstellt', '127.0.0.1'),
(UUID(), 'admin@example.com', 'migration_executed', 'database', NULL, 'Migration 023_system_logs.sql ausgeführt', '127.0.0.1');
