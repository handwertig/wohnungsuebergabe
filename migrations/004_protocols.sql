CREATE TABLE IF NOT EXISTS protocols (
  id CHAR(36) PRIMARY KEY,
  unit_id CHAR(36) NOT NULL,
  type ENUM('einzug','auszug','zwischen') NOT NULL,
  tenant_name VARCHAR(150) NOT NULL,
  payload JSON NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL,
  deleted_at DATETIME NULL,
  CONSTRAINT fk_protocols_unit FOREIGN KEY (unit_id) REFERENCES units(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS protocol_versions (
  id CHAR(36) PRIMARY KEY,
  protocol_id CHAR(36) NOT NULL,
  version_no INT NOT NULL,
  data JSON NOT NULL,
  created_by VARCHAR(150) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_protocol_versions_protocol FOREIGN KEY (protocol_id) REFERENCES protocols(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
