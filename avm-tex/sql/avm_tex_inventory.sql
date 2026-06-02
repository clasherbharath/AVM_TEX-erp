-- Database: avm_tex
-- Module 2: Inventory Management

USE avm_tex;

CREATE TABLE IF NOT EXISTS inventory (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  product_name VARCHAR(200) NOT NULL,
  category VARCHAR(100) NOT NULL DEFAULT 'General',
  quantity DECIMAL(12,2) NOT NULL DEFAULT 0,
  unit VARCHAR(30) NOT NULL DEFAULT 'pcs',
  purchase_price DECIMAL(12,2) NOT NULL DEFAULT 0,
  selling_price DECIMAL(12,2) NOT NULL DEFAULT 0,
  supplier VARCHAR(150) DEFAULT NULL,
  gst_percentage DECIMAL(5,2) NOT NULL DEFAULT 0,
  barcode VARCHAR(50) DEFAULT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_inventory_product (product_name),
  KEY idx_inventory_category (category),
  KEY idx_inventory_barcode (barcode),
  KEY idx_inventory_quantity (quantity)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Sample inventory rows
INSERT INTO inventory (
  product_name, category, quantity, unit,
  purchase_price, selling_price, supplier, gst_percentage, barcode
) VALUES
('Cotton Fabric Roll - White', 'Fabric', 45, 'meter', 120.00, 165.00, 'Kerala Textile Suppliers', 5.00, 'AVM-CF-001'),
('Silk Saree Material', 'Fabric', 8, 'meter', 850.00, 1200.00, 'Thrissur Handloom Co', 12.00, 'AVM-SK-002'),
('Polyester Thread Box', 'Accessory', 120, 'pcs', 35.00, 55.00, 'AVM Wholesale', 5.00, 'AVM-TH-003');
