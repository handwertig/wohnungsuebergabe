-- System-Log Tabelle für umfassendes Audit-Logging
CREATE TABLE IF NOT EXISTS system_log (
  id CHAR(36) PRIMARY KEY,
  user_email VARCHAR(255) NOT NULL,
  user_ip VARCHAR(45) NULL,
  action_type ENUM(
    'login','logout','password_reset',
    'protocol_created','protocol_updated','protocol_deleted','protocol_viewed',
    'protocol_draft_started','protocol_draft_saved','protocol_draft_finished',
    'pdf_generated','pdf_viewed','pdf_downloaded',
    'email_sent','email_failed',
    'owner_created','owner_updated','owner_deleted','owner_viewed',
    'manager_created','manager_updated','manager_deleted','manager_viewed',
    'object_created','object_updated','object_deleted','object_viewed',
    'unit_created','unit_updated','unit_deleted','unit_viewed',
    'user_created','user_updated','user_deleted','user_viewed',
    'settings_updated','settings_viewed',
    'stats_viewed','export_generated',
    'docusign_sent','docusign_completed','docusign_failed',
    'file_uploaded','file_deleted',
    'other'
  ) NOT NULL,
  action_description TEXT NOT NULL,
  resource_type VARCHAR(50) NULL, -- 'protocol', 'owner', 'manager', etc.
  resource_id CHAR(36) NULL,      -- ID des betroffenen Objekts
  additional_data JSON NULL,      -- Zusätzliche Daten (z.B. alte/neue Werte)
  request_method VARCHAR(10) NULL, -- GET, POST, PUT, DELETE
  request_url VARCHAR(500) NULL,
  user_agent TEXT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  
  INDEX idx_user_email (user_email),
  INDEX idx_action_type (action_type),
  INDEX idx_resource (resource_type, resource_id),
  INDEX idx_created_at (created_at),
  INDEX idx_search (action_description, user_email, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
