-- A.V.M TEX ERP — Financial transactions
USE avm_tex;

CREATE TABLE IF NOT EXISTS transactions (
  transaction_id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  invoice_id INT UNSIGNED DEFAULT NULL,
  customer_id INT UNSIGNED NOT NULL,
  transaction_type ENUM('payment', 'refund', 'adjustment', 'credit_memo') NOT NULL DEFAULT 'payment',
  amount DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  payment_method ENUM('cash', 'cheque', 'bank_transfer', 'card', 'other') NOT NULL DEFAULT 'cash',
  notes TEXT DEFAULT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (transaction_id),
  KEY idx_transactions_invoice (invoice_id),
  KEY idx_transactions_customer (customer_id),
  KEY idx_transactions_type (transaction_type),
  KEY idx_transactions_method (payment_method),
  CONSTRAINT fk_transactions_invoice
    FOREIGN KEY (invoice_id) REFERENCES invoices (id)
    ON UPDATE CASCADE ON DELETE SET NULL,
  CONSTRAINT fk_transactions_customer
    FOREIGN KEY (customer_id) REFERENCES customers (id)
    ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
