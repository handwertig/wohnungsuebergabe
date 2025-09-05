-- 025_user_roles_assignments.sql – Erweiterte Benutzerrollen und Zuweisungen

-- Erweitere die users-Tabelle um neue Rollen und Zuweisungsfelder
ALTER TABLE users 
MODIFY COLUMN role ENUM('admin','hausverwaltung','eigentuemer') NOT NULL DEFAULT 'eigentuemer';

-- Tabelle für Benutzer-Manager-Zuweisungen (Hausverwaltungsbenutzer)
CREATE TABLE IF NOT EXISTS user_manager_assignments (
  id CHAR(36) PRIMARY KEY,
  user_id CHAR(36) NOT NULL,
  manager_id CHAR(36) NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_uma_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_uma_manager FOREIGN KEY (manager_id) REFERENCES managers(id) ON DELETE CASCADE,
  UNIQUE KEY uk_user_manager (user_id, manager_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Tabelle für Benutzer-Eigentümer-Zuweisungen (Eigentümerbenutzer)
CREATE TABLE IF NOT EXISTS user_owner_assignments (
  id CHAR(36) PRIMARY KEY,
  user_id CHAR(36) NOT NULL,
  owner_id CHAR(36) NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_uoa_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_uoa_owner FOREIGN KEY (owner_id) REFERENCES owners(id) ON DELETE CASCADE,
  UNIQUE KEY uk_user_owner (user_id, owner_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Erweitere die users-Tabelle um Profilfelder (nur wenn sie noch nicht existieren)
ALTER TABLE users 
ADD COLUMN IF NOT EXISTS company VARCHAR(150) NULL AFTER email,
ADD COLUMN IF NOT EXISTS phone VARCHAR(50) NULL AFTER company,
ADD COLUMN IF NOT EXISTS address TEXT NULL AFTER phone;

-- Füge updated_at hinzu falls nicht vorhanden 
SET @sql = (
  SELECT IF(
    COUNT(*) = 0,
    'ALTER TABLE users ADD COLUMN updated_at DATETIME NULL AFTER created_at',
    'SELECT "updated_at column already exists" as message'
  )
  FROM INFORMATION_SCHEMA.COLUMNS 
  WHERE TABLE_SCHEMA = DATABASE() 
    AND TABLE_NAME = 'users' 
    AND COLUMN_NAME = 'updated_at'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Index für bessere Performance bei Rollenabfragen
CREATE INDEX IF NOT EXISTS idx_users_role ON users(role);
