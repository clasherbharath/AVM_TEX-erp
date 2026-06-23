-- A.V.M TEX ERP — Phase 3 Procurement & Supplier Management
USE avm_tex;

CREATE TABLE IF NOT EXISTS suppliers (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  supplier_name VARCHAR(150) NOT NULL,
  contact_person VARCHAR(150) DEFAULT NULL,
  phone VARCHAR(20) NOT NULL,
  email VARCHAR(191) DEFAULT NULL,
  gst_number VARCHAR(64) DEFAULT NULL,
  address TEXT NOT NULL,
  city VARCHAR(100) DEFAULT NULL,
  state VARCHAR(100) DEFAULT NULL,
  pincode VARCHAR(20) DEFAULT NULL,
  payment_terms VARCHAR(100) DEFAULT NULL,
  opening_balance DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_suppliers_phone (phone),
  KEY idx_suppliers_name (supplier_name),
  KEY idx_suppliers_gst (gst_number),
  KEY idx_suppliers_city (city)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS purchase_orders (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  po_number VARCHAR(50) NOT NULL,
  supplier_id INT UNSIGNED NOT NULL,
  order_date DATE NOT NULL,
  expected_date DATE DEFAULT NULL,
  status ENUM('draft', 'ordered', 'partial', 'received', 'cancelled') NOT NULL DEFAULT 'ordered',
  payment_status ENUM('unpaid', 'partial', 'paid') NOT NULL DEFAULT 'unpaid',
  subtotal DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  discount DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  gst_total DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  grand_total DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  notes TEXT DEFAULT NULL,
  received_at TIMESTAMP NULL DEFAULT NULL,
  created_by INT UNSIGNED DEFAULT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_purchase_orders_number (po_number),
  KEY idx_purchase_orders_supplier (supplier_id),
  KEY idx_purchase_orders_date (order_date),
  KEY idx_purchase_orders_status (status),
  KEY idx_purchase_orders_payment_status (payment_status),
  CONSTRAINT fk_purchase_orders_supplier
    FOREIGN KEY (supplier_id) REFERENCES suppliers (id)
    ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS purchase_items (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  purchase_order_id INT UNSIGNED NOT NULL,
  product_id INT UNSIGNED NOT NULL,
  product_name_snapshot VARCHAR(200) NOT NULL,
  quantity DECIMAL(12,2) NOT NULL,
  received_quantity DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  purchase_price DECIMAL(12,2) NOT NULL,
  selling_price_snapshot DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  gst_percentage DECIMAL(5,2) NOT NULL DEFAULT 0.00,
  line_subtotal DECIMAL(12,2) NOT NULL,
  line_gst DECIMAL(12,2) NOT NULL,
  line_total DECIMAL(12,2) NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_purchase_items_order (purchase_order_id),
  KEY idx_purchase_items_product (product_id),
  CONSTRAINT fk_purchase_items_order
    FOREIGN KEY (purchase_order_id) REFERENCES purchase_orders (id)
    ON UPDATE CASCADE ON DELETE CASCADE,
  CONSTRAINT fk_purchase_items_product
    FOREIGN KEY (product_id) REFERENCES inventory (id)
    ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS supplier_payments (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  supplier_id INT UNSIGNED NOT NULL,
  purchase_order_id INT UNSIGNED DEFAULT NULL,
  payment_date DATE NOT NULL,
  amount DECIMAL(12,2) NOT NULL,
  payment_method ENUM('cash', 'cheque', 'bank_transfer', 'card', 'other') NOT NULL DEFAULT 'cash',
  reference_number VARCHAR(100) DEFAULT NULL,
  notes TEXT DEFAULT NULL,
  recorded_by INT UNSIGNED DEFAULT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_supplier_payments_supplier (supplier_id),
  KEY idx_supplier_payments_po (purchase_order_id),
  KEY idx_supplier_payments_date (payment_date),
  CONSTRAINT fk_supplier_payments_supplier
    FOREIGN KEY (supplier_id) REFERENCES suppliers (id)
    ON UPDATE CASCADE ON DELETE RESTRICT,
  CONSTRAINT fk_supplier_payments_po
    FOREIGN KEY (purchase_order_id) REFERENCES purchase_orders (id)
    ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
