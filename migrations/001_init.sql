-- Initial schema
CREATE TABLE IF NOT EXISTS users (
  id CHAR(36) PRIMARY KEY,
  email VARCHAR(255) NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  role ENUM('admin','staff') NOT NULL DEFAULT 'admin',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO users (id, email, password_hash, role)
VALUES (UUID(), 'admin@example.com', '$2y$10$Zk8N9fM1bG5x9R6o4m9T4uZkQh2m9s0pZr2mQ8x7HcVqY1v4n8BPS', 'admin')
ON DUPLICATE KEY UPDATE email=email;
-- password_hash corresponds to 'admin123' (bcrypt)
