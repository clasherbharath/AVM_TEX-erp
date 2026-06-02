-- Run this in phpMyAdmin if login fails with admin / admin123
-- (fixes incorrect password hash from earlier installs)

USE avm_tex;

UPDATE admins
SET password = '$2y$10$7dOvvGASCDR8kNf0OFJRvOX0waqGU1yYc8fMs1L/2/6bDgsBJZfiq'
WHERE username = 'admin';
