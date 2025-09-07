-- Erweitere protocol_events ENUM um 'updated' für Versionierungs-Events
ALTER TABLE protocol_events 
MODIFY COLUMN type ENUM('signed_by_tenant','signed_by_owner','sent_owner','sent_manager','sent_tenant','updated','other') NOT NULL;
