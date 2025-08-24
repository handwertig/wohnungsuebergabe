-- 022_legal_texts_name_varchar.sql
-- Ziel: legal_texts.name auf VARCHAR(64) anheben (weg von ENUM / zu kurzer Länge)
--       und Index (name, version) sicherstellen.

CREATE TABLE IF NOT EXISTS legal_texts (
  id         CHAR(36)     NOT NULL,
  name       VARCHAR(64)  NOT NULL,
  version    INT          NOT NULL,
  title      VARCHAR(255) NOT NULL,
  content    MEDIUMTEXT   NOT NULL,
  created_at DATETIME     NOT NULL,
  PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Spalte 'name' robust auf VARCHAR(64) anheben (inkl. Zeichensatz/Kollation)
ALTER TABLE legal_texts
  MODIFY COLUMN name VARCHAR(64)
  CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci
  NOT NULL;

-- Index auf (name, version) sicherstellen
DROP INDEX IF EXISTS idx_legal_name_ver ON legal_texts;
CREATE INDEX idx_legal_name_ver ON legal_texts (name, version);
