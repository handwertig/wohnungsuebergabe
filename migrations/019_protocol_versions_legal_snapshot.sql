ALTER TABLE protocol_versions
  ADD COLUMN IF NOT EXISTS legal_snapshot MEDIUMTEXT NULL;
