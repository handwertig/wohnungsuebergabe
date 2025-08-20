CREATE TABLE IF NOT EXISTS protocol_events (
  id CHAR(36) PRIMARY KEY,
  protocol_id CHAR(36) NOT NULL,
  type ENUM('signed_by_tenant','signed_by_owner','sent_owner','sent_manager','sent_tenant','other') NOT NULL,
  message VARCHAR(255) NULL,
  meta JSON NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_events_protocol FOREIGN KEY (protocol_id) REFERENCES protocols(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE INDEX IF NOT EXISTS idx_events_protocol ON protocol_events (protocol_id, created_at);
