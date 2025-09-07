-- Reparatur für protocol_events Tabelle - fügt fehlende created_by Spalte hinzu

ALTER TABLE protocol_events 
ADD COLUMN created_by VARCHAR(255) NULL AFTER message;

-- Index für bessere Performance
CREATE INDEX IF NOT EXISTS idx_events_created_by ON protocol_events (created_by);
