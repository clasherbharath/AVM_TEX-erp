-- Users table for AVM TEX ERP
CREATE TABLE IF NOT EXISTS `users` (
  `id` INT NOT NULL PRIMARY KEY AUTO_INCREMENT,
  `username` VARCHAR(100) NOT NULL UNIQUE,
  `password` VARCHAR(255) NOT NULL,
  `full_name` VARCHAR(191) DEFAULT NULL,
  `email` VARCHAR(191) DEFAULT NULL,
  `role` ENUM('admin','staff') NOT NULL DEFAULT 'staff',
  `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Create default admin user (username: admin, password: admin123)
INSERT IGNORE INTO `users` (id, username, password, full_name, email, role) VALUES
(1, 'admin', '$2y$10$Io9LqmrF4Ker.2/xJT.GWO0O.QUqRiLGeB4nRX2iafJXT6ztFP3xy', 'Administrator', 'admin@example.com', 'admin');

-- Note: password hash above is bcrypt for 'admin123'
