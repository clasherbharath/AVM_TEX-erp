-- Database: avm_tex
-- Migration: add min_stock threshold to inventory

USE avm_tex;

ALTER TABLE inventory
  ADD COLUMN min_stock DECIMAL(12,2) NOT NULL DEFAULT 10 AFTER quantity,
  ADD KEY idx_inventory_min_stock (min_stock);
