CREATE TABLE IF NOT EXISTS protocol_drafts (
  id CHAR(36) PRIMARY KEY,
  unit_id CHAR(36) NULL,
  type ENUM('einzug','auszug','zwischen') NULL,
  tenant_name VARCHAR(150) NULL,
  step INT NOT NULL DEFAULT 1,
  data JSON NOT NULL,
  status ENUM('draft','finished','aborted') NOT NULL DEFAULT 'draft',
  created_by VARCHAR(150) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS protocol_files (
  id CHAR(36) PRIMARY KEY,
  draft_id CHAR(36) NULL,
  protocol_id CHAR(36) NULL,
  section ENUM('room_photo','attachment') NOT NULL,
  room_key VARCHAR(120) NULL,
  original_name VARCHAR(255) NOT NULL,
  path VARCHAR(500) NOT NULL,
  mime VARCHAR(120) NOT NULL,
  size INT NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX (draft_id),
  INDEX (protocol_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
