-- System Log Tabelle für zentrale Aktivitätslogs
CREATE TABLE IF NOT EXISTS system_log (
  id CHAR(36) PRIMARY KEY,
  user_email VARCHAR(255) NULL,
  user_ip VARCHAR(45) NULL,
  action_type VARCHAR(100) NOT NULL,
  action_description TEXT NOT NULL,
  resource_type VARCHAR(50) NULL,
  resource_id VARCHAR(255) NULL,
  additional_data JSON NULL,
  request_method VARCHAR(10) NULL,
  request_url VARCHAR(500) NULL,
  user_agent TEXT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Indizes für bessere Performance
CREATE INDEX IF NOT EXISTS idx_system_log_user ON system_log (user_email, created_at);
CREATE INDEX IF NOT EXISTS idx_system_log_action ON system_log (action_type, created_at);
CREATE INDEX IF NOT EXISTS idx_system_log_resource ON system_log (resource_type, resource_id);
CREATE INDEX IF NOT EXISTS idx_system_log_created ON system_log (created_at);
