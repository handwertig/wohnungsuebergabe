ALTER TABLE protocol_drafts
  ADD COLUMN IF NOT EXISTS owner_id CHAR(36) NULL;

CREATE INDEX IF NOT EXISTS idx_protocol_drafts_owner ON protocol_drafts(owner_id);
