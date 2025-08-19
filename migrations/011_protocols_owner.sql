ALTER TABLE protocols
  ADD COLUMN IF NOT EXISTS owner_id CHAR(36) NULL;

ALTER TABLE protocols
  ADD CONSTRAINT fk_protocols_owner
  FOREIGN KEY (owner_id) REFERENCES owners(id)
  ON DELETE SET NULL;

CREATE INDEX IF NOT EXISTS idx_protocols_owner ON protocols(owner_id);
