CREATE TABLE IF NOT EXISTS legal_texts (
  id CHAR(36) PRIMARY KEY,
  name ENUM('datenschutz','entsorgung','marketing') NOT NULL,
  version INT NOT NULL,
  title VARCHAR(120) NOT NULL,
  content MEDIUMTEXT NOT NULL,
  valid_from DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE UNIQUE INDEX IF NOT EXISTS ux_legal_texts_name_version ON legal_texts(name,version);
CREATE INDEX IF NOT EXISTS idx_legal_texts_name_valid ON legal_texts(name,valid_from);

-- Optional: erste (leere) Versionen anlegen, falls Tabelle leer ist
INSERT INTO legal_texts (id,name,version,title,content)
SELECT UUID(),'datenschutz',1,'Datenschutz','<p>(Bitte im Settings-Bereich pflegen.)</p>'
WHERE NOT EXISTS (SELECT 1 FROM legal_texts WHERE name='datenschutz');
INSERT INTO legal_texts (id,name,version,title,content)
SELECT UUID(),'entsorgung',1,'Entsorgungs-Einverständnis','<p>(Bitte im Settings-Bereich pflegen.)</p>'
WHERE NOT EXISTS (SELECT 1 FROM legal_texts WHERE name='entsorgung');
INSERT INTO legal_texts (id,name,version,title,content)
SELECT UUID(),'marketing',1,'Einwilligung E-Mail-Marketing','<p>(Bitte im Settings-Bereich pflegen.)</p>'
WHERE NOT EXISTS (SELECT 1 FROM legal_texts WHERE name='marketing');
