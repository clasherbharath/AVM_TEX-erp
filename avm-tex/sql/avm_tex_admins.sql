-- Database: avm_tex
-- Table: admins
--
-- Sample admin credentials:
--   username: admin
--   password: admin123
--
-- Password hash generated with PHP password_hash('admin123', PASSWORD_DEFAULT).

CREATE DATABASE IF NOT EXISTS avm_tex
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE avm_tex;

CREATE TABLE IF NOT EXISTS admins (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  username VARCHAR(50) NOT NULL,
  password VARCHAR(255) NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_admins_username (username)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Correct bcrypt hash for password: admin123
INSERT INTO admins (username, password)
VALUES (
  'admin',
  '$2y$10$7dOvvGASCDR8kNf0OFJRvOX0waqGU1yYc8fMs1L/2/6bDgsBJZfiq'
)
ON DUPLICATE KEY UPDATE
  password = VALUES(password),
  username = VALUES(username);
