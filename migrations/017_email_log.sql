CREATE TABLE IF NOT EXISTS email_log (
  id CHAR(36) PRIMARY KEY,
  protocol_id CHAR(36) NOT NULL,
  recipient_type ENUM('owner','manager','tenant','custom') NOT NULL,
  to_email VARCHAR(255) NOT NULL,
  subject VARCHAR(255) NOT NULL,
  status ENUM('queued','sent','failed') NOT NULL DEFAULT 'queued',
  error_msg VARCHAR(500) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  sent_at DATETIME NULL,
  CONSTRAINT fk_email_protocol FOREIGN KEY (protocol_id) REFERENCES protocols(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE INDEX IF NOT EXISTS idx_email_protocol ON email_log (protocol_id, created_at);
