-- A.V.M TEX ERP — Phase 2 Inventory Ledger
USE avm_tex;

CREATE TABLE IF NOT EXISTS stock_movements (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  movement_type ENUM('initial', 'purchase', 'sale', 'adjustment', 'return', 'delete') NOT NULL,
  product_id INT UNSIGNED NOT NULL,
  quantity_before DECIMAL(12,2) NOT NULL,
  quantity_after DECIMAL(12,2) NOT NULL,
  quantity_changed DECIMAL(12,2) NOT NULL,
  reference_type VARCHAR(50) NOT NULL,
  reference_id INT UNSIGNED DEFAULT NULL,
  notes TEXT DEFAULT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_stock_movements_product (product_id),
  KEY idx_stock_movements_type (movement_type),
  KEY idx_stock_movements_reference (reference_type, reference_id),
  KEY idx_stock_movements_created_at (created_at),
  KEY idx_stock_movements_product_created (product_id, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO stock_movements (
  movement_type, product_id, quantity_before, quantity_after,
  quantity_changed, reference_type, reference_id, notes, created_at
)
SELECT
  'initial' AS movement_type,
  i.id AS product_id,
  0 AS quantity_before,
  i.quantity AS quantity_after,
  i.quantity AS quantity_changed,
  'inventory_seed' AS reference_type,
  i.id AS reference_id,
  'Opening stock seeded from inventory table' AS notes,
  i.created_at AS created_at
FROM inventory i
WHERE NOT EXISTS (
  SELECT 1
  FROM stock_movements sm
  WHERE sm.reference_type = 'inventory_seed'
    AND sm.reference_id = i.id
    AND sm.product_id = i.id
    AND sm.movement_type = 'initial'
);