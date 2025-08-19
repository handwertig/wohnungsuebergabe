-- Protokolle
ALTER TABLE protocols
  ADD COLUMN IF NOT EXISTS manager_id CHAR(36) NULL,
  ADD CONSTRAINT fk_protocols_manager FOREIGN KEY (manager_id) REFERENCES managers(id) ON DELETE SET NULL;
CREATE INDEX IF NOT EXISTS idx_protocols_manager ON protocols(manager_id);

-- Drafts
ALTER TABLE protocol_drafts
  ADD COLUMN IF NOT EXISTS manager_id CHAR(36) NULL;
CREATE INDEX IF NOT EXISTS idx_protocol_drafts_manager ON protocol_drafts(manager_id);
