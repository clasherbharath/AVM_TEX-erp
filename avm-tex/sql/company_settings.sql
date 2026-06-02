-- SQL to create company_settings table for AVM TEX ERP
CREATE TABLE IF NOT EXISTS `company_settings` (
  `id` INT NOT NULL PRIMARY KEY AUTO_INCREMENT,
  `company_name` VARCHAR(191) DEFAULT NULL,
  `gst_number` VARCHAR(64) DEFAULT NULL,
  `address` TEXT DEFAULT NULL,
  `phone` VARCHAR(64) DEFAULT NULL,
  `email` VARCHAR(191) DEFAULT NULL,
  `invoice_prefix` VARCHAR(32) DEFAULT NULL,
  `currency_symbol` VARCHAR(8) DEFAULT NULL,
  `logo_path` VARCHAR(255) DEFAULT NULL,
  `updated_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Insert an empty initial row (optional)
INSERT IGNORE INTO `company_settings` (`id`) VALUES (1);
