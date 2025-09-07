-- Direkte SQL-Reparatur für Protocol Save Problem

-- 1. Protocol Events Tabelle: created_by Spalte hinzufügen
ALTER TABLE protocol_events 
ADD COLUMN IF NOT EXISTS created_by VARCHAR(255) NULL AFTER message;

-- 2. Index für bessere Performance  
CREATE INDEX IF NOT EXISTS idx_events_created_by ON protocol_events (created_by);

-- 3. Teste mit einem Event
INSERT IGNORE INTO protocol_events (id, protocol_id, type, message, created_by, created_at) 
VALUES (UUID(), '82cc7de7-7d1e-11f0-89a6-822b82242c5d', 'other', 'SQL-Reparatur erfolgreich', 'sql@repair.com', NOW());

-- 4. Prüfe System-Log Tabelle
CREATE TABLE IF NOT EXISTS system_log (
  id CHAR(36) PRIMARY KEY DEFAULT (UUID()),
  user_email VARCHAR(255) NOT NULL DEFAULT 'system',
  user_ip VARCHAR(45) NULL,
  action_type VARCHAR(100) NOT NULL,
  action_description TEXT NOT NULL,
  resource_type VARCHAR(50) NULL,
  resource_id CHAR(36) NULL,
  additional_data JSON NULL,
  request_method VARCHAR(10) NULL,
  request_url VARCHAR(500) NULL,
  user_agent TEXT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  
  INDEX idx_user_email (user_email),
  INDEX idx_action_type (action_type),
  INDEX idx_resource (resource_type, resource_id),
  INDEX idx_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 5. Test-Eintrag in System-Log
INSERT INTO system_log (user_email, action_type, action_description, resource_id, created_at) 
VALUES ('sql@repair.com', 'system_repair', 'Protocol Save SQL-Reparatur durchgeführt', '82cc7de7-7d1e-11f0-89a6-822b82242c5d', NOW());

-- 6. Zeige Reparatur-Status
SELECT 'Protocol Events Reparatur:' as status, COUNT(*) as anzahl_events FROM protocol_events WHERE created_by IS NOT NULL;
SELECT 'System Log Reparatur:' as status, COUNT(*) as anzahl_logs FROM system_log WHERE action_type = 'system_repair';

SELECT '✅ SQL-Reparatur abgeschlossen!' as final_status;
