CREATE TABLE IF NOT EXISTS protocol_signers (
  id CHAR(36) PRIMARY KEY,
  protocol_id CHAR(36) NOT NULL,
  role ENUM('mieter','eigentuemer','anwesend') NOT NULL,
  name VARCHAR(150) NOT NULL,
  email VARCHAR(150) NULL,
  order_no INT NOT NULL DEFAULT 1,
  required TINYINT(1) NOT NULL DEFAULT 1,
  status ENUM('pending','signed','declined') NOT NULL DEFAULT 'pending',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL,
  CONSTRAINT fk_signers_protocol FOREIGN KEY (protocol_id) REFERENCES protocols(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS protocol_signatures (
  id CHAR(36) PRIMARY KEY,
  protocol_id CHAR(36) NOT NULL,
  signer_id CHAR(36) NOT NULL,
  type ENUM('on_device','docusign') NOT NULL DEFAULT 'on_device',
  img_path VARCHAR(500) NULL,              -- Vor-Ort PNG
  docusign_envelope_id VARCHAR(100) NULL,  -- für später
  signed_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_signatures_protocol FOREIGN KEY (protocol_id) REFERENCES protocols(id) ON DELETE CASCADE,
  CONSTRAINT fk_signatures_signer FOREIGN KEY (signer_id) REFERENCES protocol_signers(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Indizes
CREATE INDEX IF NOT EXISTS idx_signers_protocol ON protocol_signers (protocol_id, role, status);
CREATE INDEX IF NOT EXISTS idx_signatures_protocol ON protocol_signatures (protocol_id, signer_id);
